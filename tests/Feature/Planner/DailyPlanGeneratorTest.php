<?php

namespace Tests\Feature\Planner;

use App\Enums\CompanySettingsEnum;
use App\Enums\DailyPlanModeEnum;
use App\Enums\DailyPlanStatusEnum;
use App\Models\Client;
use App\Models\DailyPlan;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Ai\AiCliRunner;
use App\Services\Planner\DailyPlanGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DailyPlanGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $client = Client::create(['company_id' => $this->user->company->id, 'name' => 'Vivianne']);

        $status = Status::create([
            'name' => 'A Fazer', 'phase' => 'todo', 'color' => '#000', 'sort_order' => 1, 'active' => true,
            'company_id' => $this->user->company->id,
        ]);

        $this->task = Task::create([
            'title' => 'Entregar proposta',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'client_id' => $client->id,
            'due_date' => now()->toDateString(),
        ]);
    }

    private function mockRunner(string $response): void
    {
        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->withArgs(function (string $prompt): bool {
                return str_contains($prompt, 'Entregar proposta')
                    && str_contains($prompt, '<<<DADOS>>>')
                    && str_contains($prompt, 'JSON');
            })
            ->andReturn($response);
        $this->instance(AiCliRunner::class, $runner);
    }

    public function test_generates_a_ready_plan_with_ordered_items(): void
    {
        $this->mockRunner(json_encode([
            'summary' => 'Dia focado na proposta.',
            'items' => [
                ['type' => 'task', 'title' => 'Entregar proposta', 'estimated_minutes' => 120, 'reason' => 'vence hoje', 'task_id' => $this->task->id],
                ['type' => 'admin', 'title' => 'Organizar caixa de entrada', 'estimated_minutes' => 15],
            ],
            'warnings' => ['Aviso de teste'],
        ]));

        $plan = app(DailyPlanGenerator::class)->generate(
            $this->user->company,
            CarbonImmutable::now()->startOfDay(),
            DailyPlanModeEnum::FULL,
        );

        $this->assertSame(DailyPlanStatusEnum::READY, $plan->status);
        $this->assertSame('Dia focado na proposta.', $plan->summary);
        $this->assertSame(135, $plan->planned_minutes);
        $this->assertSame(['Aviso de teste'], $plan->warnings);
        $this->assertNotNull($plan->generated_at);

        $items = $plan->items;
        $this->assertCount(2, $items);
        $this->assertSame([1, 2], $items->pluck('position')->all());
        $this->assertSame($this->task->id, $items->first()->task_id);
    }

    public function test_regenerating_replaces_the_previous_items(): void
    {
        $this->mockRunner(json_encode([
            'summary' => 'v1',
            'items' => [
                ['type' => 'task', 'title' => 'Entregar proposta', 'estimated_minutes' => 60, 'task_id' => $this->task->id],
            ],
        ]));

        $date = CarbonImmutable::now()->startOfDay();
        $first = app(DailyPlanGenerator::class)->generate($this->user->company, $date, DailyPlanModeEnum::FULL);

        $this->mockRunner(json_encode([
            'summary' => 'v2',
            'items' => [
                ['type' => 'admin', 'title' => 'Revisar semana', 'estimated_minutes' => 30],
                ['type' => 'task', 'title' => 'Entregar proposta', 'estimated_minutes' => 90, 'task_id' => $this->task->id],
            ],
        ]));

        $second = app(DailyPlanGenerator::class)->generate($this->user->company, $date, DailyPlanModeEnum::FULL);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('v2', $second->summary);
        $this->assertCount(2, $second->items);
        $this->assertSame(1, DailyPlan::allTenants()->where('company_id', $this->user->company->id)->count());
    }

    public function test_cli_failure_marks_the_plan_as_failed(): void
    {
        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->andThrow(new RuntimeException('CLI indisponível'));
        $this->instance(AiCliRunner::class, $runner);

        $plan = app(DailyPlanGenerator::class)->generate(
            $this->user->company,
            CarbonImmutable::now()->startOfDay(),
            DailyPlanModeEnum::FULL,
        );

        $this->assertSame(DailyPlanStatusEnum::FAILED, $plan->status);
        $this->assertNotNull($plan->failure_reason);
        $this->assertCount(0, $plan->items);
    }

    public function test_planner_model_and_effort_overrides_reach_the_cli(): void
    {
        config([
            'services.planner.codex_model' => 'best-model',
            'services.planner.codex_reasoning_effort' => 'xhigh',
            'services.planner.timeout' => 456,
        ]);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->withArgs(function (string $prompt, array $images = [], array $options = []): bool {
                return ($options['codex_model'] ?? null) === 'best-model'
                    && ($options['codex_reasoning_effort'] ?? null) === 'xhigh'
                    && ($options['timeout'] ?? null) === 456;
            })
            ->andReturn(json_encode(['summary' => 'ok', 'items' => []]));
        $this->instance(AiCliRunner::class, $runner);

        $plan = app(DailyPlanGenerator::class)->generate(
            $this->user->company,
            CarbonImmutable::now()->startOfDay(),
            DailyPlanModeEnum::FULL,
        );

        $this->assertSame(DailyPlanStatusEnum::READY, $plan->status);
    }

    public function test_regeneration_budgets_only_the_remaining_time_of_the_day(): void
    {
        $this->user->company->settings()->set(
            CompanySettingsEnum::PLANNER_DAY_HOURS->value,
            [1 => 6, 2 => 6, 3 => 6, 4 => 6, 5 => 6, 6 => 6, 7 => 6],
        );

        $today = CarbonImmutable::now()->startOfDay();

        WorkSession::create([
            'company_id' => $this->user->company->id,
            'start' => $today->setTime(8, 0),
            'end' => $today->setTime(13, 0),
            'is_running' => false,
        ]);

        $capturedPrompt = null;
        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->andReturnUsing(function (string $prompt) use (&$capturedPrompt): string {
                $capturedPrompt = $prompt;

                return json_encode(['summary' => 'ok', 'items' => []]);
            });
        $this->instance(AiCliRunner::class, $runner);

        $plan = app(DailyPlanGenerator::class)->generate($this->user->company, $today, DailyPlanModeEnum::FULL);

        // Day capacity 6h, 5h already worked today, so only 1h is planned.
        $this->assertSame(60, $plan->capacity_minutes);
        $this->assertStringContainsString('01h00', (string) $capturedPrompt);
        $this->assertStringContainsString('Tempo restante hoje', (string) $capturedPrompt);
    }

    public function test_extra_time_override_budgets_the_requested_time_and_forces_a_full_plan(): void
    {
        $this->user->company->settings()->set(
            CompanySettingsEnum::PLANNER_DAY_HOURS->value,
            [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 4, 7 => 4],
        );

        $today = CarbonImmutable::now()->startOfDay();

        // Already worked past the day's hours: a normal regen would go light.
        WorkSession::create([
            'company_id' => $this->user->company->id,
            'start' => $today->setTime(8, 0),
            'end' => $today->setTime(13, 0),
            'is_running' => false,
        ]);

        $capturedPrompt = null;
        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->andReturnUsing(function (string $prompt) use (&$capturedPrompt): string {
                $capturedPrompt = $prompt;

                return json_encode(['summary' => 'ok', 'items' => []]);
            });
        $this->instance(AiCliRunner::class, $runner);

        $plan = app(DailyPlanGenerator::class)->generate(
            $this->user->company,
            $today,
            DailyPlanModeEnum::FULL,
            120,
        );

        $this->assertSame(DailyPlanModeEnum::FULL, $plan->mode);
        $this->assertSame(120, $plan->capacity_minutes);
        $this->assertStringContainsString('02h00', (string) $capturedPrompt);
        $this->assertStringContainsString('EXTRA', (string) $capturedPrompt);
    }

    public function test_full_plan_falls_back_to_light_when_no_time_is_left_today(): void
    {
        $this->user->company->settings()->set(
            CompanySettingsEnum::PLANNER_DAY_HOURS->value,
            [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 4, 7 => 4],
        );

        $today = CarbonImmutable::now()->startOfDay();

        WorkSession::create([
            'company_id' => $this->user->company->id,
            'start' => $today->setTime(8, 0),
            'end' => $today->setTime(13, 0),
            'is_running' => false,
        ]);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn(json_encode(['summary' => 'ok', 'items' => []]));
        $this->instance(AiCliRunner::class, $runner);

        $plan = app(DailyPlanGenerator::class)->generate($this->user->company, $today, DailyPlanModeEnum::FULL);

        $this->assertSame(DailyPlanModeEnum::LIGHT, $plan->mode);
        $this->assertSame(0, $plan->capacity_minutes);
    }

    public function test_effort_falls_back_to_high_when_the_first_attempt_fails(): void
    {
        config(['services.planner.codex_reasoning_effort' => 'xhigh']);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->withArgs(fn (string $prompt, array $images = [], array $options = []): bool => ($options['codex_reasoning_effort'] ?? null) === 'xhigh')
            ->andThrow(new RuntimeException('effort inválido'));
        $runner->shouldReceive('complete')
            ->once()
            ->withArgs(fn (string $prompt, array $images = [], array $options = []): bool => ($options['codex_reasoning_effort'] ?? null) === 'high')
            ->andReturn(json_encode(['summary' => 'ok', 'items' => []]));
        $this->instance(AiCliRunner::class, $runner);

        $plan = app(DailyPlanGenerator::class)->generate(
            $this->user->company,
            CarbonImmutable::now()->startOfDay(),
            DailyPlanModeEnum::FULL,
        );

        $this->assertSame(DailyPlanStatusEnum::READY, $plan->status);
    }
}
