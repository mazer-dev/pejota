<?php

namespace App\Http\Controllers;

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
}
