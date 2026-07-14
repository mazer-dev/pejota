<?php

namespace Tests\Feature;

use App\Providers\Filament\AppPanelProvider;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Tests\TestCase;

class AppPanelPluginsSeamTest extends TestCase
{
    public function test_app_panel_registers_exactly_the_plugins_declared_in_config(): void
    {
        $panel = Filament::getPanel('app');
        $configured = config('pejota.app_panel_plugins', []);

        // Cross-repo invariant: the booted `app` panel carries exactly the plugins the
        // config declares. Open core declares none (empty default) → no plugins; the
        // cloud overlay declares its billing plugin → that plugin is registered. Holds
        // in both repos, so the cloud merge never has to rewrite this test.
        $this->assertCount(count($configured), $panel->getPlugins());

        foreach ($configured as $pluginClass) {
            $this->assertTrue($panel->hasPlugin(app($pluginClass)->getId()));
        }
    }

    public function test_app_panel_registers_plugins_listed_in_config(): void
    {
        config(['pejota.app_panel_plugins' => [SeamFakePlugin::class]]);

        $panel = (new AppPanelProvider(app()))->panel(Panel::make());

        $this->assertTrue($panel->hasPlugin('seam-fake'));
    }
}

class SeamFakePlugin implements Plugin
{
    public function getId(): string
    {
        return 'seam-fake';
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}
}
