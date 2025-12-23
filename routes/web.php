<?php

use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return redirect('/admin');   // if Filament admin is used
//     // or: return 'App is running ✅';
// }); 
Route::get(
    '/attachments/{module}/{companyId}/{fileName}',
    [App\Http\Controllers\AttachmentsController::class, 'getAttachment']
)
    ->name('attachments.get')
    ->middleware(\Illuminate\Auth\Middleware\Authenticate::class);
