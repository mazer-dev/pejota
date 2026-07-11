<?php

namespace App\Services;

use App\Enums\CompanyRoleEnum;
use App\Models\Company;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class CompanyService
{
    public function create(User $user, ?string $name = null, ?string $email = null): Company
    {
        $company = Company::create([
            'user_id' => $user->id,
            'name' => filled($name) ? $name : $user->name,
            'email' => filled($email) ? $email : $user->email,
        ]);

        $company->users()->attach($user->id, [
            'joined_at' => now(),
        ]);

        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        try {
            $registrar->setPermissionsTeamId($company->id);
            $user->assignRole(CompanyRoleEnum::Owner->value);
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }

        return $company;
    }
}
