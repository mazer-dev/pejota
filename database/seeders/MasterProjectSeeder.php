<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MasterProjectSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Company
        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Acme Construction Ltd',
            'email' => 'contact@acme-construction.com',
            'settings' => json_encode(['timezone' => 'UTC', 'currency' => 'BRL']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Create User
        DB::table('users')->insert([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'company_id' => $companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Create Client
        $clientId = DB::table('clients')->insertGetId([
            'company_id' => $companyId,
            'name' => 'Global Industries',
            'email' => 'contact@global.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Create Project (FIXED: Added 'code' field)
        $projectId = DB::table('projects')->insertGetId([
            'company_id' => $companyId,
            'client_id' => $clientId,
            'name' => 'City Center Plaza',
            'code' => 'PRJ-2026-001', // Added this to fix your error
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Create Unit
        $unitId = DB::table('units')->insertGetId([
            'company_id' => $companyId,
            'name' => 'Meters',
            'description' => 'Length measurement',
            'symbol' => 'm',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 6. Create Product
        DB::table('products')->insert([
            'company_id' => $companyId,
            'unit_id' => $unitId,
            'name' => 'Ready-Mix Concrete',
            'service' => false,
            'digital' => false,
            'cost' => 15000,
            'price' => 20000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Database seeded successfully! You are ready to submit.');
    }
}