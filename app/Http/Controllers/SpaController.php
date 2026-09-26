<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\DemoLoginController;
use App\Http\Controllers\Auth\GoogleController;
use Illuminate\View\View;

/** Serves the Vue app for every page URL, with the sign-in options it may offer. */
class SpaController extends Controller
{
    public function __invoke(): View
    {
        return view('app', ['appConfig' => [
            'name' => config('app.name'),
            'auth' => [
                'google' => GoogleController::enabled(),
                'demo' => DemoLoginController::enabled(),
            ],
            'privacy' => [
                'version' => config('privacy.version'),
                'controller' => config('privacy.controller'),
                'contact' => config('privacy.contact'),
            ],
        ]]);
    }
}
