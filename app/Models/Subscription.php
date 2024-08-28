<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use NunoMazer\Samehouse\BelongsToTenants;
use Spatie\Tags\HasTags;

class Subscription extends Model
{
    use HasFactory, BelongsToTenants, HasTags;

    protected $guarded = ['id'];

    protected $casts = [
        'trial_ends_at' => 'date',
        'canceled_at' => 'date',
        'price' => MoneyCast::class,
    ];
}
