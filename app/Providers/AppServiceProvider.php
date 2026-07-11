<?php

namespace App\Providers;

use App\Billing\NullBilling;
use App\Contracts\SubscriptionGate;
use App\Sentry\ConfigureUserScope;
use App\Services\Timesheet\Layouts\ClientTimesheetLayout;
use App\Services\Timesheet\Layouts\InternalTimesheetLayout;
use App\Services\Timesheet\TimesheetLayoutRegistry;
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
        $this->app->singleton(TimesheetLayoutRegistry::class, function (): TimesheetLayoutRegistry {
            $registry = new TimesheetLayoutRegistry;
            $registry->register(new ClientTimesheetLayout);
            $registry->register(new InternalTimesheetLayout);

            return $registry;
        });

        $this->app->bind(SubscriptionGate::class, NullBilling::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Filament\Resources\Resource::scopeToTenant(false);

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
