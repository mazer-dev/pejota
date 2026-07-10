<?php

use App\Enums\CompanyRoleEnum;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Grant the spatie `owner` role (scoped to each company's team) to every
     * existing company owner (companies.user_id). Owners created before Phase 1
     * have membership but no spatie role; without this they would lose invoice
     * access at cutover. Idempotent (assignRole no-ops if already assigned);
     * no-op on fresh installs (no companies at migrate time).
     */
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);

        Company::query()
            ->whereNotNull('user_id')
            ->with('owner')
            ->each(function (Company $company) use ($registrar): void {
                $owner = $company->owner;

                if ($owner === null) {
                    return;
                }

                $registrar->setPermissionsTeamId($company->id);
                $owner->assignRole(CompanyRoleEnum::Owner->value);
            });

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Not reversed: backfilled assignments are indistinguishable from
        // roles granted afterwards; revoking them would remove legitimate access.
    }
};
