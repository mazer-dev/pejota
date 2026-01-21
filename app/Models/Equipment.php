<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NunoMazer\Samehouse\BelongsToTenants;

class Equipment extends Model
{
    use BelongsToTenants;

    protected $guarded = ['id'];

    protected $casts = [
        'last_maintenance_at' => 'date',
        'next_maintenance_at' => 'date',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            // Requirement: Automatic multi-tenant assignment
            if (\Illuminate\Support\Facades\Auth::check() && empty($model->company_id)) {
                $model->company_id = \Illuminate\Support\Facades\Auth::user()?->company_id;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}