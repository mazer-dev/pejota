<?php

namespace Database\Seeders;

use App\Enums\CompanyRoleEnum;
use App\Enums\PlatformRoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        foreach ([...CompanyRoleEnum::values(), ...PlatformRoleEnum::values()] as $name) {
            Role::findOrCreate($name, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
