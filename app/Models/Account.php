<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use NunoMazer\Samehouse\BelongsToTenants;

class Account extends Model
{
    use BelongsToTenants, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'initial_balance_at' => 'date:Y-m-d',
            'initial_balance_at' => MoneyCast::class,
        ];
    }
}
