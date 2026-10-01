<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\DemoLoginController;
use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Serves the Vue app for every page URL, with the sign-in options it may offer. */
class SpaController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $response = response()->view('app', ['appConfig' => [
            'name' => config('app.name'),
            'auth' => [
                'google' => GoogleController::enabled(),
                'email' => true,
                'demo' => DemoLoginController::enabled(),
            ],
            'privacy' => [
                'version' => config('privacy.version'),
                'controller' => config('privacy.controller'),
                'contact' => config('privacy.contact'),
                // which speech services are on, so the notice only names the ones that receive data
                'stt' => config('stt.driver'),
                'tts' => config('tts.driver'),
            ],
        ]]);

        // A therapist's share link: the token is in the URL, so never pass it on or index it.
        if ($request->is('megosztas/*')) {
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }
}
