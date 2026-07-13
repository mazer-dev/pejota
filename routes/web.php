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

Route::get(
    '/whatsapp-attachments/{attachment}',
    [AttachmentsController::class, 'getWhatsappAttachment']
)
    ->name('whatsapp.attachments.show')
    ->middleware(Authenticate::class);

Route::get(
    '/assistant-attachments/{attachment}',
    [AttachmentsController::class, 'getAssistantAttachment']
)
    ->name('assistant.attachments.show')
    ->middleware(Authenticate::class);

Route::redirect('/', '/app');

Route::post('/webhooks/evolution', EvolutionWebhookController::class)
    ->name('webhooks.evolution');
