<?php

namespace App\Http\Middleware;

use App\Enums\CompanySettingsEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocalizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $settings = auth()->user()->company->settings();
            if ($settings->get(CompanySettingsEnum::LOCALIZATION_LOCALE->value))
                app()->setLocale($settings->get(CompanySettingsEnum::LOCALIZATION_LOCALE->value));
        }

        return $next($request);
    }
}
