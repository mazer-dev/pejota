<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
}
