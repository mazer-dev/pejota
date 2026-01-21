<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Material extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'unit',
        'cost_price',
        'sale_price',
        'vat_rate',
        'reorder_point',
        'barcode',
        'supplier_id',
    ];

    protected $casts = [
        'vat_rate' => 'integer',
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'reorder_point' => 'decimal:2',
    ];

    /**
     * Get all stock movements for this material
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get supplier relationship
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Calculate current stock level (IN - OUT)
     */
    public function getStockLevelAttribute(): float
    {
        $in = $this->stockMovements()->where('type', 'in')->sum('qty');
        $out = $this->stockMovements()->where('type', 'out')->sum('qty');
        return $in - $out;
    }

    /**
     * Check if stock is below reorder point
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->stock_level <= $this->reorder_point;
    }

    /**
     * Get stock level for a specific branch
     */
    public function getStockAtBranch(int $branchId): float
    {
        $in = $this->stockMovements()
            ->where('to_branch_id', $branchId)
            ->where('type', 'in')
            ->sum('qty');
        
        $out = $this->stockMovements()
            ->where('from_branch_id', $branchId)
            ->where('type', 'out')
            ->sum('qty');
        
        return $in - $out;
    }
}