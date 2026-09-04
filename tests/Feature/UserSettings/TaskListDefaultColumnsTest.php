<?php

namespace Tests\Feature\UserSettings;

use App\Enums\UserSettingsEnum;
use App\Filament\App\Resources\TaskResource;
use App\Models\User;
use Filament\Tables\Columns\Column;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class TaskListDefaultColumnsTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    /**
     * @param  array<int, string>  $selected
     */
    private function columnNamed(string $name, array $selected): Column
    {
        $user = User::factory()->create();
        $this->actingInCompany($user);
        $user->settings()->set(UserSettingsEnum::TASKS_DEFAULT_LIST_COLUMNS->value, $selected);

        foreach (TaskResource::getTableColumns() as $column) {
            if ($column->getName() === $name) {
                return $column;
            }
        }

        $this->fail("Column [{$name}] is not offered by TaskResource::getTableColumns().");
    }

    public function test_client_column_follows_its_own_option_key(): void
    {
        $this->assertFalse(
            $this->columnNamed('client', ['client'])->isToggledHiddenByDefault(),
            'Selecting the client column in the preferences must show it in the task list.',
        );
    }

    public function test_status_color_column_follows_its_own_option_key(): void
    {
        $this->assertFalse(
            $this->columnNamed('status.color', ['status.color'])->isToggledHiddenByDefault(),
            'Selecting the status color column in the preferences must show it in the task list.',
        );
    }

    public function test_unselected_column_stays_hidden(): void
    {
        $this->assertTrue(
            $this->columnNamed('client', ['title'])->isToggledHiddenByDefault(),
        );
    }
}
