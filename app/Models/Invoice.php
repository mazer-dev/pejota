<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\InvoiceStatusEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NunoMazer\Samehouse\BelongsToTenants;
use Spatie\Tags\HasTags;

class Invoice extends Model
{
    use HasFactory, BelongsToTenants, HasTags;

    protected $guarded = ['id'];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'payment_date' => 'date:Y-m-d',
        'total' => MoneyCast::class,
        'discount' => MoneyCast::class,
        'status' => InvoiceStatusEnum::class,
    ];

    protected function isOverdue(): Attribute
    {
        return Attribute::make(
            get: fn() =>
                $this->due_date->isPast() && $this->status != InvoiceStatusEnum::PAID,
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
