<?php

return [
    // Bump when the privacy notice changes materially: every parent is asked to consent again.
    'version' => env('PRIVACY_VERSION', '2026-09'),

    // Shown on the privacy page (/adatvedelem). Fill these in before real families use the app.
    // PLACEHOLDERS until the publisher is decided: set PRIVACY_CONTROLLER / PRIVACY_CONTACT before submitting to the
    // App Store (`php artisan beszed:preflight` flags them while they are here).
    'controller' => env('PRIVACY_CONTROLLER', '[Adatkezelő neve és címe – kitöltendő]'),
    'contact' => env('PRIVACY_CONTACT', '[kapcsolattartó e-mail-cím – kitöltendő]'),
];
