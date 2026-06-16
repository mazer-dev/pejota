<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use NunoMazer\Samehouse\BelongsToTenants;

/**
 * @property int $id
 * @property int $company_id
 * @property int $task_id
 * @property Carbon $completed_on
 * @property int $user_id
 */
class TaskCompletion extends Model
{
    use BelongsToTenants;

    protected $guarded = ['id'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'completed_on' => 'date',
        ];
    }
}
