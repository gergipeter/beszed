<?php

return [
    // Bump when the privacy notice changes materially: every parent is asked to consent again.
    'version' => env('PRIVACY_VERSION', '2026-10'),

    // Shown on the privacy page (/adatvedelem). Fill these in before real families use the app.
    // PLACEHOLDERS until the publisher is decided: set PRIVACY_CONTROLLER / PRIVACY_CONTACT before submitting to the
    // App Store (`php artisan beszed:preflight` flags them while they are here).
    'controller' => env('PRIVACY_CONTROLLER', '[Adatkezelő neve és címe – kitöltendő]'),
    'contact' => env('PRIVACY_CONTACT', '[kapcsolattartó e-mail-cím – kitöltendő]'),

    // The imprint (/impresszum) and the consumer terms name the publisher and the services it runs on. Fill in before real
    // families use the app; `php artisan beszed:preflight` flags the placeholders.
    'registration' => env('PRIVACY_REGISTRATION', '[cégjegyzékszám vagy egyéni vállalkozói nyilvántartási szám – kitöltendő]'),
    'tax_id' => env('PRIVACY_TAX_ID', '[adószám – kitöltendő]'),
    'hosting' => env('PRIVACY_HOSTING', '[tárhelyszolgáltató neve, címe, e-mail-címe – kitöltendő]'),
    // the consumer conciliation body of the publisher's county (békéltető testület)
    'conciliation' => env('PRIVACY_CONCILIATION', '[az illetékes békéltető testület neve, címe, e-mail-címe – kitöltendő]'),
];
