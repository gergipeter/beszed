<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Admin\ContentController;
use App\Http\Controllers\Api\Admin\ContentImageController;
use App\Http\Controllers\Api\ChildController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Beszed\ShareController;
use App\Http\Controllers\LanguageController;
use Illuminate\Support\Facades\Route;

// Account-level API for the SPA (session cookie auth via Sanctum). Games live under /api/beszed.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', MeController::class)->name('me');
    Route::post('me/consent', [AccountController::class, 'consent'])->name('me.consent');
    Route::put('me/preferences', [AccountController::class, 'preferences'])->name('me.preferences');
    // "Küldj most egy mintát": this week's report e-mailed right away
    Route::post('me/weekly-report', [AccountController::class, 'weeklyReportSample'])->middleware('throttle:3,10,weekly-sample')->name('me.weekly-report');
    Route::get('me/export', [AccountController::class, 'export'])->middleware('throttle:10,1,export')->name('me.export');
    Route::delete('me', [AccountController::class, 'destroy'])->name('me.destroy');

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

    // Speech Analysis API
    Route::prefix('beszed/children/{child}/speech')->name('beszed.speech.')->group(function () {
        Route::post('analyze', [\App\Http\Controllers\Beszed\SpeechController::class, 'analyze'])->name('analyze');
        Route::get('history', [\App\Http\Controllers\Beszed\SpeechController::class, 'history'])->name('history');
        Route::get('{recording}', [\App\Http\Controllers\Beszed\SpeechController::class, 'show'])->name('show');
        Route::get('phonemes/progress', [\App\Http\Controllers\Beszed\SpeechController::class, 'phonemeProgress'])->name('phoneme-progress');
        Route::get('trends', [\App\Http\Controllers\Beszed\SpeechController::class, 'trends'])->name('trends');
    });

    // Adaptive Learning API
    Route::prefix('beszed/children/{child}/adaptive')->name('beszed.adaptive.')->group(function () {
        Route::get('recommendations', [\App\Http\Controllers\Beszed\AdaptiveController::class, 'recommendGames'])->name('recommendations');
        Route::get('games/{game}/skill-level', [\App\Http\Controllers\Beszed\AdaptiveController::class, 'gameSkillLevel'])->name('game-skill');
        Route::get('predict/pronunciation', [\App\Http\Controllers\Beszed\AdaptiveController::class, 'predictPronunciation'])->name('predict-pronunciation');
        Route::get('predict/fluency', [\App\Http\Controllers\Beszed\AdaptiveController::class, 'predictFluency'])->name('predict-fluency');
        Route::get('predict/games/{game}', [\App\Http\Controllers\Beszed\AdaptiveController::class, 'predictGameProgress'])->name('predict-game');
        Route::get('summary', [\App\Http\Controllers\Beszed\AdaptiveController::class, 'progressSummary'])->name('summary');
    });

    // Dashboard & Reports API (Phase 3)
    Route::prefix('beszed/children/{child}/dashboard')->name('beszed.dashboard.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Beszed\DashboardController::class, 'parentDashboard'])->name('parent');
        Route::get('reports/weekly', [\App\Http\Controllers\Beszed\DashboardController::class, 'weeklyReport'])->name('weekly-report');
        Route::get('reports/monthly', [\App\Http\Controllers\Beszed\DashboardController::class, 'monthlyReport'])->name('monthly-report');
        Route::get('reports/therapist-note', [\App\Http\Controllers\Beszed\DashboardController::class, 'therapistNote'])->name('therapist-note');
    });

    Route::get('beszed/therapist/dashboard', [\App\Http\Controllers\Beszed\DashboardController::class, 'therapistDashboard'])->name('beszed.dashboard.therapist');

    // Gamification API (Phase 4)
    Route::prefix('beszed/children/{child}/gamification')->name('beszed.gamification.')->group(function () {
        Route::get('leaderboard/family', [\App\Http\Controllers\Beszed\GamificationController::class, 'familyLeaderboard'])->name('family-leaderboard');
        Route::get('leaderboard/classroom', [\App\Http\Controllers\Beszed\GamificationController::class, 'classroomLeaderboard'])->name('classroom-leaderboard');
        Route::get('leaderboard/regional', [\App\Http\Controllers\Beszed\GamificationController::class, 'regionalLeaderboard'])->name('regional-leaderboard');
        Route::get('achievements', [\App\Http\Controllers\Beszed\GamificationController::class, 'achievements'])->name('achievements');
        Route::post('achievements/check', [\App\Http\Controllers\Beszed\GamificationController::class, 'checkAchievements'])->name('check-achievements');
        Route::post('score/update', [\App\Http\Controllers\Beszed\GamificationController::class, 'updateScore'])->name('update-score');
    });

    // Enterprise & Healthcare API (Phase 5)
    Route::prefix('beszed/children/{child}/enterprise')->name('beszed.enterprise.')->group(function () {
        Route::get('fhir/json', [\App\Http\Controllers\Beszed\EnterpriseController::class, 'exportFHIRJson'])->name('fhir-json');
        Route::get('fhir/xml', [\App\Http\Controllers\Beszed\EnterpriseController::class, 'exportFHIRXml'])->name('fhir-xml');
        Route::get('encryption/keys', [\App\Http\Controllers\Beszed\EnterpriseController::class, 'getEncryptionKeys'])->name('encryption-keys');
        Route::get('compliance/audit-trail', [\App\Http\Controllers\Beszed\EnterpriseController::class, 'getAuditTrail'])->name('audit-trail');
        Route::post('compliance/dsar', [\App\Http\Controllers\Beszed\EnterpriseController::class, 'generateDSARReport'])->name('dsar-report');
        Route::get('compliance/validate', [\App\Http\Controllers\Beszed\EnterpriseController::class, 'validateCompliance'])->name('validate-compliance');
        Route::post('deletion-request', [\App\Http\Controllers\Beszed\EnterpriseController::class, 'requestDeletion'])->name('deletion-request');
        Route::get('parental-controls', [\App\Http\Controllers\Beszed\EnterpriseController::class, 'getParentalControls'])->name('parental-controls');
        Route::post('parental-controls', [\App\Http\Controllers\Beszed\EnterpriseController::class, 'updateParentalControls'])->name('update-parental-controls');
        Route::post('notifications/send', [\App\Http\Controllers\Beszed\EnterpriseController::class, 'sendNotification'])->name('send-notification');
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
    Route::get('{game}/{item}/history', [ContentController::class, 'history'])->name('history');
    Route::put('{game}/{item}', [ContentController::class, 'update'])->name('update');
    Route::delete('{game}/{item}', [ContentController::class, 'destroy'])->name('destroy');
});

// Public: serve uploaded content images.
Route::get('content-images/{id}', \App\Http\Controllers\ContentImageController::class)->name('content-images.show');

// Public API: populate game content via API (no auth required).
Route::prefix('content')->name('content.')->group(function () {
    Route::get('{game}', [\App\Http\Controllers\Api\Content\GameContentController::class, 'index'])->name('index');
    Route::post('{game}', [\App\Http\Controllers\Api\Content\GameContentController::class, 'store'])->name('store');
    Route::post('{game}/bulk', [\App\Http\Controllers\Api\Content\GameContentController::class, 'bulk'])->name('bulk');
    Route::put('{game}/{id}', [\App\Http\Controllers\Api\Content\GameContentController::class, 'update'])->name('update');
    Route::delete('{game}/{id}', [\App\Http\Controllers\Api\Content\GameContentController::class, 'destroy'])->name('destroy');
});

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
