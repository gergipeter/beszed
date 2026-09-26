<?php

use App\Http\Controllers\Beszed\AttemptController;
use App\Http\Controllers\Beszed\MetaController;
use App\Http\Controllers\Beszed\ProgressController;
use App\Http\Controllers\Beszed\PronunciationController;
use App\Http\Controllers\Beszed\RecordingController;
use App\Http\Controllers\Beszed\RewardController;
use App\Http\Controllers\Beszed\SessionController;
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

    Route::get('children/{child}/rewards', [RewardController::class, 'show'])->name('rewards');
    Route::post('children/{child}/sessions', [RewardController::class, 'store'])->middleware('throttle:60,1')->name('sessions.store');
    Route::put('children/{child}/profile', [RewardController::class, 'wear'])->name('profile');

    Route::get('tts', TtsController::class)->middleware('throttle:120,1')->name('tts');
    Route::post('pronunciation', PronunciationController::class)->middleware('throttle:30,1')->name('pronunciation');

    Route::get('voice-settings', [VoiceSettingsController::class, 'show'])->name('voice-settings.show');
    Route::put('voice-settings', [VoiceSettingsController::class, 'update'])->name('voice-settings.update');

    Route::get('recordings', [RecordingController::class, 'index'])->name('recordings.index');
    Route::post('recordings', [RecordingController::class, 'store'])->middleware('throttle:30,1')->name('recordings.store');
    Route::get('recordings/{lineKey}/audio', [RecordingController::class, 'audio'])->name('recordings.audio');
    Route::delete('recordings/{lineKey}', [RecordingController::class, 'destroy'])->name('recordings.destroy');
});
