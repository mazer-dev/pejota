<?php

namespace App\Providers\Filament;

use App\Enums\MenuGroupsEnum;
use App\Filament\App\Pages\Dashboard;
use App\Http\Middleware\LocalizationMiddleware;
use App\Http\Middleware\TenantMiddleware;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->spa()
            ->unsavedChangesAlerts()
            ->login()
            ->passwordReset()
            ->brandName('Pejota')
            ->brandLogo(asset('imgs/pejota-logo.svg'))
            ->brandLogoHeight('10em')
            ->favicon(asset('imgs/pejota-logo.svg'))
            ->colors([
                'primary' => Color::hex('#00BF63'),
            ])
            ->viteTheme('resources/css/filament/app/theme.css')
            ->sidebarWidth('15rem;')
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn(): string => ' <div class="inline footer-contacts text-gray-400 text-center max-w-full">
                        By
                        <a href="https://mazer.dev" target="_blank"
                        >
                           MAZER.DEV
                        </a>
                        |
                        <a href="https://www.linkedin.com/company/mazer-dev" target="_blank"
                        >
                           LinkedIn
                        </a>
                        |
                        <a href="https://github.com/mazer-dev/pejota" target="_blank"
                        >
                           Github
                        </a>

                </div>'
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn() => Blade::render('@livewire(\'work-sessions-top-nav\')')
            )
            ->maxContentWidth(MaxWidth::Full)
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\\Filament\\App\\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\\Filament\\App\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\\Filament\\App\\Widgets')
            ->widgets([])
            ->navigationGroups([
                NavigationGroup::make(fn() => __(MenuGroupsEnum::DAILY_WORK->value))
                    ->icon('heroicon-o-inbox-stack'),
                NavigationGroup::make(fn() => __(MenuGroupsEnum::FINANCE->value))
                    ->icon('heroicon-o-currency-dollar'),
                NavigationGroup::make(fn() => __(MenuGroupsEnum::ADMINISTRATION->value))
                    ->icon('heroicon-o-briefcase'),
                NavigationGroup::make(fn() => __(MenuGroupsEnum::SETTINGS->value))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed(),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                LocalizationMiddleware::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                TenantMiddleware::class,
            ], isPersistent: true);
    }
}
