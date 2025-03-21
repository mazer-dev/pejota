<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\BankAccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NunoMazer\Samehouse\BelongsToTenants;
use Spatie\Tags\HasTags;

class BankAccount extends Model
{
    use HasFactory, BelongsToTenants, HasTags;

    protected $guarded = ['id'];

    protected $casts = [
        'initial_balance' => MoneyCast::class,
        'current_balance' => MoneyCast::class,
        'credit_limit' => MoneyCast::class,
        'loan_amount' => MoneyCast::class,
        'interest_rate' => 'float',
        'initial_balance_date' => 'date',
        'loan_start_date' => 'date',
        'loan_end_date' => 'date',
        'active' => 'boolean',
        'type' => BankAccountType::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}