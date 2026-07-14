<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Http\Request;

class RedirectInaccessibleTenant
{
    public function handle(Request $request, Closure $next): mixed
    {
        $panel = Filament::getCurrentPanel();

        if (! $panel?->hasTenancy() || ! $request->route()?->hasParameter('tenant')) {
            return $next($request);
        }

        $user = $panel->auth()->user();
        $tenant = $panel->getTenant($request->route()->parameter('tenant'));

        if ($user instanceof HasTenants && $tenant && ! $user->canAccessTenant($tenant)) {
            $resolver = config('pejota.blocked_tenant_redirect');

            if ($resolver) {
                $url = app($resolver)($tenant, $user);

                if ($url) {
                    return redirect()->to($url);
                }
            }
        }

        return $next($request);
    }
}
