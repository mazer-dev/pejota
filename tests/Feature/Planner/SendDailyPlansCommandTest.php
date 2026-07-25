<?php

namespace Tests\Feature\Planner;

use App\Enums\DailyPlanModeEnum;
use App\Enums\DailyPlanStatusEnum;
use App\Models\DailyPlan;
use App\Models\User;
use App\Services\Ai\AiCliRunner;
use App\Services\Evolution\EvolutionApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendDailyPlansCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        config([
            'services.assistant.whatsapp.enabled' => true,
            'services.assistant.whatsapp.instance' => 'Assistente_Pejota',
            'services.planner.delivery_numbers' => ['5554999371490', '5581985573942'],
        ]);
    }

    public function test_sends_the_ready_plan_of_today(): void
    {
        DailyPlan::create([
            'company_id' => $this->user->company->id,
            'plan_date' => now()->toDateString(),
            'mode' => DailyPlanModeEnum::FULL,
            'status' => DailyPlanStatusEnum::READY,
            'summary' => 'Foco total.',
            'generated_at' => now(),
        ]);

        $evolution = Mockery::mock(EvolutionApiClient::class);
        $evolution->shouldReceive('sendTextToNumber')->twice();
        $this->instance(EvolutionApiClient::class, $evolution);

        $this->artisan('pj:daily-plan:send', ['--company' => $this->user->company->id])
            ->expectsOutputToContain('enviado pelo WhatsApp')
            ->assertSuccessful();
    }

    public function test_generates_inline_when_no_plan_exists_yet(): void
    {
        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->andReturn(json_encode(['summary' => 'Gerado na hora.', 'items' => []]));
        $this->instance(AiCliRunner::class, $runner);

        $evolution = Mockery::mock(EvolutionApiClient::class);
        $evolution->shouldReceive('sendTextToNumber')->twice();
        $this->instance(EvolutionApiClient::class, $evolution);

        $this->artisan('pj:daily-plan:send', ['--company' => $this->user->company->id])
            ->expectsOutputToContain('tentando gerar agora')
            ->assertSuccessful();

        $this->assertSame(
            DailyPlanStatusEnum::READY->value,
            DailyPlan::allTenants()->where('company_id', $this->user->company->id)->first()->status->value,
        );
    }

    public function test_skips_when_the_plan_was_already_sent(): void
    {
        DailyPlan::create([
            'company_id' => $this->user->company->id,
            'plan_date' => now()->toDateString(),
            'mode' => DailyPlanModeEnum::FULL,
            'status' => DailyPlanStatusEnum::READY,
            'generated_at' => now(),
            'sent_at' => now(),
        ]);

        $evolution = Mockery::mock(EvolutionApiClient::class);
        $evolution->shouldNotReceive('sendTextToNumber');
        $this->instance(EvolutionApiClient::class, $evolution);

        $this->artisan('pj:daily-plan:send', ['--company' => $this->user->company->id])
            ->expectsOutputToContain('envio pulado')
            ->assertSuccessful();
    }
}
