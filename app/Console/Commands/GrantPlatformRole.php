<?php

namespace App\Console\Commands;

use App\Enums\PlatformRoleEnum;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

class GrantPlatformRole extends Command
{
    protected $signature = 'pj:grant-platform-role {email} {role}';

    protected $description = 'Grant a platform-axis role (super-admin/support-tier-1) to a user (team=0 sentinel)';

    public function handle(): int
    {
        $role = $this->argument('role');

        if (! in_array($role, PlatformRoleEnum::values(), true)) {
            $this->error('Invalid platform role. Valid: '.implode(', ', PlatformRoleEnum::values()));

            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error("User not found: {$this->argument('email')}");

            return self::FAILURE;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId(PlatformRoleEnum::TeamId);
        $user->assignRole($role);

        $this->info("Granted platform role '{$role}' to {$user->email}.");

        return self::SUCCESS;
    }
}
