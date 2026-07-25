<?php

namespace App\Models;

use App\Enums\DailyPlanItemStatusEnum;
use App\Enums\DailyPlanModeEnum;
use App\Enums\DailyPlanStatusEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NunoMazer\Samehouse\BelongsToTenants;

class DailyPlan extends Model
{
    use BelongsToTenants;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::deleting(function (self $plan): void {
            $plan->items()->delete();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DailyPlanItem::class)->orderBy('position');
    }

    public function scopeForDate(Builder $query, CarbonInterface|string $date): void
    {
        $query->whereDate('plan_date', $date instanceof CarbonInterface ? $date->toDateString() : $date);
    }

    public function scopeReady(Builder $query): void
    {
        $query->where('status', DailyPlanStatusEnum::READY->value);
    }

    public function isReady(): bool
    {
        return $this->status === DailyPlanStatusEnum::READY;
    }

    public function isGenerating(): bool
    {
        return $this->status === DailyPlanStatusEnum::GENERATING;
    }

    public function isFailed(): bool
    {
        return $this->status === DailyPlanStatusEnum::FAILED;
    }

    public function isLight(): bool
    {
        return $this->mode === DailyPlanModeEnum::LIGHT;
    }

    public function pendingMinutes(): int
    {
        return (int) $this->items
            ->where('status', DailyPlanItemStatusEnum::PENDING)
            ->sum('estimated_minutes');
    }

    protected function casts(): array
    {
        return [
            'plan_date' => 'date',
            'mode' => DailyPlanModeEnum::class,
            'status' => DailyPlanStatusEnum::class,
            'warnings' => 'array',
            'generated_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
