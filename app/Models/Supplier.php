<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NunoMazer\Samehouse\BelongsToTenants;

class Supplier extends Model
{
    use BelongsToTenants;

    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'terms',
        'rating',
        'is_active',
        'company_id',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}