<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NunoMazer\Samehouse\BelongsToTenants;

class Product extends Model
{
    use BelongsToTenants,
        HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'service' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
