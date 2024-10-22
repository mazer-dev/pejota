<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\InvoiceStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use NunoMazer\Samehouse\BelongsToTenants;

class Account extends Model
{
    use HasFactory, BelongsToTenants;

    protected $guarded = ['id'];

    protected $casts = [
        'initial_balance_at' => 'date:Y-m-d',
        'initial_balance_at' => MoneyCast::class,
    ];
}
