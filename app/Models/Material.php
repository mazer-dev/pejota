<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    //
    // These match exactly the columns in your migration
    protected $fillable = [
        'sku',
        'name',
        'unit',
        'cost_price',
        'sale_price',
        'vat_rate',
    ];
    // This makes sure VAT is an integer and prices are decimals
    protected $casts = [
        'vat_rate' => 'integer',
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
    ];
}
