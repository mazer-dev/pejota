<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\InvoiceStatusEnum;
use App\Exceptions\MissingExchangeRateException;
use App\Helpers\PejotaHelper;
use App\Services\ExchangeRateService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Invoice $invoice): void {
            if ($invoice->status === InvoiceStatusEnum::PAID) {
                if ($invoice->exchange_rate === null) {
                    $invoice->exchange_rate = $invoice->resolveAutomaticRate();
                }
            } else {
                $invoice->exchange_rate = null;
            }
        });
    }

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

    public function deliveries(): HasMany
    {
        return $this->hasMany(InvoiceDelivery::class);
    }

    public function scopePending(Builder $query): void
    {
        $query->whereIn('status', [
            InvoiceStatusEnum::SENT->value,
            InvoiceStatusEnum::PARTIALLY_PAID->value,
        ]);
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->pending()
            ->whereDate('due_date', '<', static::currentDay()->toDateString());
    }

    public function scopeDueWithin(Builder $query, int $days): void
    {
        $today = static::currentDay();

        $query->pending()
            ->whereBetween('due_date', [
                $today->toDateString(),
                $today->addDays($days)->toDateString(),
            ]);
    }

    public function scopeDelinquent(Builder $query): void
    {
        $query->where('status', InvoiceStatusEnum::UNPAID->value);
    }

    public function scopeReceivedBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('status', InvoiceStatusEnum::PAID->value)
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()]);
    }

    protected function baseTotal(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                $base = PejotaHelper::getUserCurrency();
                $currency = $this->currency ?? $base;

                if ($this->exchange_rate !== null) {
                    return (float) $this->total * (float) $this->exchange_rate;
                }

                if ($currency === $base) {
                    return (float) $this->total;
                }

                return app(ExchangeRateService::class)->convert(
                    (float) $this->total,
                    $currency,
                    $base,
                    CarbonImmutable::now(PejotaHelper::getUserTimeZone()),
                );
            },
        );
    }

    protected function resolveAutomaticRate(): ?float
    {
        $base = PejotaHelper::getUserCurrency();
        $currency = $this->currency ?? $base;

        if ($currency === $base) {
            return 1.0;
        }

        try {
            return app(ExchangeRateService::class)->convert(
                1.0,
                $currency,
                $base,
                $this->payment_date ?? CarbonImmutable::now(PejotaHelper::getUserTimeZone()),
            );
        } catch (MissingExchangeRateException) {
            return null;
        }
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
