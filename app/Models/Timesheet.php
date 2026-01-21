<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NunoMazer\Samehouse\BelongsToTenants;

class Timesheet extends Model
{
    use BelongsToTenants;

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'hours' => 'decimal:2',
    ];
    protected $attributes = [
    'company_id' => 2,
];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}