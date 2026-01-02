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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}