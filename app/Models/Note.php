<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NunoMazer\Samehouse\BelongsToTenants;
use Spatie\Tags\HasTags;

class Note extends Model
{
    use BelongsToTenants,
        HasFactory,
        HasTags;

    protected $guarded = ['id'];

    protected $casts = [
        'content' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->user_id = auth()->user()->id;
        });
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
