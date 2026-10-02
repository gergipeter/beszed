<?php

use App\Http\Controllers\Beszed\WeatherController;
use Illuminate\Support\Facades\Route;

// Loaded by WeatherServiceProvider under /api/beszed with auth:sanctum.
Route::get('weather', WeatherController::class)->middleware('throttle:30,1,weather')->name('beszed.weather');
