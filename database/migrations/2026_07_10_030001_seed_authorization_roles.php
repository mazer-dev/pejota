<?php

use Database\Seeders\RolesSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seed the role definitions from a data migration (not only a seeder) so
     * they exist in every RefreshDatabase test DB — User::factory() cascades
     * into an owner role assignment that would otherwise throw RoleDoesNotExist
     * — and so `migrate --force` provisions them on deploy. Idempotent.
     */
    public function up(): void
    {
        (new RolesSeeder)->run();
    }

    public function down(): void
    {
        // Definitions are reference data; leaving them is harmless on rollback.
    }
};
