<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NunoMazer\Samehouse\BelongsToTenants;

class WhatsappMessage extends Model
{
    use BelongsToTenants;
    use HasFactory;

    protected $guarded = ['id'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'whatsapp_conversation_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(WhatsappAttachment::class);
    }

    public function direction(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->from_me ? __('Sent') : __('Received'),
        );
    }

    protected function casts(): array
    {
        return [
            'from_me' => 'boolean',
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
