<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use NunoMazer\Samehouse\BelongsToTenants;

class AssistantMessageAttachment extends Model
{
    use BelongsToTenants,
        HasFactory;

    public const STATUS_STORED = 'stored';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_ERROR = 'error';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::deleting(function (self $attachment): void {
            $attachment->deletePhysicalFile();
        });
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AssistantMessage::class, 'assistant_message_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf' || strtolower((string) $this->extension) === 'pdf';
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;

        if ($bytes <= 0) {
            return '0 KB';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }

    public function deletePhysicalFile(): void
    {
        if (! $this->path) {
            return;
        }

        $disk = Storage::disk($this->disk ?: 'local');

        if ($disk->exists($this->path)) {
            $disk->delete($this->path);
        }
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'page_count' => 'integer',
        ];
    }
}
