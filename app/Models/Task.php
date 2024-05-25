<?php

namespace App\Models;

use App\Enums\CompanySettingsEnum;
use App\Enums\StatusPhaseEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NunoMazer\Samehouse\BelongsToTenants;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;
use Spatie\Tags\HasTags;

class Task extends Model
{
    use HasFactory,
        BelongsToTenants,
        HasFilamentComments,
        HasTags;

    protected $guarded = ['id'];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->isDirty('status_id')) {
                self::setStartEndDates($model);
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    protected static function setStartEndDates(Model $model): void
    {
        $settings = auth()->user()->company
                ->settings();

        $status = Status::find($model->status_id);

        if (
            $status->phase == StatusPhaseEnum::IN_PROGRESS->value &&
            $settings->get(CompanySettingsEnum::TASKS_FILL_ACTUAL_START_DATE_WHEN_IN_PROGRESS->value)
        ) {
            $model->actual_start = $model->actual_start ?? now()->format('Y-m-d');
        }

        if (
            $status->phase == StatusPhaseEnum::CLOSED->value &&
            $settings->get(CompanySettingsEnum::TASKS_FILL_ACTUAL_END_DATE_WHEN_CLOSED->value)
        ) {
            $model->actual_end = $model->actual_end ?? now()->format('Y-m-d');
        }

    }
}
