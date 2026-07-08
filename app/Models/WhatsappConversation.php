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

    public function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->push_name ?: $this->phone_number ?: $this->remote_jid,
        );
    }

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'context_updated_at' => 'datetime',
            'unread_count' => 'integer',
            'context_tokens' => 'integer',
        ];
    }
}
