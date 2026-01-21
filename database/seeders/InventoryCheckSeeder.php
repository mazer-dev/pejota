<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Material;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class InventoryCheckSeeder extends Seeder
{
    public function run(): void
    {
       // 1. Create a Company (The Tenant)
        $company = Company::create([
            'name' => 'BuildMaster Construction Ltd',
            'email' => 'contact@buildmaster.com', // Added this line to fix the error
        ]);
        // 2. Create a User linked to this company
        $user = User::create([
            'name' => 'Project Admin',
            'email' => 'admin@buildmaster.com',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
        ]);

        // Log the user in so the Model Boot/Hooks find the company_id
        Auth::login($user);

        // 3. Create Branches (Requirement 2.B: Track stock per branch)
        $warehouse = Branch::create([
            'name' => 'Central Warehouse',
            'address' => '123 Industrial Zone',
            'contact' => '555-0101',
            'is_active' => true,
            'company_id' => $company->id,
        ]);

        $site = Branch::create([
            'name' => 'Downtown Plaza Site',
            'address' => '456 City Center',
            'contact' => '555-0102',
            'is_active' => true,
            'company_id' => $company->id,
        ]);

        // 4. Create Materials (Requirement 6: SKU, Unit, and Financials)
        $cement = Material::create([
            'name' => 'Portland Cement',
            'sku' => 'CMNT-BAG-01',
            'unit' => 'bag',
            'cost_price' => 12.50,
            'reorder_point' => 50.00, // SRS: Low-stock alert threshold
        ]);

        $rebar = Material::create([
            'name' => 'Deformed Steel Bar 16mm',
            'sku' => 'STEL-16MM-T',
            'unit' => 'ton',
            'cost_price' => 900.00,
            'reorder_point' => 5.00,
        ]);

        // 5. Stock Movements (The Audit Trail)
        
        // Initial Purchase: 200 bags to Warehouse
        StockMovement::create([
            'material_id' => $cement->id,
            'to_branch_id' => $warehouse->id,
            'qty' => 200,
            'type' => 'in',
            'company_id' => $company->id,
            'movement_date' => now(),

        ]);

        // Transfer: 50 bags from Warehouse to Construction Site
        StockMovement::create([
            'material_id' => $cement->id,
            'from_branch_id' => $warehouse->id,
            'to_branch_id' => $site->id,
            'qty' => 50,
            'type' => 'transfer',
            'company_id' => $company->id,
            'movement_date' => now(),
        ]);

        // Consumption: 20 bags used on site (Out)
        StockMovement::create([
            'material_id' => $cement->id,
            'from_branch_id' => $site->id,
            'qty' => 20,
            'type' => 'out',
            'reason' => 'Concrete pouring for Floor 1', // SRS Reason Code
            'company_id' => $company->id,
            'movement_date' => now(),
        ]);
    }
}