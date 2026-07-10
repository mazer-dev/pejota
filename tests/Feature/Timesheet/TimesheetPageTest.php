<?php

namespace Tests\Feature\Timesheet;

use App\Filament\App\Pages\Timesheet;
use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class TimesheetPageTest extends TestCase
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

    public function test_page_loads(): void
    {
        $this->get(Timesheet::getUrl())->assertOk();
    }

    private function makeClientWithSession(): Client
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->company->id, 'currency' => 'BRL']);
        WorkSession::create([
            'title' => 'Work', 'company_id' => $this->company->id, 'client_id' => $client->id,
            'is_running' => false, 'rate' => 100.00, 'start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 11:00:00',
        ]);

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Client $client, bool $includeValue = true): array
    {
        return [
            'client_id' => $client->id,
            'from' => '2026-06-01',
            'to' => '2026-06-30',
            'grouping' => 'none',
            'detailLevel' => 'detailed',
            'includeValue' => $includeValue,
            'billableOnly' => false,
            'layoutKey' => 'client',
        ];
    }

    public function test_preview_builds_data_for_selected_client(): void
    {
        $client = $this->makeClientWithSession();

        Livewire::test(Timesheet::class)
            ->fillForm($this->formData($client))
            ->call('preview')
            ->assertHasNoFormErrors()
            ->assertSet('hasPreview', true);
    }

    public function test_preview_shows_subtotals_and_total_for_known_dataset(): void
    {
        $client = $this->makeClientWithSession();

        Livewire::test(Timesheet::class)
            ->fillForm($this->formData($client))
            ->call('preview')
            ->assertSee('Work')                                 // entry description
            ->assertSee('200.00')                               // value / subtotal / grand total
            ->assertSeeHtml(PejotaHelper::formatDuration(120));  // 2h total for the known session
    }

    public function test_preview_hides_value_when_include_value_false(): void
    {
        $client = $this->makeClientWithSession();

        Livewire::test(Timesheet::class)
            ->fillForm($this->formData($client, includeValue: false))
            ->call('preview')
            ->assertSet('hasPreview', true)
            ->assertDontSee('200.00');
    }

    public function test_preview_does_not_crash_when_form_becomes_invalid_after_preview(): void
    {
        $client = $this->makeClientWithSession();

        Livewire::test(Timesheet::class)
            ->fillForm($this->formData($client))
            ->call('preview')
            ->assertSet('hasPreview', true)
            ->set('data.client_id', null)
            ->call('$refresh')
            ->assertOk();
    }

    public function test_export_csv_action_streams_without_errors(): void
    {
        $client = $this->makeClientWithSession();

        Livewire::test(Timesheet::class)
            ->fillForm($this->formData($client))
            ->callAction('exportCsv')
            ->assertHasNoActionErrors();
    }
}
