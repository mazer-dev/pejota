<?php

namespace Tests\Feature\Timesheet;

use App\Enums\TimesheetDetailLevel;
use App\Enums\TimesheetGrouping;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Timesheet\Layouts\ClientTimesheetLayout;
use App\Services\Timesheet\Renderers\PdfTimesheetRenderer;
use App\Services\Timesheet\TimesheetBuilder;
use App\Services\Timesheet\TimesheetRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimesheetPdfRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_renders_and_contains_client_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $client = Client::create(['name' => 'Acme Corp', 'company_id' => $user->company->id, 'currency' => 'BRL']);
        WorkSession::create([
            'title' => 'Did work', 'company_id' => $user->company->id, 'client_id' => $client->id,
            'is_running' => false, 'rate' => 100.00, 'start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00',
        ]);

        $request = new TimesheetRequest(
            clientId: $client->id,
            from: CarbonImmutable::parse('2026-06-01 00:00:00'),
            to: CarbonImmutable::parse('2026-06-30 23:59:59'),
            timezone: 'UTC',
            currency: 'BRL',
            grouping: TimesheetGrouping::None,
            detailLevel: TimesheetDetailLevel::Detailed,
            includeValue: true,
            billableOnly: false,
            layoutKey: 'client',
        );
        $data = (new TimesheetBuilder)->build($request);

        $pdf = (new PdfTimesheetRenderer)->make($data, new ClientTimesheetLayout, $request);
        $output = $pdf->output();

        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF', $output);
    }

    public function test_pdf_view_renders_client_header_groups_subtotal_and_total(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $client = Client::create(['name' => 'Acme Corp', 'company_id' => $user->company->id, 'currency' => 'BRL']);
        WorkSession::create([
            'title' => 'Did work', 'company_id' => $user->company->id, 'client_id' => $client->id,
            'is_running' => false, 'rate' => 100.00, 'start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00',
        ]);

        $request = $this->makeRequest($client, includeValue: true);
        $data = (new TimesheetBuilder)->build($request);

        $html = view('timesheet.pdf', [
            'data' => $data,
            'layout' => new ClientTimesheetLayout,
            'request' => $request,
        ])->render();

        $this->assertStringContainsString('Acme Corp', $html);   // client header
        $this->assertStringContainsString('Did work', $html);    // entry description
        $this->assertStringContainsString('Subtotal', $html);    // per-group subtotal row
        $this->assertStringContainsString('Total', $html);       // grand-total row
        $this->assertStringContainsString('Value', $html);       // value column header present
    }

    public function test_pdf_view_omits_value_columns_when_include_value_false(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $client = Client::create(['name' => 'Acme Corp', 'company_id' => $user->company->id, 'currency' => 'BRL']);
        WorkSession::create([
            'title' => 'Did work', 'company_id' => $user->company->id, 'client_id' => $client->id,
            'is_running' => false, 'rate' => 100.00, 'start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00',
        ]);

        $request = $this->makeRequest($client, includeValue: false);
        $data = (new TimesheetBuilder)->build($request);

        $html = view('timesheet.pdf', [
            'data' => $data,
            'layout' => new ClientTimesheetLayout,
            'request' => $request,
        ])->render();

        $this->assertStringContainsString('Did work', $html);
        $this->assertStringNotContainsString('>Value<', $html);
        $this->assertStringNotContainsString('>Rate<', $html);
    }

    public function test_pdf_view_renders_empty_notice_for_empty_period(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $client = Client::create(['name' => 'Acme Corp', 'company_id' => $user->company->id, 'currency' => 'BRL']);

        $request = $this->makeRequest($client, includeValue: true);
        $data = (new TimesheetBuilder)->build($request);

        $html = view('timesheet.pdf', [
            'data' => $data,
            'layout' => new ClientTimesheetLayout,
            'request' => $request,
        ])->render();

        $this->assertStringContainsString('No entries for this period.', $html);
    }

    private function makeRequest(Client $client, bool $includeValue): TimesheetRequest
    {
        return new TimesheetRequest(
            clientId: $client->id,
            from: CarbonImmutable::parse('2026-06-01 00:00:00'),
            to: CarbonImmutable::parse('2026-06-30 23:59:59'),
            timezone: 'UTC',
            currency: $client->currency ?? 'USD',
            grouping: TimesheetGrouping::None,
            detailLevel: TimesheetDetailLevel::Detailed,
            includeValue: $includeValue,
            billableOnly: false,
            layoutKey: 'client',
        );
    }
}
