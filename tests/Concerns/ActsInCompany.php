<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use NunoMazer\Samehouse\Facades\Landlord;

trait ActsInCompany
{
    protected function actingInCompany(User $user, ?Company $company = null): Company
    {
        $company ??= $user->companies()->wherePivotNotNull('joined_at')->firstOrFail();

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($company);
        Landlord::addTenant('company_id', $company->getKey());
        Landlord::applyTenantScopesToDeferredModels();

        return $company;
    }
}
