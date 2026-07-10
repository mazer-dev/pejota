<?php

namespace Tests\Feature\Invoicing;

use App\Enums\TimesheetGrouping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Timesheet\SessionGroupKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class SessionGroupKeyTest extends TestCase
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

    private function makeSession(array $attrs): WorkSession
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->company->id, 'currency' => 'BRL']);

        return WorkSession::create(array_merge([
            'title' => 'Work', 'company_id' => $this->company->id, 'client_id' => $client->id,
            'is_running' => false, 'rate' => 100.00,
        ], $attrs));
    }

    public function test_day_key_uses_local_timezone(): void
    {
        // 02:30 UTC is 23:30 the PREVIOUS day in São Paulo (UTC−3): the bucket must shift back a day.
        $session = $this->makeSession(['start' => '2026-06-10 02:30:00', 'end' => '2026-06-10 03:30:00']);

        $this->assertSame('2026-06-09', SessionGroupKey::for($session, TimesheetGrouping::Day, 'America/Sao_Paulo'));
        $this->assertSame('2026-06-10', SessionGroupKey::for($session, TimesheetGrouping::Day, 'UTC'));
    }

    public function test_project_key_falls_back_when_absent(): void
    {
        $session = $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00']);

        $this->assertSame(__('No project'), SessionGroupKey::for($session, TimesheetGrouping::Project, 'UTC'));
    }

    public function test_project_key_uses_project_name(): void
    {
        $project = Project::create(['name' => 'Alpha', 'company_id' => $this->company->id]);
        $session = $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00', 'project_id' => $project->id]);
        $session->load('project');

        $this->assertSame('Alpha', SessionGroupKey::for($session, TimesheetGrouping::Project, 'UTC'));
    }

    public function test_none_key_is_total(): void
    {
        $session = $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00']);

        $this->assertSame(__('Total'), SessionGroupKey::for($session, TimesheetGrouping::None, 'UTC'));
    }
}
