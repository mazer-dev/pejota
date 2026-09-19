<?php

namespace Tests\Feature;

use App\Enums\SubscriptionBillingPeriodEnum;
use App\Filament\App\Resources\SubscriptionResource;
use App\Filament\App\Resources\SubscriptionResource\Pages\CreateSubscription;
use App\Filament\App\Resources\SubscriptionResource\Pages\EditSubscription;
use App\Filament\App\Resources\SubscriptionResource\Pages\ListSubscriptions;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\ActsInCompany;
use Tests\Support\SubscriptionExtensionStub;
use Tests\TestCase;

class SubscriptionResourceExtensionTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private User $owner;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->company = $this->actingInCompany($this->owner);

        SubscriptionExtensionStub::reset();
    }

    protected function tearDown(): void
    {
        SubscriptionExtensionStub::reset();
        config(['pejota.subscription_resource_extensions' => []]);

        parent::tearDown();
    }

    private function registerStub(): void
    {
        config(['pejota.subscription_resource_extensions' => [SubscriptionExtensionStub::class]]);
    }

    /** @param array<string, mixed> $extra */
    private function subscription(array $extra = []): Subscription
    {
        return Subscription::create([
            'service' => 'Netflix',
            'price' => 54.00,
            'currency' => 'BRL',
            'payment_method' => 'Cartão de crédito',
            'billing_period' => SubscriptionBillingPeriodEnum::MONTHLY->value,
            'started_on' => '2024-08-28',
            ...$extra,
        ]);
    }

    /**
     * Sem extensão registrada — que é o estado permanente do projeto aberto — o
     * formulário continua sendo o `Grid` plano de sempre. Um `Tabs` de aba única
     * aqui seria custo visual sem contrapartida.
     */
    public function test_without_an_extension_the_form_has_no_tabs(): void
    {
        $this->assertSame([], SubscriptionResource::activeExtensions());

        $subscription = $this->subscription();

        Livewire::test(EditSubscription::class, ['record' => $subscription->getKey()])
            ->assertSchemaComponentDoesNotExist('stub.note')
            ->assertDontSee(__('Subscription data'));
    }

    /**
     * `assertSchemaComponentExists` procura a chave em
     * `getFlatComponents(withHidden: true)`, e a chave de um componente é derivada do
     * `getStatePath(isAbsolute: false)` dele
     * (`vendor/filament/schemas/src/Components/Concerns/HasKey.php:35`). Achar
     * `stub.note` prova as DUAS coisas que esta task entrega: a aba da extensão entrou no
     * schema, e o `statePath` dela aninhou o campo.
     *
     * NÃO asserte o VALOR do estado aqui. Hidratar `stub.note` a partir do record depende
     * de `mutateFormDataBeforeFill`, que a Task A2 acrescenta a `EditSubscription`; o
     * ciclo completo é provado lá.
     */
    public function test_a_registered_extension_adds_its_tab_nested_under_its_state_path(): void
    {
        $this->registerStub();

        $subscription = $this->subscription();

        Livewire::test(EditSubscription::class, ['record' => $subscription->getKey()])
            ->assertSchemaComponentExists('stub.note');
    }

    public function test_one_save_writes_the_record_and_the_extension_state(): void
    {
        $this->registerStub();

        $subscription = $this->subscription();

        Livewire::test(EditSubscription::class, ['record' => $subscription->getKey()])
            ->fillForm([
                'service' => 'Spotify',
                'stub' => ['note' => 'vinda da aba'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $subscription->refresh();

        $this->assertSame('Spotify', $subscription->service);
        $this->assertSame('vinda da aba', $subscription->obs);
    }

    /**
     * `persist()` roda no `afterSave`, DEPOIS de `handleRecordUpdate`. O stub e o
     * formulário do core escrevem os dois em `obs`, e o valor da extensão é o que tem de
     * sobreviver — se a ordem se inverter, o `obs` do core vence e o teste cai.
     */
    public function test_the_extension_writes_after_the_record_is_saved(): void
    {
        $this->registerStub();

        $subscription = $this->subscription();

        Livewire::test(EditSubscription::class, ['record' => $subscription->getKey()])
            ->fillForm([
                'obs' => 'escrito pelo core',
                'stub' => ['note' => 'escrito pela extensão'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('escrito pela extensão', $subscription->refresh()->obs);
    }

    /**
     * O `Halt` sai de `refuse()`, que roda ANTES de `handleRecordUpdate`. A assinatura
     * não pode ter sido tocada.
     */
    public function test_a_refusing_extension_leaves_the_subscription_untouched(): void
    {
        $this->registerStub();
        SubscriptionExtensionStub::$refuses = true;

        $subscription = $this->subscription();

        Livewire::test(EditSubscription::class, ['record' => $subscription->getKey()])
            ->fillForm(['service' => 'Spotify'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Netflix', $subscription->refresh()->service);
    }

    /**
     * Sem `$hasDatabaseTransactions = true` nas páginas, o `UPDATE` da assinatura fica
     * gravado e só a extensão falha — que é o save pela metade que a fusão existe para
     * não criar. `Panel::$hasDatabaseTransactions` é `false` por padrão.
     */
    public function test_an_exception_in_persist_rolls_the_subscription_back(): void
    {
        $this->registerStub();
        SubscriptionExtensionStub::$throwsOnPersist = true;

        $subscription = $this->subscription();

        try {
            Livewire::test(EditSubscription::class, ['record' => $subscription->getKey()])
                ->fillForm(['service' => 'Spotify'])
                ->call('save');

            $this->fail('Expected the stub persist failure to propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('stub persist failure', $exception->getMessage());
        }

        $this->assertSame('Netflix', $subscription->refresh()->service);
    }

    /**
     * Gêmeo do teste de rollback da edição, para a página de criação. Sem ele,
     * `$hasDatabaseTransactions` em `CreateSubscription` é removível sem nada ficar
     * vermelho, e a spec manda a transação nas duas páginas.
     */
    public function test_an_exception_in_persist_rolls_the_creation_back(): void
    {
        $this->registerStub();
        SubscriptionExtensionStub::$throwsOnPersist = true;

        Currency::factory()->create(['code' => 'BRL', 'name' => 'Brazilian Real', 'is_active' => true]);

        try {
            Livewire::test(CreateSubscription::class)
                ->fillForm([
                    'service' => 'Disney+',
                    'price' => 40,
                    'currency' => 'BRL',
                    'payment_method' => 'Cartão de crédito',
                    'billing_period' => SubscriptionBillingPeriodEnum::MONTHLY->value,
                    'started_on' => '2026-09-01',
                    'stub' => ['note' => 'criada junto'],
                ])
                ->call('create');

            $this->fail('Expected the stub persist failure to propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('stub persist failure', $exception->getMessage());
        }

        $this->assertSame(0, Subscription::query()->where('service', 'Disney+')->count());
    }

    public function test_creating_writes_both_in_one_click(): void
    {
        $this->registerStub();

        Currency::factory()->create(['code' => 'BRL', 'name' => 'Brazilian Real', 'is_active' => true]);

        Livewire::test(CreateSubscription::class)
            ->fillForm([
                'service' => 'Disney+',
                'price' => 40,
                'currency' => 'BRL',
                'payment_method' => 'Cartão de crédito',
                'billing_period' => SubscriptionBillingPeriodEnum::MONTHLY->value,
                'started_on' => '2026-09-01',
                'stub' => ['note' => 'criada junto'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $subscription = Subscription::query()->where('service', 'Disney+')->sole();

        $this->assertSame('criada junto', $subscription->obs);
    }

    /**
     * Extensão inativa não compõe aba, então a chave nem existe em `$data` — e
     * `persistExtensionState()` tem de PULAR, não persistir vazio. Um `?? []` no lugar da
     * checagem apagaria o `obs` de toda assinatura salva por um tenant sem a feature.
     */
    public function test_an_inactive_extension_does_not_persist(): void
    {
        $this->registerStub();
        SubscriptionExtensionStub::$active = false;

        $subscription = $this->subscription(['obs' => 'preservado']);

        Livewire::test(EditSubscription::class, ['record' => $subscription->getKey()])
            ->fillForm(['service' => 'Spotify'])
            ->call('save')
            ->assertHasNoFormErrors();

        $subscription->refresh();

        $this->assertSame('Spotify', $subscription->service);
        $this->assertSame('preservado', $subscription->obs);
    }

    public function test_without_an_extension_the_query_adds_no_eager_load(): void
    {
        $this->assertSame([], array_keys(SubscriptionResource::getEloquentQuery()->getEagerLoads()));
    }

    public function test_a_registered_extension_adds_its_eager_load(): void
    {
        $this->registerStub();

        $this->assertContains('vendor', array_keys(SubscriptionResource::getEloquentQuery()->getEagerLoads()));
    }

    public function test_a_registered_extension_adds_its_column_and_filter(): void
    {
        $this->registerStub();

        $noted = $this->subscription(['service' => 'Com nota', 'obs' => 'anotada']);
        $bare = $this->subscription(['service' => 'Sem nota']);

        Livewire::test(ListSubscriptions::class)
            ->assertCanSeeTableRecords([$noted, $bare])
            ->assertCanRenderTableColumn('obs')
            ->filterTable('stub_noted')
            ->assertCanSeeTableRecords([$noted])
            ->assertCanNotSeeTableRecords([$bare]);
    }
}
