<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\PurchaseOrder;
use App\Models\Material;


class PurchaseItem extends Model
{
    protected $guarded = ['id'];

    // Automatically calculate subtotal before saving
    protected static function booted()
    {
        static::saving(function ($item) {
            $item->subtotal = ($item->qty ?? 0) * ($item->unit_price ?? 0);
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}