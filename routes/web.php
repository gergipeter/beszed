<?php

use App\Http\Controllers\Auth\DemoLoginController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\PictogramController;
use App\Http\Controllers\SpaController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
Route::post('/auth/demo', DemoLoginController::class)->middleware('throttle:10,1,demo')->name('auth.demo');
Route::post('/logout', LogoutController::class)->name('logout');

// ARASAAC pictograms, from this server's copy (fetched once). Plain files: no session, no cookies.
Route::get('/pictograms/{id}.png', PictogramController::class)
    ->where('id', '[0-9]{1,6}')
    ->middleware('throttle:600,1,pictograms')
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, AddQueuedCookiesToResponse::class, EncryptCookies::class, ValidateCsrfToken::class])
    ->name('pictogram');

// Every other page URL is the Vue app; it decides between sign-in and the games.
Route::get('/login', SpaController::class)->name('login');
Route::get('/{path?}', SpaController::class)->where('path', '^(?!api/|auth/|up$|build/|storage/|pictograms/).*')->name('spa');
