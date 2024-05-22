<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use NunoMazer\Samehouse\Facades\Landlord;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            if (auth()->user()->company?->id) {
                Landlord::addTenant('company_id', auth()->user()->company->id);
            }
        }

        return $next($request);
    }
}
