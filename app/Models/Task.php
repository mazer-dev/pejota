<?php

namespace App\Models;

use App\Enums\CompanySettingsEnum;
use App\Enums\StatusPhaseEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NunoMazer\Samehouse\BelongsToTenants;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Tags\HasTags;

class Task extends Model
{
    use BelongsToTenants,
        HasFactory,
        HasFilamentComments,
        HasTags,
        LogsActivity;

    public const LOG_NAME = 'task';

    public const LOG_EVENT_STATUS_CHANGED = 'status_changed';

    protected $guarded = ['id'];

    protected $casts = [
        'checklist' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->isDirty('status_id')) {
                self::setStartEndDates($model);

                //                activity(self::LOG_NAME)
                //                    ->event(self::LOG_EVENT_STATUS_CHANGED)
                //                    ->performedOn($model)
                //                    ->
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

    public function workSessions(): HasMany
    {
        return $this->hasMany(WorkSession::class);
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status.name'])
            ->useLogName(self::LOG_NAME)
            ->dontSubmitEmptyLogs()
            ->logOnlyDirty();
    }

    public function scopeOpened(Builder $query)
    {
        $query->whereHas('status', function (Builder $query) {
            $query->whereIn('phase', [
                StatusPhaseEnum::TODO,
                StatusPhaseEnum::IN_PROGRESS,
            ]);
        });
    }

    public function scopeClosed(Builder $query)
    {
        $query->whereHas('status', function (Builder $query) {
            $query->whereIn('phase', [
                StatusPhaseEnum::CLOSED,
            ]);
        });
    }
}
