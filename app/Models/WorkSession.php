<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NunoMazer\Samehouse\BelongsToTenants;

class WorkSession extends Model
{
    use HasFactory,
        BelongsToTenants;

    protected $guarded = ['id'];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
    ];

    public function calculateDuration(): void
    {
        if ($this->start && $this->end) {
            $this->duration = $this->end->diffInMinutes($this->start);
        }
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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
