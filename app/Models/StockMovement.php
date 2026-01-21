<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NunoMazer\Samehouse\BelongsToTenants;
use Illuminate\Support\Facades\Auth;

class StockMovement extends Model
{
    use BelongsToTenants;

    protected $fillable = [
        'material_id',
        'type',
        'qty',
        'from_branch_id',
        'to_branch_id',
        'project_id',
        'reference',
        'reference_id',
        'reference_type',
        'reason',
        'movement_date',
        'company_id',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'movement_date' => 'date',
    ];
protected static function boot(): void
{
    parent::boot();

    static::creating(function ($model) {
        // Use Auth::check() to see if a user is logged in
        if (Auth::check()) {
            // Use Auth::user() to get the company_id
            $model->company_id = Auth::user()->company_id;
        }
    });
}
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}