<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi(); // includes Sanctum's AuthenticateSession: a changed password ends older cookie sessions
        // Only a proxy we run (the tunnel, a load balancer on a private network) may tell us the visitor's address.
        // Trusting every sender let a forged X-Forwarded-For get around the sign-in and sign-up throttles.
        // Behind Cloudflare or another public proxy, set TRUSTED_PROXIES in the environment (comma-separated
        // addresses or ranges, or * if the app is only reachable through the proxy).
        $trusted = trim((string) (getenv('TRUSTED_PROXIES') ?: ''));
        $middleware->trustProxies(at: match (true) {
            $trusted === '*' => '*',
            $trusted !== '' => array_map('trim', explode(',', $trusted)),
            default => ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', 'fc00::/7'],
        });
        $middleware->alias(['idempotent' => \App\Http\Middleware\Idempotent::class]);
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
