<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Admin\ContentController;
use App\Http\Controllers\Api\Admin\ContentImageController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ChildController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\RevenueCatWebhookController;
use App\Http\Controllers\Auth\EmailAuthController;
use App\Http\Controllers\Auth\TokenAuthController;
use App\Http\Controllers\Beszed\ShareController;
use App\Http\Controllers\LanguageController;
use Illuminate\Support\Facades\Route;

// Sign-in of the iOS / Android app: the same sign-up and password sign-in as the web, answered with a Bearer token
// (the app runs on its own origin, where the cookie session cannot). Same throttles as the web routes.
Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('register', [TokenAuthController::class, 'register'])->middleware('throttle:10,1,register')->name('register');
    Route::post('login', [TokenAuthController::class, 'login'])->middleware('throttle:30,1,login')->name('login');
    Route::post('forgot-password', [EmailAuthController::class, 'forgot'])->middleware('throttle:5,1,forgot')->name('forgot');
    Route::post('reset-password', [EmailAuthController::class, 'reset'])->middleware('throttle:10,1,reset')->name('reset');
    Route::delete('token', [TokenAuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
});

// RevenueCat → us (no session; the shared secret in the Authorization header is the key)
Route::post('webhooks/revenuecat', RevenueCatWebhookController::class)->middleware('throttle:120,1,revenuecat')->name('webhooks.revenuecat');

// Account-level API for the SPA (session cookie auth via Sanctum). Games live under /api/beszed.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', MeController::class)->name('me');
    Route::post('me/consent', [AccountController::class, 'consent'])->name('me.consent');
    Route::put('me/preferences', [AccountController::class, 'preferences'])->name('me.preferences');
    // "Küldj most egy mintát": this week's report e-mailed right away
    Route::post('me/weekly-report', [AccountController::class, 'weeklyReportSample'])->middleware('throttle:3,10,weekly-sample')->name('me.weekly-report');
    Route::get('me/export', [AccountController::class, 'export'])->middleware('throttle:10,1,export')->name('me.export');
    Route::delete('me', [AccountController::class, 'destroy'])->name('me.destroy');

    // In-app subscription (Apple In-App Purchase / Google Play Billing through RevenueCat)
    Route::get('billing', [BillingController::class, 'show'])->name('billing.show');
    Route::post('billing/sync', [BillingController::class, 'sync'])->middleware('throttle:20,1,billing-sync')->name('billing.sync');

    Route::apiResource('children', ChildController::class)->only(['index', 'store', 'update', 'destroy']);

    // Pet Tamagotchi API
    Route::prefix('beszed/children/{child}')->name('beszed.pet.')->group(function () {
        Route::get('pet', [\App\Http\Controllers\Beszed\PetController::class, 'show'])->name('show');
        Route::post('pet', [\App\Http\Controllers\Beszed\PetController::class, 'store'])->name('store');
        Route::post('pet/feed', [\App\Http\Controllers\Beszed\PetController::class, 'feed'])->name('feed');
        Route::post('pet/play', [\App\Http\Controllers\Beszed\PetController::class, 'play'])->name('play');
        Route::post('pet/clean', [\App\Http\Controllers\Beszed\PetController::class, 'clean'])->name('clean');
        Route::post('pet/sleep', [\App\Http\Controllers\Beszed\PetController::class, 'sleep'])->name('sleep');
        Route::get('pet/history', [\App\Http\Controllers\Beszed\PetController::class, 'history'])->name('history');
    });
});

// Content editor, for the parents listed in ADMIN_EMAILS.
Route::middleware(['auth:sanctum', 'can:edit-content'])->prefix('admin/content')->name('admin.content.')->group(function () {
    Route::get('/', [ContentController::class, 'games'])->name('games');
    Route::post('images', [ContentImageController::class, 'store'])->name('images.store');
    Route::get('{game}/export', [ContentController::class, 'export'])->name('export');
    Route::post('{game}/import', [ContentController::class, 'import'])->name('import');
    Route::post('{game}/bulk', [ContentController::class, 'bulk'])->name('bulk');
    Route::get('{game}', [ContentController::class, 'index'])->name('index');
    Route::post('{game}', [ContentController::class, 'store'])->name('store');
    Route::put('{game}/{item}', [ContentController::class, 'update'])->name('update');
    Route::delete('{game}/{item}', [ContentController::class, 'destroy'])->name('destroy');
});

// Public: serve uploaded content images.
Route::get('content-images/{id}', \App\Http\Controllers\ContentImageController::class)->name('content-images.show');

// The read-only report behind a therapist's share link: no sign-in, just the token.
Route::get('share/{token}', [ShareController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:30,1,share-view')->name('share.show');

// Language API (no auth required)
Route::prefix('language')->name('language.')->group(function () {
    Route::get('/', [LanguageController::class, 'index'])->name('index');
    Route::get('current', [LanguageController::class, 'current'])->name('current');
    Route::post('switch', [LanguageController::class, 'switch'])->name('switch');
    Route::get('{language}/translations', [LanguageController::class, 'getTranslations'])->name('translations');
});

// Admin: Add new language
Route::post('language/add', [LanguageController::class, 'add'])->middleware('auth:sanctum')->name('language.add');
