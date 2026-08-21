<?php

namespace Tests\Feature;

use App\Enums\SubscriptionBillingPeriodEnum;
use App\Enums\SubscriptionStatusEnum;
use App\Filament\App\Resources\SubscriptionResource;
use App\Filament\App\Resources\SubscriptionResource\Pages\CreateSubscription;
use App\Filament\App\Resources\SubscriptionResource\Pages\EditSubscription;
use App\Filament\App\Resources\SubscriptionResource\Pages\ListSubscriptions;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class SubscriptionResourceTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->company = $this->actingInCompany($this->user);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
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

    public function test_list_does_not_render_a_total_that_mixes_currencies(): void
    {
        $this->subscription(['service' => 'Netflix', 'price' => 54.00, 'currency' => 'BRL']);
        $this->subscription(['service' => 'Rambox', 'price' => 34.00, 'currency' => 'USD']);

        Livewire::test(ListSubscriptions::class)
            ->assertSuccessful()
            ->assertDontSee('88.00')
            ->assertDontSee('88,00');
    }

    public function test_currency_field_rejects_a_currency_that_is_not_active(): void
    {
        Currency::factory()->create(['code' => 'BRL', 'name' => 'Brazilian Real', 'is_active' => true]);
        Currency::factory()->create(['code' => 'JPY', 'name' => 'Japanese Yen', 'is_active' => false]);

        Livewire::test(CreateSubscription::class)
            ->fillForm([
                'service' => 'Netflix',
                'price' => 54.00,
                'currency' => 'JPY',
                'payment_method' => 'Cartão de crédito',
                'billing_period' => SubscriptionBillingPeriodEnum::MONTHLY->value,
                'started_on' => '2024-08-28',
            ])
            ->call('create')
            ->assertHasFormErrors(['currency']);
    }

    public function test_a_legacy_currency_that_is_no_longer_active_can_still_be_saved(): void
    {
        Currency::factory()->create(['code' => 'BRL', 'name' => 'Brazilian Real', 'is_active' => true]);

        $subscription = $this->subscription(['currency' => 'XAU']);

        Livewire::test(EditSubscription::class, ['record' => $subscription->getRouteKey()])
            ->assertFormSet(['currency' => 'XAU'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('XAU', $subscription->refresh()->currency);
    }

    public function test_subscription_can_carry_a_vendor_and_the_form_persists_it(): void
    {
        Currency::factory()->create(['code' => 'BRL', 'name' => 'Brazilian Real', 'is_active' => true]);

        $vendor = Vendor::create(['name' => 'Hostinger', 'company_id' => $this->company->id]);

        Livewire::test(CreateSubscription::class)
            ->fillForm([
                'service' => 'Hostgator',
                'price' => 1713.00,
                'currency' => 'BRL',
                'payment_method' => 'Cartão de crédito',
                'billing_period' => SubscriptionBillingPeriodEnum::SIX_MONTHS->value,
                'vendor_id' => $vendor->id,
                'started_on' => '2024-08-28',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('subscriptions', [
            'service' => 'Hostgator',
            'vendor_id' => $vendor->id,
        ]);

        $this->assertSame('Hostinger', Subscription::where('service', 'Hostgator')->sole()->vendor->name);
    }

    public function test_deleting_a_vendor_leaves_the_subscription_alive_without_one(): void
    {
        $vendor = Vendor::create(['name' => 'Hostinger', 'company_id' => $this->company->id]);
        $subscription = $this->subscription(['vendor_id' => $vendor->id]);

        $vendor->delete();

        $this->assertNull($subscription->refresh()->vendor_id);
        $this->assertSame('Netflix', $subscription->service);
    }

    public function test_started_on_is_required_when_creating_a_subscription(): void
    {
        Currency::factory()->create(['code' => 'BRL', 'name' => 'Brazilian Real', 'is_active' => true]);

        Livewire::test(CreateSubscription::class)
            ->fillForm([
                'service' => 'Netflix',
                'price' => 54.00,
                'currency' => 'BRL',
                'payment_method' => 'Cartão de crédito',
                'billing_period' => SubscriptionBillingPeriodEnum::MONTHLY->value,
                'started_on' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['started_on']);
    }

    public function test_started_on_cannot_be_null_at_the_database_level(): void
    {
        $this->expectException(QueryException::class);

        DB::table('subscriptions')->insert([
            'company_id' => $this->company->id,
            'service' => 'Netflix',
            'price' => 5400,
            'currency' => 'BRL',
            'payment_method' => 'Cartão de crédito',
            'billing_period' => SubscriptionBillingPeriodEnum::MONTHLY->value,
            'started_on' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_creating_a_subscription_needs_no_status_input(): void
    {
        Currency::factory()->create(['code' => 'BRL', 'name' => 'Brazilian Real', 'is_active' => true]);

        Livewire::test(CreateSubscription::class)
            ->fillForm([
                'service' => 'Netflix',
                'price' => 54.00,
                'currency' => 'BRL',
                'payment_method' => 'Cartão de crédito',
                'billing_period' => SubscriptionBillingPeriodEnum::MONTHLY->value,
                'started_on' => '2024-08-28',
                'canceled_at' => null,
                'trial_ends_at' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            SubscriptionStatusEnum::ACTIVE,
            Subscription::where('service', 'Netflix')->sole()->status,
        );
    }

    public function test_cancelling_by_date_alone_moves_the_row_into_the_canceled_filter(): void
    {
        $canceled = $this->subscription([
            'service' => 'Rambox',
            'canceled_at' => now()->subDay()->toDateString(),
        ]);
        $living = $this->subscription(['service' => 'Netflix']);

        Livewire::test(ListSubscriptions::class)
            ->filterTable('state', 'canceled')
            ->assertCanSeeTableRecords([$canceled])
            ->assertCanNotSeeTableRecords([$living]);
    }

    public function test_the_state_filter_separates_trialing_from_active(): void
    {
        $trialing = $this->subscription([
            'service' => 'Trialing',
            'trial_ends_at' => now()->addMonth()->toDateString(),
        ]);
        $active = $this->subscription(['service' => 'Active']);
        $canceled = $this->subscription([
            'service' => 'Canceled',
            'canceled_at' => now()->subDay()->toDateString(),
        ]);

        Livewire::test(ListSubscriptions::class)
            ->filterTable('state', SubscriptionStatusEnum::TRIAL->value)
            ->assertCanSeeTableRecords([$trialing])
            ->assertCanNotSeeTableRecords([$active, $canceled]);

        Livewire::test(ListSubscriptions::class)
            ->filterTable('state', SubscriptionStatusEnum::ACTIVE->value)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$trialing, $canceled]);
    }

    public function test_the_status_column_is_no_longer_backed_by_a_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('subscriptions', 'status'),
            'A coluna foi dropada: o estado é derivado das datas.',
        );
    }

    public function test_the_view_page_shows_the_derived_state(): void
    {
        $subscription = $this->subscription();

        $this->get(SubscriptionResource::getUrl('view', ['record' => $subscription]))
            ->assertOk()
            ->assertSeeText((string) SubscriptionStatusEnum::ACTIVE->getLabel());
    }
}
