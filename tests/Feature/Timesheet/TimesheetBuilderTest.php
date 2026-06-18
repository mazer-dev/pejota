<?php

namespace Tests\Feature\Timesheet;

use App\Enums\TimesheetDetailLevel;
use App\Enums\TimesheetGrouping;
use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\Project;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Timesheet\TimesheetBuilder;
use App\Services\Timesheet\TimesheetRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class TimesheetBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Landlord::addTenant('company_id', $this->user->company->id);
        $this->client = Client::create([
            'name' => 'Acme',
            'company_id' => $this->user->company->id,
            'currency' => 'BRL',
            'default_hourly_rate' => 100.00,
            'billable_default' => true,
        ]);
    }

    private function makeSession(array $attrs): WorkSession
    {
        return WorkSession::create(array_merge([
            'title' => 'Work',
            'company_id' => $this->user->company->id,
            'client_id' => $this->client->id,
            'is_running' => false,
            'rate' => 100.00,
        ], $attrs));
    }

    private function makeStatus(): Status
    {
        return Status::create([
            'name' => 'To Do', 'phase' => 'todo', 'color' => '#000000',
            'sort_order' => 1, 'active' => true, 'company_id' => $this->user->company->id,
        ]);
    }

    private function request(array $overrides = []): TimesheetRequest
    {
        $clientId = $overrides['clientId'] ?? $this->client->id;

        return new TimesheetRequest(
            clientId: $clientId,
            from: $overrides['from'] ?? CarbonImmutable::parse('2026-06-01 00:00:00'),
            to: $overrides['to'] ?? CarbonImmutable::parse('2026-06-30 23:59:59'),
            timezone: $overrides['timezone'] ?? 'UTC',
            currency: $overrides['currency'] ?? (Client::find($clientId)?->currency ?? PejotaHelper::getUserCurrency()),
            grouping: $overrides['grouping'] ?? TimesheetGrouping::None,
            detailLevel: $overrides['detailLevel'] ?? TimesheetDetailLevel::Detailed,
            includeValue: $overrides['includeValue'] ?? true,
            billableOnly: $overrides['billableOnly'] ?? false,
            layoutKey: $overrides['layoutKey'] ?? 'client',
        );
    }

    public function test_totals_sum_minutes_and_value(): void
    {
        $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 11:00:00']);
        $this->makeSession(['start' => '2026-06-11 09:00:00', 'end' => '2026-06-11 10:00:00']);

        $data = (new TimesheetBuilder)->build($this->request());

        $this->assertSame(180, $data->grandTotalMinutes);
        $this->assertEquals(300.00, $data->grandTotalValue);
        $this->assertSame('BRL', $data->currency);
    }

    public function test_group_by_project(): void
    {
        $p1 = Project::create(['name' => 'Alpha', 'company_id' => $this->user->company->id, 'client_id' => $this->client->id]);
        $p2 = Project::create(['name' => 'Beta', 'company_id' => $this->user->company->id, 'client_id' => $this->client->id]);
        $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00', 'project_id' => $p1->id]);
        $this->makeSession(['start' => '2026-06-10 11:00:00', 'end' => '2026-06-10 12:00:00', 'project_id' => $p2->id]);

        $data = (new TimesheetBuilder)->build($this->request(['grouping' => TimesheetGrouping::Project]));

        $this->assertCount(2, $data->groups);
        $labels = $data->groups->pluck('label')->all();
        $this->assertContains('Alpha', $labels);
        $this->assertContains('Beta', $labels);
    }

    public function test_group_by_day(): void
    {
        $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00']);
        $this->makeSession(['start' => '2026-06-11 09:00:00', 'end' => '2026-06-11 10:00:00']);

        $data = (new TimesheetBuilder)->build($this->request(['grouping' => TimesheetGrouping::Day]));

        $this->assertCount(2, $data->groups);
        $this->assertSame('2026-06-10', $data->groups->first()->label);
    }

    public function test_detailed_has_one_entry_per_session_summary_has_none(): void
    {
        $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00']);
        $this->makeSession(['start' => '2026-06-10 11:00:00', 'end' => '2026-06-10 12:00:00']);

        $detailed = (new TimesheetBuilder)->build($this->request(['detailLevel' => TimesheetDetailLevel::Detailed]));
        $this->assertCount(2, $detailed->groups->first()->entries);

        $summary = (new TimesheetBuilder)->build($this->request(['detailLevel' => TimesheetDetailLevel::GroupSummary]));
        $this->assertCount(0, $summary->groups->first()->entries);
        $this->assertSame(120, $summary->groups->first()->subtotalMinutes);
    }

    public function test_parent_task_rollup_attributes_subtask_sessions_to_root(): void
    {
        $status = $this->makeStatus();
        $root = Task::create(['title' => 'Root', 'company_id' => $this->user->company->id, 'client_id' => $this->client->id, 'status_id' => $status->id]);
        $child = Task::create(['title' => 'Child', 'company_id' => $this->user->company->id, 'client_id' => $this->client->id, 'status_id' => $status->id, 'parent_id' => $root->id]);
        $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00', 'task_id' => $root->id]);
        $this->makeSession(['start' => '2026-06-10 11:00:00', 'end' => '2026-06-10 12:00:00', 'task_id' => $child->id]);

        $data = (new TimesheetBuilder)->build($this->request([
            'grouping' => TimesheetGrouping::None,
            'detailLevel' => TimesheetDetailLevel::ParentTaskRollup,
        ]));

        $entries = $data->groups->first()->entries;
        $this->assertCount(1, $entries);
        $this->assertSame('Root', $entries->first()->taskTitle);
        $this->assertSame(120, $entries->first()->minutes);
        $this->assertNull($entries->first()->rate);
    }

    public function test_billable_only_excludes_non_billable(): void
    {
        $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00', 'billable' => true]);
        $this->makeSession(['start' => '2026-06-10 11:00:00', 'end' => '2026-06-10 12:00:00', 'billable' => false]);

        $data = (new TimesheetBuilder)->build($this->request(['billableOnly' => true]));

        $this->assertSame(60, $data->grandTotalMinutes);
    }

    public function test_empty_range_produces_empty_data(): void
    {
        $data = (new TimesheetBuilder)->build($this->request([
            'from' => CarbonImmutable::parse('2030-01-01 00:00:00'),
            'to' => CarbonImmutable::parse('2030-01-31 23:59:59'),
        ]));

        $this->assertCount(0, $data->groups);
        $this->assertSame(0, $data->grandTotalMinutes);
        $this->assertEquals(0.0, $data->grandTotalValue);
    }

    public function test_currency_falls_back_to_base_when_client_has_none(): void
    {
        $noCurrency = Client::create(['name' => 'NoCur', 'company_id' => $this->user->company->id]);
        $this->makeSession(['client_id' => $noCurrency->id, 'start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00']);

        $data = (new TimesheetBuilder)->build($this->request(['clientId' => $noCurrency->id]));

        $this->assertSame('USD', $data->currency);
    }

    public function test_group_by_task(): void
    {
        $status = $this->makeStatus();
        $t1 = Task::create(['title' => 'Task One', 'company_id' => $this->user->company->id, 'client_id' => $this->client->id, 'status_id' => $status->id]);
        $t2 = Task::create(['title' => 'Task Two', 'company_id' => $this->user->company->id, 'client_id' => $this->client->id, 'status_id' => $status->id]);
        $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00', 'task_id' => $t1->id]);
        $this->makeSession(['start' => '2026-06-10 11:00:00', 'end' => '2026-06-10 12:00:00', 'task_id' => $t2->id]);

        $data = (new TimesheetBuilder)->build($this->request(['grouping' => TimesheetGrouping::Task]));

        $labels = $data->groups->pluck('label')->all();
        $this->assertContains('Task One', $labels);
        $this->assertContains('Task Two', $labels);
    }

    public function test_group_by_month(): void
    {
        $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00']);
        $this->makeSession(['start' => '2026-07-10 09:00:00', 'end' => '2026-07-10 10:00:00']);

        $data = (new TimesheetBuilder)->build($this->request([
            'from' => CarbonImmutable::parse('2026-06-01 00:00:00'),
            'to' => CarbonImmutable::parse('2026-07-31 23:59:59'),
            'grouping' => TimesheetGrouping::Month,
        ]));

        $labels = $data->groups->pluck('label')->all();
        $this->assertContains('2026-06', $labels);
        $this->assertContains('2026-07', $labels);
    }

    public function test_group_by_week_buckets_by_start_of_week(): void
    {
        // 2026-06-10 (Wed) and 2026-06-17 (Wed) are in different ISO weeks.
        $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00']);
        $this->makeSession(['start' => '2026-06-17 09:00:00', 'end' => '2026-06-17 10:00:00']);

        $data = (new TimesheetBuilder)->build($this->request(['grouping' => TimesheetGrouping::Week]));

        $this->assertCount(2, $data->groups, 'two distinct weeks produce two groups');
    }

    public function test_totals_are_computed_even_when_value_is_excluded(): void
    {
        $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 11:00:00']);

        $data = (new TimesheetBuilder)->build($this->request(['includeValue' => false]));

        $this->assertSame(120, $data->grandTotalMinutes);
        $this->assertEquals(200.00, $data->grandTotalValue, 'builder always computes value; suppression is a layout concern');
        $this->assertFalse($data->includeValue);
    }
}
