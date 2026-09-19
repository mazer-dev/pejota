<?php

namespace Tests\Feature;

use App\Enums\SubscriptionBillingPeriodEnum;
use App\Filament\App\Resources\SubscriptionResource;
use App\Filament\App\Resources\SubscriptionResource\Pages\EditSubscription;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
}
