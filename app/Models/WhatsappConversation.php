<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use NunoMazer\Samehouse\BelongsToTenants;

class WhatsappConversation extends Model
{
    use BelongsToTenants;
    use HasFactory;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $conversation): void {
            if (filled($conversation->name)) {
                return;
            }

            $client = $conversation->client_id ? Client::find($conversation->client_id) : null;
            $conversation->name = collect([
                $conversation->push_name,
                $client?->name,
                $client?->tradename,
                $conversation->phone_number,
                $conversation->remote_jid,
            ])->first(fn ($value): bool => is_string($value) && trim($value) !== '') ?: 'Conversa do WhatsApp';
        });

        static::deleting(function (self $conversation): void {
            $conversation->messages()->get()->each(fn (WhatsappMessage $message) => $message->delete());
            $conversation->suggestions()->get()->each(fn (WhatsappSuggestion $suggestion) => $suggestion->delete());
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class)->oldest('sent_at')->oldest('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(WhatsappMessage::class)->latestOfMany('sent_at');
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(WhatsappSuggestion::class);
    }

    public function lastSuggestedMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'last_suggested_message_id');
    }

    public function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->name,
        );
    }

    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
            'last_message_at' => 'datetime',
            'context_updated_at' => 'datetime',
            'unread_count' => 'integer',
            'context_tokens' => 'integer',
            'last_suggested_message_id' => 'integer',
        ];
    }
}
