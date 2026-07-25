<?php

namespace App\Models;

use App\Enums\DailyPlanItemStatusEnum;
use App\Enums\DailyPlanItemTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NunoMazer\Samehouse\BelongsToTenants;

class DailyPlanItem extends Model
{
    use BelongsToTenants;

    protected $guarded = ['id'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(DailyPlan::class, 'daily_plan_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'whatsapp_conversation_id');
    }

    public function markDone(): void
    {
        $this->update([
            'status' => DailyPlanItemStatusEnum::DONE,
            'done_at' => now(),
        ]);
    }

    public function markSkipped(): void
    {
        $this->update([
            'status' => DailyPlanItemStatusEnum::SKIPPED,
            'done_at' => null,
        ]);
    }

    public function reopen(): void
    {
        $this->update([
            'status' => DailyPlanItemStatusEnum::PENDING,
            'done_at' => null,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === DailyPlanItemStatusEnum::PENDING;
    }

    protected function casts(): array
    {
        return [
            'type' => DailyPlanItemTypeEnum::class,
            'status' => DailyPlanItemStatusEnum::class,
            'done_at' => 'datetime',
            'estimated_minutes' => 'integer',
            'position' => 'integer',
        ];
    }
}
