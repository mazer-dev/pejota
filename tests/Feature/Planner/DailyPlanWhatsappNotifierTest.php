<?php

namespace Tests\Feature\Planner;

use App\Enums\CompanySettingsEnum;
use App\Enums\DailyPlanItemTypeEnum;
use App\Enums\DailyPlanModeEnum;
use App\Enums\DailyPlanStatusEnum;
use App\Models\DailyPlan;
use App\Models\User;
use App\Services\Evolution\EvolutionApiClient;
use App\Services\Planner\DailyPlanWhatsappNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DailyPlanWhatsappNotifierTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        config([
            'services.assistant.whatsapp.enabled' => true,
            'services.assistant.whatsapp.instance' => 'Assistente_Pejota',
            'services.planner.delivery_numbers' => ['5554999371490', '5581985573942'],
        ]);
    }

    private function makeReadyPlan(): DailyPlan
    {
        $plan = DailyPlan::create([
            'company_id' => $this->user->company->id,
            'plan_date' => now()->toDateString(),
            'mode' => DailyPlanModeEnum::FULL,
            'status' => DailyPlanStatusEnum::READY,
            'capacity_minutes' => 480,
            'planned_minutes' => 135,
            'summary' => 'Dia focado na proposta.',
            'generated_at' => now(),
        ]);

        $plan->items()->create([
            'company_id' => $this->user->company->id,
            'position' => 1,
            'type' => DailyPlanItemTypeEnum::FOLLOW_UP,
            'title' => 'Cobrar retorno da Vivianne',
            'reason' => 'sem resposta há 2 dias',
            'estimated_minutes' => 15,
            'suggested_message' => 'Oi Vivianne, tudo bem? Conseguiu ver aquele acesso?',
        ]);

        return $plan->load(['items', 'company']);
    }

    public function test_sends_the_plan_to_every_configured_number_and_marks_sent_at(): void
    {
        $evolution = Mockery::mock(EvolutionApiClient::class);
        $evolution->shouldReceive('sendTextToNumber')
            ->once()
            ->withArgs(fn (string $instance, string $number, string $text): bool => $instance === 'Assistente_Pejota'
                && $number === '5554999371490'
                && str_contains($text, 'Plano do dia')
                && str_contains($text, 'Cobrar retorno da Vivianne')
                && str_contains($text, 'Oi Vivianne'));
        $evolution->shouldReceive('sendTextToNumber')
            ->once()
            ->withArgs(fn (string $instance, string $number): bool => $number === '5581985573942');
        $this->instance(EvolutionApiClient::class, $evolution);

        $sent = app(DailyPlanWhatsappNotifier::class)->send($this->makeReadyPlan());

        $this->assertTrue($sent);
        $this->assertNotNull(DailyPlan::allTenants()->where('company_id', $this->user->company->id)->first()->sent_at);
    }

    public function test_does_not_resend_an_already_sent_plan_unless_forced(): void
    {
        $plan = $this->makeReadyPlan();
        $plan->update(['sent_at' => now()]);

        $evolution = Mockery::mock(EvolutionApiClient::class);
        $evolution->shouldNotReceive('sendTextToNumber');
        $this->instance(EvolutionApiClient::class, $evolution);

        $this->assertFalse(app(DailyPlanWhatsappNotifier::class)->send($plan->fresh()->load(['items', 'company'])));

        $evolutionForced = Mockery::mock(EvolutionApiClient::class);
        $evolutionForced->shouldReceive('sendTextToNumber')->twice();
        $this->instance(EvolutionApiClient::class, $evolutionForced);

        $this->assertTrue(app(DailyPlanWhatsappNotifier::class)->send($plan->fresh()->load(['items', 'company']), force: true));
    }

    public function test_respects_the_company_delivery_setting(): void
    {
        $this->user->company->settings()->set(CompanySettingsEnum::PLANNER_WHATSAPP_DELIVERY->value, false);

        $evolution = Mockery::mock(EvolutionApiClient::class);
        $evolution->shouldNotReceive('sendTextToNumber');
        $this->instance(EvolutionApiClient::class, $evolution);

        $this->assertFalse(app(DailyPlanWhatsappNotifier::class)->send($this->makeReadyPlan()));
    }

    public function test_does_nothing_when_whatsapp_is_disabled_or_numbers_are_missing(): void
    {
        $evolution = Mockery::mock(EvolutionApiClient::class);
        $evolution->shouldNotReceive('sendTextToNumber');
        $this->instance(EvolutionApiClient::class, $evolution);

        $plan = $this->makeReadyPlan();

        config(['services.assistant.whatsapp.enabled' => false]);
        $this->assertFalse(app(DailyPlanWhatsappNotifier::class)->send($plan));

        config(['services.assistant.whatsapp.enabled' => true, 'services.planner.delivery_numbers' => []]);
        $this->assertFalse(app(DailyPlanWhatsappNotifier::class)->send($plan));
    }

    public function test_light_plan_without_items_sends_the_rest_message(): void
    {
        $plan = DailyPlan::create([
            'company_id' => $this->user->company->id,
            'plan_date' => now()->toDateString(),
            'mode' => DailyPlanModeEnum::LIGHT,
            'status' => DailyPlanStatusEnum::READY,
            'generated_at' => now(),
        ]);

        $evolution = Mockery::mock(EvolutionApiClient::class);
        $evolution->shouldReceive('sendTextToNumber')
            ->twice()
            ->withArgs(fn (string $instance, string $number, string $text): bool => str_contains($text, 'Nada urgente hoje'));
        $this->instance(EvolutionApiClient::class, $evolution);

        $this->assertTrue(app(DailyPlanWhatsappNotifier::class)->send($plan->load(['items', 'company'])));
    }
}
