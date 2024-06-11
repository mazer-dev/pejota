<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class AttachmentsController extends Controller
{
    public function getAttachment(string $module, int $companyId, string $fileName)
    {
        if (auth()->user()->company->id == $companyId) {
            $file = Storage::disk($module)->get($companyId.'/'.$fileName);

            return Storage::disk($module)->response($companyId.'/'.$fileName);
        } else {
            abort(404);
        }
    }
}
