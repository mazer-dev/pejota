<?php

namespace Tests\Feature\Timesheet;

use App\Enums\TimesheetDetailLevel;
use App\Enums\TimesheetGrouping;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Timesheet\Layouts\ClientTimesheetLayout;
use App\Services\Timesheet\Layouts\InternalTimesheetLayout;
use App\Services\Timesheet\Renderers\CsvTimesheetRenderer;
use App\Services\Timesheet\TimesheetBuilder;
use App\Services\Timesheet\TimesheetRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class TimesheetCsvRendererTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private function streamContent(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    private function makeRequest(Client $client, bool $includeValue, TimesheetDetailLevel $detail): TimesheetRequest
    {
        return new TimesheetRequest(
            clientId: $client->id,
            from: CarbonImmutable::parse('2026-06-01 00:00:00'),
            to: CarbonImmutable::parse('2026-06-30 23:59:59'),
            timezone: 'UTC',
            currency: $client->currency ?? 'USD',
            grouping: TimesheetGrouping::None,
            detailLevel: $detail,
            includeValue: $includeValue,
            billableOnly: false,
            layoutKey: 'client',
        );
    }

    public function test_detailed_csv_emits_a_subtotal_row_after_entries(): void
    {
        $user = User::factory()->create();
        $company = $this->actingInCompany($user);
        $client = Client::create(['name' => 'Acme', 'company_id' => $company->id, 'currency' => 'BRL']);
        WorkSession::create([
            'title' => 'E1', 'company_id' => $company->id, 'client_id' => $client->id,
            'is_running' => false, 'rate' => 100.00, 'start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00',
        ]);
        WorkSession::create([
            'title' => 'E2', 'company_id' => $company->id, 'client_id' => $client->id,
            'is_running' => false, 'rate' => 100.00, 'start' => '2026-06-10 11:00:00', 'end' => '2026-06-10 12:00:00',
        ]);

        $request = $this->makeRequest($client, includeValue: true, detail: TimesheetDetailLevel::Detailed);
        $data = (new TimesheetBuilder)->build($request);
        $csv = $this->streamContent((new CsvTimesheetRenderer)->render($data, new ClientTimesheetLayout, $request));

        // Two entry rows + a subtotal row ('Total' label, group sum 200.00) + grand total row.
        $this->assertStringContainsString('E1', $csv);
        $this->assertStringContainsString('E2', $csv);
        $this->assertSame(2, substr_count($csv, '200.00'), 'one group subtotal + one grand total = two 200.00 lines');
    }

    public function test_csv_has_header_and_one_row_per_entry_with_value(): void
    {
        $user = User::factory()->create();
        $company = $this->actingInCompany($user);
        $client = Client::create(['name' => 'Acme', 'company_id' => $company->id, 'currency' => 'BRL']);
        WorkSession::create([
            'title' => 'Did work', 'company_id' => $company->id, 'client_id' => $client->id,
            'is_running' => false, 'rate' => 100.00, 'start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00',
        ]);

        $request = $this->makeRequest($client, includeValue: true, detail: TimesheetDetailLevel::Detailed);
        $data = (new TimesheetBuilder)->build($request);
        $csv = $this->streamContent((new CsvTimesheetRenderer)->render($data, new ClientTimesheetLayout, $request));

        $this->assertStringContainsString('Value', $csv);   // money column present
        $this->assertStringContainsString('Did work', $csv); // entry row
        $this->assertStringContainsString('100.00', $csv);    // value
    }

    public function test_csv_omits_value_column_when_include_value_false(): void
    {
        $user = User::factory()->create();
        $company = $this->actingInCompany($user);
        $client = Client::create(['name' => 'Acme', 'company_id' => $company->id, 'currency' => 'BRL']);
        WorkSession::create([
            'title' => 'Did work', 'company_id' => $company->id, 'client_id' => $client->id,
            'is_running' => false, 'rate' => 100.00, 'start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00',
        ]);

        $request = $this->makeRequest($client, includeValue: false, detail: TimesheetDetailLevel::Detailed);
        $data = (new TimesheetBuilder)->build($request);
        $csv = $this->streamContent((new CsvTimesheetRenderer)->render($data, new ClientTimesheetLayout, $request));

        $this->assertStringNotContainsString('Value', $csv);
    }

    public function test_group_summary_emits_one_row_per_group(): void
    {
        $user = User::factory()->create();
        $company = $this->actingInCompany($user);
        $client = Client::create(['name' => 'Acme', 'company_id' => $company->id, 'currency' => 'BRL']);
        WorkSession::create([
            'title' => 'A', 'company_id' => $company->id, 'client_id' => $client->id,
            'is_running' => false, 'rate' => 100.00, 'start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 11:00:00',
        ]);

        $request = $this->makeRequest($client, includeValue: true, detail: TimesheetDetailLevel::GroupSummary);
        $data = (new TimesheetBuilder)->build($request);
        $csv = $this->streamContent((new CsvTimesheetRenderer)->render($data, new ClientTimesheetLayout, $request));

        // One data row (the group subtotal) labelled 'Total', plus the grand-total line.
        $this->assertStringContainsString('Total', $csv);
        $this->assertStringContainsString('200.00', $csv);
    }

    public function test_internal_layout_grouped_by_project_emits_subtotal_per_group(): void
    {
        $user = User::factory()->create();
        $company = $this->actingInCompany($user);
        $client = Client::create(['name' => 'Acme', 'company_id' => $company->id, 'currency' => 'BRL']);
        $alpha = Project::create(['name' => 'Alpha', 'company_id' => $company->id, 'client_id' => $client->id]);
        $beta = Project::create(['name' => 'Beta', 'company_id' => $company->id, 'client_id' => $client->id]);
        WorkSession::create([
            'title' => 'WorkA', 'company_id' => $company->id, 'client_id' => $client->id, 'project_id' => $alpha->id,
            'is_running' => false, 'rate' => 100.00, 'start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00',
        ]);
        WorkSession::create([
            'title' => 'WorkB', 'company_id' => $company->id, 'client_id' => $client->id, 'project_id' => $beta->id,
            'is_running' => false, 'rate' => 100.00, 'start' => '2026-06-11 09:00:00', 'end' => '2026-06-11 11:00:00',
        ]);

        $request = new TimesheetRequest(
            clientId: $client->id,
            from: CarbonImmutable::parse('2026-06-01 00:00:00'),
            to: CarbonImmutable::parse('2026-06-30 23:59:59'),
            timezone: 'UTC',
            currency: 'BRL',
            grouping: TimesheetGrouping::Project,
            detailLevel: TimesheetDetailLevel::Detailed,
            includeValue: true,
            billableOnly: false,
            layoutKey: 'internal',
        );
        $data = (new TimesheetBuilder)->build($request);
        $csv = $this->streamContent((new CsvTimesheetRenderer)->render($data, new InternalTimesheetLayout, $request));

        // Internal layout exposes Project and Task columns in the header.
        $this->assertStringContainsString('Project', $csv);
        $this->assertStringContainsString('Task', $csv);
        // The project name populates the entry row's Project column.
        $this->assertStringContainsString('Alpha', $csv);
        $this->assertStringContainsString('Beta', $csv);
        // Two groups → two per-group subtotal rows; Alpha 100.00, Beta 200.00, grand total 300.00.
        $this->assertStringContainsString('100.00', $csv);
        $this->assertSame(2, substr_count($csv, '200.00'), 'Beta subtotal + nothing else at 200.00');
        $this->assertStringContainsString('300.00', $csv); // grand total
    }
}
