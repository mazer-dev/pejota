<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Helpers\PejotaHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NunoMazer\Samehouse\BelongsToTenants;

class WorkSession extends Model
{
    use BelongsToTenants,
        HasFactory;

    protected $guarded = ['id'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (WorkSession $model): void {
            $model->user_id ??= auth()->id();
            if (is_null($model->currency)) {
                $model->currency = $model->resolveCurrency();
            }
        });

        static::saving(function (WorkSession $model): void {
            $model->recalculate();
        });
    }

    /**
     * Derive duration (minutes) and value (rate * hours) from start/end/rate.
     * start + end are the source of truth; duration and value are computed.
     * rate and value are both read/written through MoneyCast, so this math
     * operates entirely in major units (e.g. 100.00), never raw cents.
     *
     * @throws \InvalidArgumentException when end is before start
     */
    public function recalculate(): void
    {
        if ($this->start && $this->end) {
            if ($this->end->lessThan($this->start)) {
                throw new \InvalidArgumentException('Work session end must be greater than or equal to start.');
            }

            $this->duration = (int) round($this->start->diffInMinutes($this->end, absolute: false));
        }

        $this->value = $this->duration ? round((float) $this->rate * $this->duration / 60, 2) : 0;
    }

    /**
     * Read a money column as decimal WITHOUT MoneyCast's null->0 coercion,
     * so the cascade can distinguish "unset" from "zero".
     */
    private static function rawMoney(?Model $model, string $key): ?float
    {
        if (! $model) {
            return null;
        }

        $raw = $model->getAttributes()[$key] ?? null;

        return $raw === null ? null : (float) $raw / 100;
    }

    public function resolveRate(): float
    {
        return self::rawMoney($this->task, 'hourly_rate')
            ?? self::rawMoney($this->project, 'hourly_rate')
            ?? self::rawMoney($this->client, 'default_hourly_rate')
            ?? 0.0;
    }

    public function resolveCurrency(): string
    {
        if ($this->client?->currency) {
            return $this->client->currency;
        }

        if (auth()->check() && auth()->user()->company) {
            return PejotaHelper::getUserCurrency();
        }

        return 'USD';
    }

    public function resolveBillable(): bool
    {
        return $this->client?->billable_default ?? true;
    }

    public function isInvoiced(): bool
    {
        return $this->invoice_item_id !== null;
    }

    public function scopeBillableOpen(Builder $query): void
    {
        $query->where('billable', true)->whereNull('invoice_item_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    /**
     * Finish the WorkSession that is running
     */
    public function finish(): bool
    {
        if ($this->is_running) {
            $this->update([
                'end' => now(),
                'is_running' => false,
            ]);

            return true;
        }

        return false;
    }

    protected function casts(): array
    {
        return [
            'start' => 'datetime',
            'end' => 'datetime',
            'rate' => MoneyCast::class,
            'value' => MoneyCast::class,
            'is_running' => 'boolean',
            'billable' => 'boolean',
        ];
    }
}
