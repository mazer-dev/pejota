<?php

namespace Tests\Feature\Timesheet;

use App\Filament\App\Resources\WorkSessionResource\Pages\ListWorkSessions;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class TimesheetActionTest extends TestCase
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

    private function clientWithSession(): Client
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->company->id, 'currency' => 'BRL']);
        WorkSession::create([
            'title' => 'Work', 'company_id' => $this->company->id, 'client_id' => $client->id,
            'is_running' => false, 'rate' => 100.00, 'start' => now()->subDays(2), 'end' => now()->subDays(2)->addHour(),
        ]);

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    private function actionData(?int $clientId, string $format = 'csv'): array
    {
        return [
            'client_id' => $clientId,
            'from' => now()->subMonth()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
            'grouping' => 'none',
            'detailLevel' => 'detailed',
            'layoutKey' => 'client',
            'format' => $format,
            'includeValue' => true,
            'billableOnly' => false,
        ];
    }

    public function test_generate_timesheet_action_streams_csv_for_a_client(): void
    {
        $client = $this->clientWithSession();

        Livewire::test(ListWorkSessions::class)
            ->callAction('generateTimesheet', data: $this->actionData($client->id, 'csv'))
            ->assertHasNoActionErrors();
    }

    public function test_generate_timesheet_action_streams_pdf_for_a_client(): void
    {
        $client = $this->clientWithSession();

        Livewire::test(ListWorkSessions::class)
            ->callAction('generateTimesheet', data: $this->actionData($client->id, 'pdf'))
            ->assertHasNoActionErrors();
    }

    public function test_generate_timesheet_action_requires_a_client(): void
    {
        Livewire::test(ListWorkSessions::class)
            ->callAction('generateTimesheet', data: $this->actionData(null))
            ->assertHasActionErrors(['client_id' => ['required']]);
    }

    public function test_generate_timesheet_action_prefills_client_from_table_filter(): void
    {
        $client = $this->clientWithSession();

        Livewire::test(ListWorkSessions::class)
            ->set('tableFilters.client.value', $client->id)
            ->mountAction('generateTimesheet')
            ->assertActionDataSet(['client_id' => $client->id]);
    }
}
