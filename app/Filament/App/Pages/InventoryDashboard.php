<?php

namespace App\Filament\App\Pages;

use App\Models\Material;
use App\Models\StockMovement;
use App\Models\Branch;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class InventoryDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.app.pages.inventory-dashboard';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Inventory Dashboard';

    protected static ?int $navigationSort = 0;

    public function getLowStockMaterials(): Collection
    {
        return Material::all()->filter(function ($material) {
            return $material->stock_level <= $material->reorder_point;
        });
    }

    public function getTotalMaterials(): int
    {
        return Material::count();
    }

    public function getTotalBranches(): int
    {
        return Branch::where('is_active', true)->count();
    }

    public function getRecentMovements(): Collection
    {
        return StockMovement::with(['material', 'fromBranch', 'toBranch'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    public function getStockByBranch(): Collection
    {
        $branches = Branch::where('is_active', true)->get();
        
        return $branches->map(function ($branch) {
            $stockIn = StockMovement::where('to_branch_id', $branch->id)
                ->whereIn('type', ['in', 'transfer', 'adjustment_add'])
                ->sum('qty');
            
            $stockOut = StockMovement::where('from_branch_id', $branch->id)
                ->whereIn('type', ['out', 'transfer', 'adjustment_subtract'])
                ->sum('qty');

            return [
                'name' => $branch->name,
                'stock' => $stockIn - $stockOut,
            ];
        });
    }

    public function getTotalStockValue(): float
    {
        return Material::all()->sum(function ($material) {
            return $material->stock_level * ($material->cost_price ?? 0);
        });
    }
}