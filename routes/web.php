<?php

use App\Http\Controllers\AttachmentsController;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::get(
    '/attachments/{module}/{companyId}/{fileName}',
    [AttachmentsController::class, 'getAttachment']
)
    ->name('attachments.get')
    ->middleware(Authenticate::class);
