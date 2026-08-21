<?php

namespace Tests\Feature;

use App\Enums\SubscriptionBillingPeriodEnum;
use App\Enums\SubscriptionStatusEnum;
use App\Enums\UserSettingsEnum;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class SubscriptionStateTest extends TestCase
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

    public function test_the_accessor_classifies_each_state(): void
    {
        $canceled = $this->subscription(['canceled_at' => now()->subDay()->toDateString()]);
        $canceledInFuture = $this->subscription(['canceled_at' => now()->addMonth()->toDateString()]);
        $trialing = $this->subscription(['trial_ends_at' => now()->addMonth()->toDateString()]);
        $trialEndedToday = $this->subscription(['trial_ends_at' => now()->toDateString()]);
        $trialExpired = $this->subscription(['trial_ends_at' => now()->subDay()->toDateString()]);
        $plain = $this->subscription();

        $this->assertSame(SubscriptionStatusEnum::CANCELED, $canceled->status);
        $this->assertSame(
            SubscriptionStatusEnum::CANCELED,
            $canceledInFuture->status,
            'Qualquer data de cancelamento conta, inclusive futura — é o que o gerador do cloud já faz.',
        );
        $this->assertSame(SubscriptionStatusEnum::TRIAL, $trialing->status);
        $this->assertSame(
            SubscriptionStatusEnum::TRIAL,
            $trialEndedToday->status,
            'O limite é inclusivo: o teste vale até o fim do dia em que termina.',
        );
        $this->assertSame(SubscriptionStatusEnum::ACTIVE, $trialExpired->status);
        $this->assertSame(SubscriptionStatusEnum::ACTIVE, $plain->status);
    }

    public function test_cancellation_beats_an_open_trial(): void
    {
        $subscription = $this->subscription([
            'trial_ends_at' => now()->addMonth()->toDateString(),
            'canceled_at' => now()->subDay()->toDateString(),
        ]);

        $this->assertSame(SubscriptionStatusEnum::CANCELED, $subscription->status);
    }

    public function test_every_scope_selects_exactly_what_the_accessor_classifies(): void
    {
        $this->subscription(['canceled_at' => now()->subDay()->toDateString()]);
        $this->subscription(['canceled_at' => now()->addMonth()->toDateString()]);
        $this->subscription(['trial_ends_at' => now()->addMonth()->toDateString()]);
        $this->subscription(['trial_ends_at' => now()->toDateString()]);
        $this->subscription(['trial_ends_at' => now()->subDay()->toDateString()]);
        $this->subscription();
        $this->subscription([
            'trial_ends_at' => now()->addMonth()->toDateString(),
            'canceled_at' => now()->subDay()->toDateString(),
        ]);

        $byAccessor = Subscription::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Subscription $subscription): string => $subscription->status->value);

        $expected = [
            'canceled' => $byAccessor->get(SubscriptionStatusEnum::CANCELED->value, collect())->pluck('id')->all(),
            'trial' => $byAccessor->get(SubscriptionStatusEnum::TRIAL->value, collect())->pluck('id')->all(),
            'active' => $byAccessor->get(SubscriptionStatusEnum::ACTIVE->value, collect())->pluck('id')->all(),
        ];

        $this->assertSame($expected['canceled'], Subscription::query()->canceled()->orderBy('id')->pluck('id')->all());
        $this->assertSame($expected['trial'], Subscription::query()->inTrial()->orderBy('id')->pluck('id')->all());
        $this->assertSame($expected['active'], Subscription::query()->active()->orderBy('id')->pluck('id')->all());

        $this->assertSame(
            7,
            count($expected['canceled']) + count($expected['trial']) + count($expected['active']),
            'Os três estados precisam particionar o conjunto: nenhuma linha fora, nenhuma em dois.',
        );
    }

    public function test_the_accessor_and_the_scopes_agree_west_of_utc(): void
    {
        $this->user->settings()->set(UserSettingsEnum::LOCALIZATION_TIMEZONE->value, 'America/Sao_Paulo');

        $endingToday = $this->subscription(['trial_ends_at' => now('America/Sao_Paulo')->toDateString()]);

        $this->assertSame(
            SubscriptionStatusEnum::TRIAL,
            $endingToday->status,
            'O accessor precisa concordar com o scope no ultimo dia do teste, em qualquer fuso.',
        );

        $this->assertSame(
            [$endingToday->id],
            Subscription::query()->inTrial()->pluck('id')->all(),
        );
    }
}
