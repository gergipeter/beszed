<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

Route::get('/{path?}', function () {
    if (app()->isLocal()) {
        Auth::login(User::query()->where('email', 'parent@example.test')->firstOrFail());
    }

    return view('app');
})->where('path', '.*');
