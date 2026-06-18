<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\CompanySettingsEnum;
use App\Enums\ContinuousModeEnum;
use App\Enums\StatusPhaseEnum;
use App\Helpers\PejotaHelper;
use App\Models\Scopes\ExcludeRecurrenceTemplatesScope;
use App\Services\RecurrenceService;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use NunoMazer\Samehouse\BelongsToTenants;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Tags\HasTags;

/**
 * Class Model Task
 *
 * @property int $id
 * @property string $title
 * @property int|null $client_id
 * @property int|null $project_id
 * @property int $status_id
 * @property int|null $parent_id
 * @property Carbon|null $planned_start
 * @property Carbon|null $planned_end
 * @property Carbon|null $actual_start
 * @property Carbon|null $actual_end
 * @property Carbon|null $due_date
 * @property array|null $checklist
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[ScopedBy([ExcludeRecurrenceTemplatesScope::class])]
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

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->isDirty('status_id')) {
                self::setStartEndDates($model);
            }

            $model->continuous_mode = $model->is_continuous
                ? ContinuousModeEnum::DailyCheck
                : null;
        });

        static::updated(function (Task $model) {
            self::handleRecurrenceOnCompletion($model);
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

    public function taskCompletions(): HasMany
    {
        return $this->hasMany(TaskCompletion::class);
    }

    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(TaskRecurrence::class, 'recurrence_id');
    }

    protected static function setStartEndDates(Model $model): void
    {
        if (! auth()->check()) {
            return;
        }

        $settings = auth()->user()->company
            ->settings();

        $status = Status::find($model->status_id);

        if ($status) {
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

    protected static function handleRecurrenceOnCompletion(Task $model): void
    {
        if ($model->recurrence_id === null) {
            return;
        }

        if (! $model->wasChanged('status_id')) {
            return;
        }

        $status = Status::find($model->status_id);

        if (! $status || $status->phase !== StatusPhaseEnum::CLOSED->value) {
            return;
        }

        $completedOn = $model->actual_end
            ? Carbon::parse($model->actual_end)
            : Carbon::today(auth()->check() ? PejotaHelper::getUserTimeZone() : null);

        app(RecurrenceService::class)->generateOnCompletion($model, $completedOn);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status.name'])
            ->useLogName(self::LOG_NAME)
            ->dontSubmitEmptyLogs()
            ->logOnlyDirty();
    }

    public function scopeByProject(Builder $query, Project|int|null $project): void
    {
        if ($project) {
            $query->where('project_id', $project);
        }
    }

    public function scopeOpened(Builder $query): void
    {
        $query->whereHas('status', function (Builder $query) {
            $query->whereIn('phase', [
                StatusPhaseEnum::TODO,
                StatusPhaseEnum::IN_PROGRESS,
            ]);
        });
    }

    public function scopeClosed(Builder $query): void
    {
        $query->whereHas('status', function (Builder $query) {
            $query->whereIn('phase', [
                StatusPhaseEnum::CLOSED,
            ]);
        });
    }

    public function isContinuous(): bool
    {
        return (bool) $this->is_continuous;
    }

    public function isDailyCheck(): bool
    {
        return $this->isContinuous();
    }

    public function isDoneToday(): bool
    {
        return $this->taskCompletions()
            ->whereDate('completed_on', Carbon::today(PejotaHelper::getUserTimeZone()))
            ->exists();
    }

    public function markDoneToday(): void
    {
        $today = Carbon::today(PejotaHelper::getUserTimeZone())->toDateString();

        $existing = $this->taskCompletions()
            ->whereDate('completed_on', $today)
            ->first();

        if (! $existing) {
            $this->taskCompletions()->create([
                'completed_on' => $today,
                'company_id' => $this->company_id,
                'user_id' => auth()->id(),
            ]);
        }
    }

    public function markUndoneToday(): void
    {
        $this->taskCompletions()
            ->whereDate('completed_on', Carbon::today(PejotaHelper::getUserTimeZone()))
            ->delete();
    }

    public function scopeExcludeDoneTodayChecks(Builder $query): void
    {
        $today = Carbon::today(PejotaHelper::getUserTimeZone())->toDateString();

        $query->where(function (Builder $query) use ($today) {
            $query->where('is_continuous', false)
                ->orWhereDoesntHave('taskCompletions', function (Builder $query) use ($today) {
                    $query->whereDate('completed_on', $today);
                });
        });
    }

    public function currentStreak(): int
    {
        $dates = $this->taskCompletions()
            ->orderByDesc('completed_on')
            ->pluck('completed_on')
            ->map(fn ($date) => $date->toDateString())
            ->all();

        if ($dates === []) {
            return 0;
        }

        $today = Carbon::today(PejotaHelper::getUserTimeZone());

        if ($dates[0] !== $today->toDateString()) {
            return 0;
        }

        $streak = 0;
        $cursor = $today->copy();

        foreach ($dates as $date) {
            if ($date === $cursor->toDateString()) {
                $streak++;
                $cursor->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }

    public function scopeOrderedForList(Builder $query): void
    {
        $today = Carbon::today(PejotaHelper::getUserTimeZone())->toDateString();

        $query
            ->orderByDesc('is_continuous')
            ->orderByRaw(
                'CASE WHEN exists (select 1 from task_completions tc'
                .' where tc.task_id = tasks.id and DATE(tc.completed_on) = ?) THEN 1 ELSE 0 END asc',
                [$today],
            )
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END asc')
            ->orderBy('due_date')
            ->orderBy('id');
    }

    /**
     * Postpones the specified field by the given interval.
     *
     * @param  string  $field  The name of the field to be postponed.
     * @param  string  $interval  The interval by which the field should be postponed.
     * @param  bool  $fromNow  Whether to postpone from now or from the current value of the field.
     */
    public function postpone(string $field, string $interval, bool $fromNow = true): void
    {
        if ($this->{$field}) {
            $now = now()->tz(PejotaHelper::getUserTimeZone());
            if ($interval == 'today') {
                $this->{$field} = $now->format('Y-m-d');
            } else {
                if (in_array($field, ['planned_end', 'due_date'])) {
                    if (! $this->{$field} || $this->{$field}->startOfDay()->lte($now->startOfDay())) {
                        $nextDate = $now->copy()->add($interval);
                    } else {
                        $nextDate = $this->{$field}->copy()->add($interval);
                    }
                } else {
                    $nextDate = $fromNow ? $now->copy()->add($interval) : $this->{$field}->copy()->add($interval);
                }

                $this->{$field} = $nextDate;
            }
            $this->save();
        }
    }

    protected function casts(): array
    {
        return [
            'planned_start' => 'date',
            'planned_end' => 'date',
            'actual_start' => 'date',
            'actual_end' => 'date',
            'due_date' => 'date',
            'checklist' => 'array',
            'is_recurrence_template' => 'boolean',
            'is_continuous' => 'boolean',
            'continuous_mode' => ContinuousModeEnum::class,
            'hourly_rate' => MoneyCast::class,
        ];
    }
}
