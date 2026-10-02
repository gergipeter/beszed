<?php

/*
| The web app is served by this server itself (same origin), so it needs no CORS. The iOS / Android app is the web
| app bundled in a Capacitor shell and talks to this API from its own origin, signed in with a Bearer token
| (App\Http\Controllers\Auth\TokenAuthController). No cookies cross origins: `supports_credentials` stays off.
*/
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // The app shell's own origin: capacitor.config sets the host to app.beszed.local (capacitor://… on iOS, https://… on
    // Android), plus Capacitor's default localhost for development. Add more with a comma.
    // Not "localhost" for the shipped app: Sanctum's default stateful list contains it, which would make Sanctum treat an
    // Android https://localhost origin as the cookie web app and ask for a CSRF token on every token request.
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'capacitor://app.beszed.local,https://app.beszed.local,capacitor://localhost,https://localhost,http://localhost',
    ))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With', 'Idempotency-Key'],

    'exposed_headers' => ['Idempotent-Replayed', 'Retry-After'],

    'max_age' => 600,

    'supports_credentials' => false,
];
