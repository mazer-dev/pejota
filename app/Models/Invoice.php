<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\InvoiceStatusEnum;
use App\Helpers\PejotaHelper;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NunoMazer\Samehouse\BelongsToTenants;
use Spatie\Tags\HasTags;

class Invoice extends Model
{
    use BelongsToTenants, HasFactory, HasTags;

    protected $guarded = ['id'];

    protected function isOverdue(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->due_date?->isPast()
                && in_array($this->status, [InvoiceStatusEnum::SENT, InvoiceStatusEnum::PARTIALLY_PAID], true),
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

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereIn('status', [
            InvoiceStatusEnum::SENT->value,
            InvoiceStatusEnum::PARTIALLY_PAID->value,
        ]);
    }

    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->pending()
            ->whereDate('due_date', '<', static::currentDay()->toDateString());
    }

    #[Scope]
    protected function dueWithin(Builder $query, int $days): void
    {
        $today = static::currentDay();

        $query->pending()
            ->whereBetween('due_date', [
                $today->toDateString(),
                $today->addDays($days)->toDateString(),
            ]);
    }

    #[Scope]
    protected function delinquent(Builder $query): void
    {
        $query->where('status', InvoiceStatusEnum::UNPAID->value);
    }

    #[Scope]
    protected function receivedBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('status', InvoiceStatusEnum::PAID->value)
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()]);
    }

    private static function currentDay(): CarbonImmutable
    {
        return CarbonImmutable::now(PejotaHelper::getUserTimeZone())->startOfDay();
    }

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'payment_date' => 'date:Y-m-d',
            'total' => MoneyCast::class,
            'discount' => MoneyCast::class,
            'status' => InvoiceStatusEnum::class,
        ];
    }
}
