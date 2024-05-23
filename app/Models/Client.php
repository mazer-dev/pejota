<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use NunoMazer\Samehouse\BelongsToTenants;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;

class Client extends Model
{
    use HasFactory,
        BelongsToTenants,
        HasFilamentComments;

    protected $guarded = ['id'];

}
