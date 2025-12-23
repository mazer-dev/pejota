<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Milestone extends Model
{
    /* Allow mass assignment for these specific fields */
    protected $fillable = [
        'project_id',
        'name',
        'due_date',
        'is_completed',
    ];

    /**
     * Get the project that owns the milestone.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
