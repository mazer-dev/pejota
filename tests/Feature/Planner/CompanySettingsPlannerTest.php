<?php

namespace Tests\Feature\Planner;

use App\Enums\CompanySettingsEnum;
use App\Filament\App\Pages\CompanySettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanySettingsPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_planning_tab_renders_with_per_day_hours_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CompanySettings::class)
            ->assertSee(__('Planning'))
            ->assertSee(__('Monday'))
            ->assertSee(__('Sunday'))
            ->assertStatus(200);
    }

    public function test_saving_day_hours_persists_in_company_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CompanySettings::class)
            ->fillForm([
                CompanySettingsEnum::PLANNER_DAY_HOURS->value => [
                    1 => 8, 2 => 8, 3 => 8, 4 => 8, 5 => 8, 6 => 4, 7 => 3,
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $stored = $user->company->fresh()->settings()->get(CompanySettingsEnum::PLANNER_DAY_HOURS->value);

        $this->assertSame(4, (int) ($stored[6] ?? $stored['6'] ?? null));
        $this->assertSame(3, (int) ($stored[7] ?? $stored['7'] ?? null));
    }
}
