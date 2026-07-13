<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NunoMazer\Samehouse\BelongsToTenants;

class AssistantConversation extends Model
{
    use BelongsToTenants,
        HasFactory;

    public const CHANNEL_WEB = 'web';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    protected $guarded = ['id'];

    /**
     * Deletes messages (which in turn delete their attachments and physical
     * files) whenever a conversation is deleted through Eloquent.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $conversation): void {
            $conversation->messages()->get()->each(fn (AssistantMessage $message) => $message->delete());
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class)->oldest('id');
    }

    protected function casts(): array
    {
        return [
            'pending_action' => 'array',
            'closed_at' => 'datetime',
        ];
    }
}
