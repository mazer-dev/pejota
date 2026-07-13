<?php

namespace App\Services\Ai;

use App\Models\AssistantMessage;
use App\Models\AssistantMessageAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Validates and persists chat attachments.
 *
 * The extension the browser reports is only ever used to pick which real
 * MIME types are acceptable; the definitive on-disk name is always a fresh
 * UUID, and the actual bytes are inspected with fileinfo before anything is
 * written to permanent storage. The final file must exist on disk before a
 * queue job is dispatched — a Livewire TemporaryUploadedFile cannot survive
 * serialization onto the queue.
 */
class AssistantAttachmentUploader
{
    /**
     * @var array<string, array<int, string>>
     */
    private const EXTENSION_MIMES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
        ],
        'csv' => ['text/csv', 'text/plain'],
        'txt' => ['text/plain'],
    ];

    public function isEnabled(): bool
    {
        return (bool) config('services.assistant.attachments.enabled', true);
    }

    public function maxFiles(): int
    {
        return max(1, (int) config('services.assistant.attachments.max_files', 3));
    }

    public function maxFileBytes(): int
    {
        return max(1, (int) config('services.assistant.attachments.max_file_mb', 25)) * 1024 * 1024;
    }

    /**
     * @return array<int, string>
     */
    public function allowedExtensions(): array
    {
        return array_keys(self::EXTENSION_MIMES);
    }

    /**
     * Validates quantity, size, extension allowlist and real (fileinfo)
     * MIME type. Throws with a Portuguese, user-facing message on failure.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function assertBatchIsValid(array $files): void
    {
        if ($files === []) {
            return;
        }

        if (! $this->isEnabled()) {
            throw new InvalidArgumentException('O envio de anexos está desabilitado no momento.');
        }

        if (count($files) > $this->maxFiles()) {
            throw new InvalidArgumentException("Você pode enviar no máximo {$this->maxFiles()} arquivos por mensagem.");
        }

        foreach ($files as $file) {
            $this->assertValid($file);
        }
    }

    public function assertValid(UploadedFile $file): void
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! array_key_exists($extension, self::EXTENSION_MIMES)) {
            throw new InvalidArgumentException("Tipo de arquivo não permitido: .{$extension}.");
        }

        if ($file->getSize() > $this->maxFileBytes()) {
            $maxMb = (int) config('services.assistant.attachments.max_file_mb', 25);
            throw new InvalidArgumentException("\"{$file->getClientOriginalName()}\" excede o limite de {$maxMb} MB por arquivo.");
        }

        $realMime = $this->detectRealMimeType($file->getRealPath());
        $allowedMimes = self::EXTENSION_MIMES[$extension];

        if (! in_array($realMime, $allowedMimes, true)) {
            throw new InvalidArgumentException("\"{$file->getClientOriginalName()}\" não corresponde ao tipo esperado pela extensão.");
        }
    }

    public function detectRealMimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        try {
            $mime = $finfo !== false ? finfo_file($finfo, $path) : false;
        } finally {
            if ($finfo !== false) {
                finfo_close($finfo);
            }
        }

        return is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';
    }

    /**
     * Moves the temporary upload into definitive private storage
     * (`assistant/{company_id}/{conversation_id}/{message_id}/{uuid}.{ext}`
     * on the `local` disk) and creates the persisted attachment record.
     * Must be called synchronously in the request, before the message is
     * dispatched to the queue.
     */
    public function persist(UploadedFile $file, AssistantMessage $message): AssistantMessageAttachment
    {
        $this->assertValid($file);

        return $this->persistFromPath(
            (string) $file->getRealPath(),
            $file->getClientOriginalName(),
            $message,
        );
    }

    /**
     * Persists an already-on-disk file (e.g. decoded from a WhatsApp
     * webhook) as a definitive attachment. Runs the same extension/size/
     * real-MIME validation as browser uploads — the extension comes from
     * the provided original filename, never from the temp path.
     */
    public function persistFromPath(string $absolutePath, string $originalFilename, AssistantMessage $message): AssistantMessageAttachment
    {
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        if (! array_key_exists($extension, self::EXTENSION_MIMES)) {
            throw new InvalidArgumentException("Tipo de arquivo não permitido: .{$extension}.");
        }

        if (! is_file($absolutePath)) {
            throw new InvalidArgumentException("Arquivo não encontrado: \"{$originalFilename}\".");
        }

        $sizeBytes = (int) filesize($absolutePath);

        if ($sizeBytes > $this->maxFileBytes()) {
            $maxMb = (int) config('services.assistant.attachments.max_file_mb', 25);
            throw new InvalidArgumentException("\"{$originalFilename}\" excede o limite de {$maxMb} MB por arquivo.");
        }

        $realMime = $this->detectRealMimeType($absolutePath);

        if (! in_array($realMime, self::EXTENSION_MIMES[$extension], true)) {
            throw new InvalidArgumentException("\"{$originalFilename}\" não corresponde ao tipo esperado pela extensão.");
        }

        $disk = Storage::disk('local');
        $directory = "assistant/{$message->company_id}/{$message->assistant_conversation_id}/{$message->id}";
        $path = $directory.'/'.Str::uuid()->toString().'.'.$extension;

        $stream = fopen($absolutePath, 'r');

        try {
            $disk->put($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $message->attachments()->create([
            'company_id' => $message->company_id,
            'disk' => 'local',
            'path' => $path,
            'original_filename' => $originalFilename,
            'mime_type' => $realMime,
            'extension' => $extension,
            'size_bytes' => $sizeBytes,
            'sha256' => hash_file('sha256', $absolutePath),
            'status' => AssistantMessageAttachment::STATUS_STORED,
        ]);
    }
}
