<?php

return [
    // azure | null  (null = no server assessment, the parent judges the attempt)
    'driver' => env('STT_DRIVER', 'null'),

    'azure' => [
        'key' => env('AZURE_SPEECH_KEY'),
        'region' => env('AZURE_SPEECH_REGION', 'westeurope'),
        'language' => env('AZURE_SPEECH_STT_LANGUAGE', 'hu-HU'),
        'audio_format' => 'webm',
    ],
];
