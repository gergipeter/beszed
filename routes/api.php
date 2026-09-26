<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Admin\ContentController;
use App\Http\Controllers\Api\Admin\ContentImageController;
use App\Http\Controllers\Api\ChildController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Beszed\ShareController;
use Illuminate\Support\Facades\Route;

// Account-level API for the SPA (session cookie auth via Sanctum). Games live under /api/beszed.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', MeController::class)->name('me');
    Route::post('me/consent', [AccountController::class, 'consent'])->name('me.consent');
    Route::put('me/preferences', [AccountController::class, 'preferences'])->name('me.preferences');
    Route::get('me/export', [AccountController::class, 'export'])->middleware('throttle:10,1')->name('me.export');
    Route::delete('me', [AccountController::class, 'destroy'])->name('me.destroy');

    Route::apiResource('children', ChildController::class)->only(['index', 'store', 'update', 'destroy']);
});

// Content editor, for the parents listed in ADMIN_EMAILS.
Route::middleware(['auth:sanctum', 'can:edit-content'])->prefix('admin/content')->name('admin.content.')->group(function () {
    Route::get('/', [ContentController::class, 'games'])->name('games');
    Route::post('images', [ContentImageController::class, 'store'])->name('images.store');
    Route::get('{game}', [ContentController::class, 'index'])->name('index');
    Route::post('{game}', [ContentController::class, 'store'])->name('store');
    Route::put('{game}/{item}', [ContentController::class, 'update'])->name('update');
    Route::delete('{game}/{item}', [ContentController::class, 'destroy'])->name('destroy');
});

// Public: serve uploaded content images.
Route::get('content-images/{id}', \App\Http\Controllers\ContentImageController::class)->name('content-images.show');

// The read-only report behind a therapist's share link: no sign-in, just the token.
Route::get('share/{token}', [ShareController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:30,1')->name('share.show');
