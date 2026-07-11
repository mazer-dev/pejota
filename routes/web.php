<?php

use App\Http\Controllers\AttachmentsController;
use App\Livewire\AcceptInvitation;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::get(
    '/attachments/{module}/{companyId}/{fileName}',
    [AttachmentsController::class, 'getAttachment']
)
    ->name('attachments.get')
    ->middleware(Authenticate::class);

Route::get('/invite/{token}', AcceptInvitation::class)
    ->name('invitations.accept');
