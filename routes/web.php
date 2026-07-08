<?php

use App\Http\Controllers\AttachmentsController;
use App\Http\Controllers\Webhooks\EvolutionWebhookController;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::get(
    '/attachments/{module}/{companyId}/{fileName}',
    [AttachmentsController::class, 'getAttachment']
)
    ->name('attachments.get')
    ->middleware(Authenticate::class);

Route::redirect('/', '/app');

Route::post('/webhooks/evolution', EvolutionWebhookController::class)
    ->name('webhooks.evolution');
