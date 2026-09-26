<?php

use App\Http\Controllers\Beszed\AttemptController;
use App\Http\Controllers\Beszed\DailyPathController;
use App\Http\Controllers\Beszed\MetaController;
use App\Http\Controllers\Beszed\ProgressController;
use App\Http\Controllers\Beszed\PronunciationController;
use App\Http\Controllers\Beszed\RecordingController;
use App\Http\Controllers\Beszed\RewardController;
use App\Http\Controllers\Beszed\SessionController;
use App\Http\Controllers\Beszed\ShareController;
use App\Http\Controllers\Beszed\SpotlightController;
use App\Http\Controllers\Beszed\TtsController;
use App\Http\Controllers\Beszed\VoiceSettingsController;
use Illuminate\Support\Facades\Route;

// Loaded by BeszedServiceProvider under /api/beszed with auth:sanctum.
Route::name('beszed.')->group(function () {
    Route::get('meta', MetaController::class)->name('meta');

    Route::get('children/{child}/session', [SessionController::class, 'show'])->name('session');
    Route::post('children/{child}/attempts', [AttemptController::class, 'store'])->name('attempts.store');
    Route::get('children/{child}/progress', [ProgressController::class, 'show'])->name('progress');
    Route::get('children/{child}/progress/history', [ProgressController::class, 'history'])->name('progress.history');
    Route::get('children/{child}/shares', [ShareController::class, 'index'])->name('shares.index');
    Route::post('children/{child}/shares', [ShareController::class, 'store'])->middleware('throttle:10,1,shares')->name('shares.store');
    Route::delete('children/{child}/shares/{share}', [ShareController::class, 'destroy'])->name('shares.destroy');
    Route::get('children/{child}/daily-path', [DailyPathController::class, 'show'])->name('daily-path');
    Route::get('children/{child}/spotlight', [SpotlightController::class, 'show'])->name('spotlight');

    Route::get('children/{child}/rewards', [RewardController::class, 'show'])->name('rewards');
    Route::post('children/{child}/sessions', [RewardController::class, 'store'])->middleware('throttle:60,1,sessions')->name('sessions.store');
    Route::put('children/{child}/profile', [RewardController::class, 'wear'])->name('profile');
    Route::put('children/{child}/scene', [RewardController::class, 'scene'])->name('scene');

    Route::get('tts', TtsController::class)->middleware('throttle:120,1,tts')->name('tts');
    Route::post('pronunciation', PronunciationController::class)->middleware('throttle:30,1,pronunciation')->name('pronunciation');

    Route::get('voice-settings', [VoiceSettingsController::class, 'show'])->name('voice-settings.show');
    Route::put('voice-settings', [VoiceSettingsController::class, 'update'])->name('voice-settings.update');

    Route::get('recordings', [RecordingController::class, 'index'])->name('recordings.index');
    Route::post('recordings', [RecordingController::class, 'store'])->middleware('throttle:30,1,recordings')->name('recordings.store');
    Route::get('recordings/{lineKey}/audio', [RecordingController::class, 'audio'])->name('recordings.audio');
    Route::delete('recordings/{lineKey}', [RecordingController::class, 'destroy'])->name('recordings.destroy');
});
