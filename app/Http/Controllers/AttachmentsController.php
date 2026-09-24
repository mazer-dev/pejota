<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Support\Facades\Storage;

class AttachmentsController extends Controller
{
    private const SERVABLE_DISKS = [
        'tasks',
        'projects',
        'work_sessions',
        'companies',
        'companies-logo',
    ];

    public function getAttachment(string $module, int $companyId, string $fileName)
    {
        if (! in_array($module, self::SERVABLE_DISKS, true)) {
            abort(404);
        }

        if ($this->containsPathSeparator($fileName)) {
            abort(404);
        }

        $path = $companyId.'/'.$fileName;

        abort_unless(
            Company::whereKey($companyId)->first()?->hasMember(auth()->user())
                && Storage::disk($module)->exists($path),
            404
        );

        return Storage::disk($module)->response($path);
    }

    private function containsPathSeparator(string $fileName): bool
    {
        return str_contains($fileName, '/')
            || str_contains($fileName, '\\')
            || in_array($fileName, ['.', '..'], true);
    }
}
