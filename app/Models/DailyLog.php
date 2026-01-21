<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DailyLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'log_date',
        'description',
        'photos',
        'materials_data',
    ];

    protected $casts = [
        'log_date' => 'date',
        'photos' => 'array', // Crucial for storing multiple uploaded images
    ];

    /**
     * Relationship: Each log entry belongs to a specific project.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Relationship: Link daily logs to materials via a pivot table.
     * This allows tracking quantity of each material used in a log.
     */
    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'daily_log_material')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }
}