<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use NunoMazer\Samehouse\BelongsToTenants;

class Unit extends Model
{
    use BelongsToTenants,
        HasFactory;

    protected $guarded = ['id'];
}
