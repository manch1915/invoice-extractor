<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DocumentApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/documents', [DocumentApiController::class, 'store'])->name('api.documents.store');
    Route::get('/documents/{document}', [DocumentApiController::class, 'show'])->name('api.documents.show');
});
