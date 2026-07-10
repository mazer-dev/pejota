<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use NunoMazer\Samehouse\Facades\Landlord;
use Symfony\Component\HttpFoundation\Response;

class ApplyTenantToLandlord
{
    /**
     * Feed the Filament-resolved tenant into samehouse. Runs inside tenant
     * context (panel tenantMiddleware), so a null tenant here means the
     * request escaped tenant resolution — abort rather than run unscoped.
     *
     * Filament eager-boots the scoped resource models while building the panel
     * (before any tenant is resolved), so samehouse defers their global scope.
     * Flushing the deferred models applies the scope now that the tenant is
     * known — without it, those models would query unscoped and leak data.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        abort_if($tenant === null, 403, 'No active company.');

        Landlord::addTenant('company_id', $tenant->getKey());

        // Filament eager-boots tenant-scoped models during panel build, before any
        // tenant is resolved, so samehouse defers their company_id scope. Flush the
        // deferred scopes now — without this, those models read UNSCOPED (cross-tenant leak).
        Landlord::applyTenantScopesToDeferredModels();

        return $next($request);
    }
}
