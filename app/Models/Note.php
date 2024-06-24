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

    protected $casts = [
        'content' => 'array',
    ];
}
