<?php

namespace App\Http\Controllers;

use App\Models\AssistantMessageAttachment;
use App\Models\WhatsappAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentsController extends Controller
{
    public function getAttachment(string $module, int $companyId, string $fileName)
    {
        if ($module == 'companies-logo') {
            return Storage::disk($module)->response($companyId.'/'.$fileName);
        }

        if (auth()->user()->company->id == $companyId) {
            return Storage::disk($module)->response($companyId.'/'.$fileName);
        }

        abort(404);
    }

    public function getWhatsappAttachment(WhatsappAttachment $attachment): BinaryFileResponse
    {
        if (auth()->user()?->company?->id !== $attachment->company_id) {
            abort(404);
        }

        $disk = Storage::disk($attachment->disk);

        if (! $attachment->path || ! $disk->exists($attachment->path)) {
            abort(404);
        }

        return response()->file($disk->path($attachment->path), [
            'Accept-Ranges' => 'bytes',
            'Content-Type' => $this->contentType($attachment),
        ]);
    }

    public function getAssistantAttachment(AssistantMessageAttachment $attachment): BinaryFileResponse
    {
        if (auth()->user()?->company?->id !== $attachment->company_id) {
            abort(404);
        }

        $disk = Storage::disk($attachment->disk ?: 'local');

        if (! $attachment->path || ! $disk->exists($attachment->path)) {
            abort(404);
        }

        $mimeType = str((string) $attachment->mime_type)->before(';')->trim()->toString();
        $mimeType = $mimeType !== '' ? $mimeType : 'application/octet-stream';

        $inline = $mimeType === 'application/pdf' || str_starts_with($mimeType, 'image/');
        $filename = str($attachment->original_filename ?: 'anexo')->ascii()->toString();

        return response()->file($disk->path($attachment->path), [
            'Accept-Ranges' => 'bytes',
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.addslashes($filename).'"',
        ]);
    }

    private function contentType(WhatsappAttachment $attachment): string
    {
        $mimeType = str((string) $attachment->mime_type)->before(';')->trim()->toString();

        return $mimeType !== '' ? $mimeType : 'application/octet-stream';
    }
}
