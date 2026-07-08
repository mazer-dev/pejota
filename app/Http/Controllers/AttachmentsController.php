<?php

namespace App\Http\Controllers;

use App\Models\WhatsappAttachment;
use Illuminate\Support\Facades\Storage;

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

    public function getWhatsappAttachment(WhatsappAttachment $attachment)
    {
        if (auth()->user()?->company?->id !== $attachment->company_id) {
            abort(404);
        }

        if (! $attachment->path || ! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_filename
        );
    }
}
