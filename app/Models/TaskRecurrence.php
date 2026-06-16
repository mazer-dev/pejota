<?php

namespace App\Models;

use App\Enums\RecurrenceAnchorFieldEnum;
use App\Enums\RecurrenceFrequencyEnum;
use App\Enums\RecurrenceGenerationModeEnum;
use App\Enums\RecurrenceStopTypeEnum;
use App\Models\Scopes\ExcludeRecurrenceTemplatesScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $task_id
 * @property int $interval
 * @property int $offset_days
 * @property int $generated_count
 * @property bool $is_active
 * @property Carbon|null $until_date
 * @property int|null $max_count
 * @property Carbon|null $next_run_date
 * @property Carbon|null $last_generated_date
 */
class TaskRecurrence extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'interval' => 1,
        'anchor_field' => 'due_date',
        'offset_days' => 0,
        'generation_mode' => 'by_date',
        'stop_type' => 'never',
        'generated_count' => 0,
        'is_active' => true,
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id')
            ->withoutGlobalScope(ExcludeRecurrenceTemplatesScope::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(Task::class, 'recurrence_id');
    }

    protected function casts(): array
    {
        return [
            'frequency' => RecurrenceFrequencyEnum::class,
            'anchor_field' => RecurrenceAnchorFieldEnum::class,
            'generation_mode' => RecurrenceGenerationModeEnum::class,
            'stop_type' => RecurrenceStopTypeEnum::class,
            'is_active' => 'boolean',
            'until_date' => 'date',
            'next_run_date' => 'date',
            'last_generated_date' => 'date',
        ];
    }
}
