<?php

namespace Tests\Feature\Planner;

use App\Enums\DailyPlanItemStatusEnum;
use App\Enums\DailyPlanItemTypeEnum;
use App\Enums\DailyPlanModeEnum;
use App\Enums\DailyPlanStatusEnum;
use App\Filament\App\Pages\PlanOfTheDay;
use App\Jobs\GenerateDailyPlan;
use App\Models\DailyPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class PlanOfTheDayPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeReadyPlan(?int $companyId = null): DailyPlan
    {
        $companyId ??= $this->user->company->id;

        $plan = DailyPlan::allTenants()->create([
            'company_id' => $companyId,
            'plan_date' => now()->toDateString(),
            'mode' => DailyPlanModeEnum::FULL,
            'status' => DailyPlanStatusEnum::READY,
            'capacity_minutes' => 480,
            'planned_minutes' => 60,
            'summary' => 'Foco na proposta da Vivianne.',
            'generated_at' => now(),
        ]);

        $plan->items()->create([
            'company_id' => $companyId,
            'position' => 1,
            'type' => DailyPlanItemTypeEnum::ADMIN,
            'title' => 'Revisar caixa de entrada',
            'estimated_minutes' => 60,
        ]);

        return $plan;
    }

    public function test_renders_the_ready_plan_with_its_items(): void
    {
        $this->makeReadyPlan();

        Livewire::test(PlanOfTheDay::class)
            ->assertSee('Foco na proposta da Vivianne.')
            ->assertSee('Revisar caixa de entrada')
            ->assertStatus(200);
    }

    public function test_marking_an_item_done_sets_status_and_done_at(): void
    {
        $plan = $this->makeReadyPlan();
        $item = $plan->items()->first();

        Livewire::test(PlanOfTheDay::class)
            ->call('markItemDone', $item->id);

        $item->refresh();
        $this->assertSame(DailyPlanItemStatusEnum::DONE, $item->status);
        $this->assertNotNull($item->done_at);
    }

    public function test_skipping_and_reopening_an_item(): void
    {
        $plan = $this->makeReadyPlan();
        $item = $plan->items()->first();

        Livewire::test(PlanOfTheDay::class)->call('skipItem', $item->id);
        $this->assertSame(DailyPlanItemStatusEnum::SKIPPED, $item->refresh()->status);

        Livewire::test(PlanOfTheDay::class)->call('reopenItem', $item->id);
        $this->assertSame(DailyPlanItemStatusEnum::PENDING, $item->refresh()->status);
    }

    public function test_generate_plan_dispatches_the_job_and_marks_generating(): void
    {
        Bus::fake();

        Livewire::test(PlanOfTheDay::class)
            ->call('generatePlan')
            ->assertNotified();

        Bus::assertDispatched(GenerateDailyPlan::class, function (GenerateDailyPlan $job): bool {
            return $job->company->id === $this->user->company->id;
        });

        $plan = DailyPlan::allTenants()->where('company_id', $this->user->company->id)->first();
        $this->assertSame(DailyPlanStatusEnum::GENERATING, $plan->status);
    }

    public function test_does_not_show_or_touch_plans_from_another_company(): void
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);
        $otherPlan = $this->makeReadyPlan($otherUser->company->id);
        $otherItem = $otherPlan->items()->first();

        $this->actingAs($this->user);

        Livewire::test(PlanOfTheDay::class)
            ->assertDontSee('Foco na proposta da Vivianne.')
            ->call('markItemDone', $otherItem->id);

        $this->assertSame(DailyPlanItemStatusEnum::PENDING, $otherItem->refresh()->status);
    }

    public function test_failed_plan_shows_the_failure_reason(): void
    {
        DailyPlan::allTenants()->create([
            'company_id' => $this->user->company->id,
            'plan_date' => now()->toDateString(),
            'mode' => DailyPlanModeEnum::FULL,
            'status' => DailyPlanStatusEnum::FAILED,
            'failure_reason' => 'CLI indisponível',
        ]);

        Livewire::test(PlanOfTheDay::class)
            ->assertSee('CLI indisponível');
    }
}
