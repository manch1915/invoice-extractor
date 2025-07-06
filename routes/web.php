<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::post('/locale', LocaleController::class)->name('locale');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:10,1');
    Route::post('/demo/{role}', [SessionController::class, 'demo'])->name('demo.login')->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store')->middleware('throttle:30,1');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/original', [DocumentController::class, 'original'])->name('documents.original');
    Route::get('/documents/{document}/pages/{number}', [DocumentController::class, 'page'])->whereNumber('number')->name('documents.page');
    Route::post('/documents/{document}/reprocess', [DocumentController::class, 'reprocess'])->name('documents.reprocess');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::controller(ReviewController::class)->prefix('/documents/{document}')->name('review.')->group(function () {
        Route::patch('/fields/{key}', 'updateField')->name('field');
        Route::post('/fields/{key}/confirm', 'confirmField')->name('confirm');
        Route::put('/category', 'setCategory')->name('category');
        Route::post('/issues/{issue}/resolve', 'resolveIssue')->name('resolve');
        Route::post('/approve', 'approve')->name('approve');
        Route::post('/reject', 'reject')->name('reject');
        Route::post('/reopen', 'reopen')->name('reopen');
    });

    Route::get('/exports', [ExportController::class, 'index'])->name('exports.index');
    Route::post('/exports', [ExportController::class, 'store'])->name('exports.store')->middleware('throttle:20,1');
    Route::get('/exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::post('/rules', [CategoryController::class, 'storeRule'])->name('rules.store');
    Route::patch('/rules/{rule}', [CategoryController::class, 'updateRule'])->name('rules.update');
    Route::delete('/rules/{rule}', [CategoryController::class, 'destroyRule'])->name('rules.destroy');

    Route::get('/activity', ActivityController::class)->name('activity');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings/tokens', [SettingsController::class, 'createToken'])->name('tokens.store')->middleware('throttle:10,1');
    Route::delete('/settings/tokens/{token}', [SettingsController::class, 'deleteToken'])->name('tokens.destroy');

    Route::get('/integrations/{provider}/connect', [IntegrationController::class, 'connect'])->name('integrations.connect');
    Route::get('/integrations/{provider}/callback', [IntegrationController::class, 'callback'])->name('integrations.callback');
    Route::delete('/integrations/{provider}', [IntegrationController::class, 'disconnect'])->name('integrations.disconnect');
});
