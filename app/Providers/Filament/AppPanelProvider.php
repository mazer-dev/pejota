<?php

namespace App\Providers\Filament;

use App\Filament\App\Widgets\OverallNumbersOverview;
use App\Http\Middleware\LocalizationMiddleware;
use App\Http\Middleware\TenantMiddleware;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->login()
            ->colors([
                'primary' => Color::Green,
            ])
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): string => ' <div class="inline text-gray-400 justify-center max-w-full">
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
            ->maxContentWidth(MaxWidth::Full)
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\\Filament\\App\\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\\Filament\\App\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\\Filament\\App\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                OverallNumbersOverview::class,
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
