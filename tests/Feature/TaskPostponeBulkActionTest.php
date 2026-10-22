<?php

namespace Tests\Feature;

use App\Filament\App\Resources\TaskResource\Pages\ListTasks;
use App\Helpers\PejotaHelper;
use App\Models\Company;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class TaskPostponeBulkActionTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = $this->actingInCompany(User::factory()->create());

        Carbon::setTestNow('2026-10-22 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeTask(): Task
    {
        $status = Status::create([
            'name' => 'To do',
            'phase' => 'todo',
            'color' => '#000000',
            'sort_order' => 1,
            'active' => true,
            'company_id' => $this->company->id,
        ]);

        return Task::create([
            'title' => 'Postponable',
            'status_id' => $status->id,
            'company_id' => $this->company->id,
            'planned_end' => '2026-10-05',
            'due_date' => '2026-10-01',
        ]);
    }

    public function test_field_and_interval_are_select_boxes(): void
    {
        Livewire::test(ListTasks::class)
            ->mountTableBulkAction('postpone', [$this->makeTask()])
            ->assertFormFieldExists('field', fn (Field $field): bool => $field instanceof Select)
            ->assertFormFieldExists('interval', fn (Field $field): bool => $field instanceof Select && ! $field->canSelectPlaceholder());
    }

    public function test_field_has_no_default_and_interval_defaults_to_today(): void
    {
        Livewire::test(ListTasks::class)
            ->mountTableBulkAction('postpone', [$this->makeTask()])
            ->assertTableBulkActionDataSet(['field' => null, 'interval' => 'today']);
    }

    public function test_field_must_be_chosen_explicitly(): void
    {
        $task = $this->makeTask();

        Livewire::test(ListTasks::class)
            ->callTableBulkAction('postpone', [$task], ['interval' => 'today'])
            ->assertHasActionErrors(['field' => 'required']);

        $this->assertSame('2026-10-01', $task->refresh()->due_date->toDateString());
    }

    public function test_resulting_date_shows_the_weekday_in_the_user_locale(): void
    {
        app()->setLocale('pt_BR');

        Livewire::test(ListTasks::class)
            ->mountTableBulkAction('postpone', [$this->makeTask()])
            ->fillForm(['interval' => '1 day'])
            ->assertSchemaComponentStateSet(
                'resulting_date',
                'sexta-feira, '.Carbon::parse('2026-10-23')->format(PejotaHelper::getUserDateFormat()),
            );
    }

    public function test_custom_interval_shows_the_weekday_of_the_chosen_date(): void
    {
        app()->setLocale('pt_BR');

        Livewire::test(ListTasks::class)
            ->mountTableBulkAction('postpone', [$this->makeTask()])
            ->assertFormFieldExists('date', fn (Field $field): bool => $field->isLive())
            ->fillForm(['interval' => 'custom', 'date' => '2026-11-03'])
            ->assertSchemaComponentVisible('resulting_date')
            ->assertSchemaComponentStateSet(
                'resulting_date',
                'terça-feira, '.Carbon::parse('2026-11-03')->format(PejotaHelper::getUserDateFormat()),
            );
    }

    public function test_custom_interval_without_a_date_hides_the_resulting_date(): void
    {
        Livewire::test(ListTasks::class)
            ->mountTableBulkAction('postpone', [$this->makeTask()])
            ->fillForm(['interval' => 'custom'])
            ->assertSuccessful()
            ->assertSchemaComponentHidden('resulting_date');
    }

    public function test_cleared_interval_hides_the_resulting_date_instead_of_failing(): void
    {
        Livewire::test(ListTasks::class)
            ->mountTableBulkAction('postpone', [$this->makeTask()])
            ->fillForm(['interval' => null])
            ->assertSuccessful()
            ->assertSchemaComponentHidden('resulting_date');
    }

    public function test_postpones_only_the_chosen_field_to_the_interval_target(): void
    {
        $task = $this->makeTask();

        Livewire::test(ListTasks::class)
            ->callTableBulkAction('postpone', [$task], ['field' => 'due_date', 'interval' => '1 week'])
            ->assertHasNoActionErrors();

        $task->refresh();
        $this->assertSame('2026-10-29', $task->due_date->toDateString());
        $this->assertSame('2026-10-05', $task->planned_end->toDateString());
    }
}
