<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\ChildController;
use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;

// Account-level API for the SPA (session cookie auth via Sanctum). Games live under /api/beszed.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', MeController::class)->name('me');
    Route::post('me/consent', [AccountController::class, 'consent'])->name('me.consent');
    Route::get('me/export', [AccountController::class, 'export'])->middleware('throttle:10,1')->name('me.export');
    Route::delete('me', [AccountController::class, 'destroy'])->name('me.destroy');

    Route::apiResource('children', ChildController::class)->only(['index', 'store', 'update', 'destroy']);
});
