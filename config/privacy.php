<?php

return [
    // Bump when the privacy notice changes materially: every parent is asked to consent again.
    'version' => env('PRIVACY_VERSION', '2026-09'),

    // Shown on the privacy page (/adatvedelem). Fill these in before real families use the app.
    'controller' => env('PRIVACY_CONTROLLER', ''),
    'contact' => env('PRIVACY_CONTACT', ''),
];
