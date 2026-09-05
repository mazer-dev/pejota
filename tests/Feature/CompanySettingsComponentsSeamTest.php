<?php

namespace Tests\Feature;

use App\Contracts\CompanySettingsComponents;
use App\Filament\App\Pages\CompanySettings;
use App\Models\Currency;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

/**
 * Cross-repo seam: `config('pejota.company_settings_components')` lets an overlay
 * append schema components to a tab of the company settings page instead of
 * publishing a second settings page of its own. Open core declares none.
 *
 * Persistence is the assertion because it is the whole point of the seam: a
 * component that is not in the schema contributes nothing to `$this->form->getState()`,
 * so its key never reaches the settings record. Asserting the key survives a save
 * proves the component was really mounted, not merely rendered.
 */
class CompanySettingsComponentsSeamTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::factory()->create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    }

    /**
     * Cross-repo invariant: the seam ships OFF. Reading the config FILE instead of
     * the runtime value is deliberate - an overlay registers its components at
     * runtime, from its service provider, so the runtime value differs between
     * repos while the shipped default does not. Asserting the runtime value here
     * would make this test fail in the overlay for the correct behaviour.
     */
    public function test_the_seam_ships_with_no_registered_components(): void
    {
        $shipped = require config_path('pejota.php');

        $this->assertSame([], $shipped['company_settings_components']);
    }

    public function test_components_registered_for_a_tab_are_mounted_and_persisted(): void
    {
        config(['pejota.company_settings_components' => ['Finance' => [SeamFakeCompanySettingsComponents::class]]]);

        $company = $this->actingInCompany(User::factory()->create());

        Livewire::test(CompanySettings::class)
            ->set('data.seam.fake_setting', 'injected')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('injected', $company->refresh()->settings()->get('seam.fake_setting'));
    }

    public function test_without_the_config_the_same_key_is_not_part_of_the_schema(): void
    {
        $company = $this->actingInCompany(User::factory()->create());

        Livewire::test(CompanySettings::class)
            ->set('data.seam.fake_setting', 'injected')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($company->refresh()->settings()->get('seam.fake_setting'));
    }

    public function test_a_tab_name_that_does_not_exist_is_ignored(): void
    {
        config(['pejota.company_settings_components' => ['NoSuchTab' => [SeamFakeCompanySettingsComponents::class]]]);

        $company = $this->actingInCompany(User::factory()->create());

        Livewire::test(CompanySettings::class)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($company->refresh()->settings()->get('seam.fake_setting'));
    }
}

class SeamFakeCompanySettingsComponents implements CompanySettingsComponents
{
    public static function components(): array
    {
        return [
            TextInput::make('seam.fake_setting'),
        ];
    }
}
