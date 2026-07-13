<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class TopNavigateHookScopeTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    /**
     * Filament only pushes a panel's render hooks into the global ViewManager
     * when that panel's boot() lifecycle runs (normally via the `SetUpPanel`
     * middleware for a request routed to that panel). Before the fix,
     * `top-navigate-action` was registered directly on `FilamentView` from
     * `AppServiceProvider::boot()`, which runs unconditionally for every
     * request/test regardless of which panel (if any) is booted — i.e. it was
     * global. Querying the hook here, with no panel ever booted, proves it is
     * no longer registered unconditionally: it only shows up once the `app`
     * panel itself boots (see the sibling test below), never "for free".
     */
    public function test_top_navigate_hook_is_not_registered_without_booting_a_panel(): void
    {
        $user = User::factory()->create();
        $this->actingInCompany($user);

        $hook = FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, scopes: 'admin')->toHtml();

        $this->assertStringNotContainsString('top-navigate-action', $hook);
    }

    public function test_top_navigate_hook_still_renders_once_the_app_panel_boots(): void
    {
        $user = User::factory()->create();
        $this->actingInCompany($user);
        Filament::bootCurrentPanel();

        $hook = FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, scopes: 'app')->toHtml();

        $this->assertStringContainsString('top-navigate-action', $hook);
    }
}
