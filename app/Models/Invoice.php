<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use NunoMazer\Samehouse\BelongsToTenants;
use Spatie\Tags\HasTags;

class Invoice extends Model
{
    use HasFactory, BelongsToTenants, HasTags;

    protected $guarded = ['id'];

    protected $casts = [
        'due_date' => 'date',
        'total' => MoneyCast::class,
        'discount' => MoneyCast::class,
        'status' => Invoice::class,
    ];
}
