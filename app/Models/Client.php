<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use NunoMazer\Samehouse\BelongsToTenants;

class Client extends Model
{
    use HasFactory,
        BelongsToTenants;

    protected $guarded = ['id'];
}
