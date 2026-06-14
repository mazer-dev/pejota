<?php

namespace App\Providers;

use App\Sentry\ConfigureUserScope;
use Detection\MobileDetect;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ((new MobileDetect)->isMobile() == false) {
            FilamentView::registerRenderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => Blade::render("@livewire('top-navigate-action')"),
            );
        }

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        Event::listen(Authenticated::class, ConfigureUserScope::class);
    }
}
