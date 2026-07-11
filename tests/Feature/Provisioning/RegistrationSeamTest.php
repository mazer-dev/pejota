<?php

namespace Tests\Feature\Provisioning;

use App\Providers\Filament\AppPanelProvider;
use Filament\Pages\Auth\Register;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationSeamTest extends TestCase
{
    use RefreshDatabase;

    private function buildAppPanel(): Panel
    {
        return (new AppPanelProvider(app()))->panel(Panel::make());
    }

    public function test_registration_disabled_when_config_is_null(): void
    {
        config(['pejota.registration_page' => null]);

        $this->assertFalse($this->buildAppPanel()->hasRegistration());
    }

    public function test_registration_enabled_when_config_points_to_a_page(): void
    {
        config(['pejota.registration_page' => Register::class]);

        $this->assertTrue($this->buildAppPanel()->hasRegistration());
    }
}
