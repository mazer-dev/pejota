<?php

namespace App\Http\Middleware;

use App\Helpers\PejotaHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocalizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            app()->setLocale(PejotaHelper::getUserLocate());
        }

        return $next($request);
    }
}
