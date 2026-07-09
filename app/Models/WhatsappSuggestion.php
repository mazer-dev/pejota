<?php

namespace App\Models;

use App\Enums\WhatsappSuggestionStatusEnum;
use App\Enums\WhatsappSuggestionTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NunoMazer\Samehouse\BelongsToTenants;

class WhatsappSuggestion extends Model
{
    use BelongsToTenants;

    protected $guarded = ['id'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'whatsapp_conversation_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'whatsapp_message_id');
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

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', WhatsappSuggestionStatusEnum::Pending);
    }

    protected function casts(): array
    {
        return [
            'type' => WhatsappSuggestionTypeEnum::class,
            'status' => WhatsappSuggestionStatusEnum::class,
            'payload' => 'array',
            'accepted_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }
}
