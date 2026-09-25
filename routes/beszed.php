<?php

use App\Http\Controllers\Beszed\AttemptController;
use App\Http\Controllers\Beszed\MetaController;
use App\Http\Controllers\Beszed\ProgressController;
use App\Http\Controllers\Beszed\RecordingController;
use App\Http\Controllers\Beszed\SessionController;
use App\Http\Controllers\Beszed\TtsController;
use Illuminate\Support\Facades\Route;

// Loaded by BeszedServiceProvider under /api/beszed with auth:sanctum.
Route::name('beszed.')->group(function () {
    Route::get('meta', MetaController::class)->name('meta');

    Route::get('children/{child}/session', [SessionController::class, 'show'])->name('session');
    Route::post('children/{child}/attempts', [AttemptController::class, 'store'])->name('attempts.store');
    Route::get('children/{child}/progress', [ProgressController::class, 'show'])->name('progress');

    Route::get('tts', TtsController::class)->middleware('throttle:120,1')->name('tts');

    Route::get('recordings', [RecordingController::class, 'index'])->name('recordings.index');
    Route::post('recordings', [RecordingController::class, 'store'])->middleware('throttle:30,1')->name('recordings.store');
    Route::get('recordings/{lineKey}/audio', [RecordingController::class, 'audio'])->name('recordings.audio');
    Route::delete('recordings/{lineKey}', [RecordingController::class, 'destroy'])->name('recordings.destroy');
});
