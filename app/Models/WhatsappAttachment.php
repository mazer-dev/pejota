<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use NunoMazer\Samehouse\BelongsToTenants;

class WhatsappAttachment extends Model
{
    use BelongsToTenants;
    use HasFactory;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::deleting(function (self $attachment): void {
            if (! $attachment->path) {
                return;
            }

            $disk = Storage::disk($attachment->disk ?: 'local');
            if ($disk->exists($attachment->path)) {
                $disk->delete($attachment->path);
            }
        });
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'whatsapp_message_id');
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'size_bytes' => 'integer',
        ];
    }
}
