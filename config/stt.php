<?php

return [
    // whisper | azure | null  (null = no server assessment, the parent judges the attempt)
    'driver' => env('STT_DRIVER', 'null'),

    // Self-hosted Whisper large-v3 behind an OpenAI-compatible server: the child's voice stays on our server.
    'whisper' => [
        'url' => env('WHISPER_URL', 'http://whisper:8000'),
        'model' => env('WHISPER_MODEL', 'Systran/faster-whisper-large-v3'),
        'language' => env('WHISPER_LANGUAGE', 'hu'),
        'key' => env('WHISPER_KEY'),
        'timeout' => (int) env('WHISPER_TIMEOUT', 30),
        'audio_format' => 'webm',
    ],

    'azure' => [
        'key' => env('AZURE_SPEECH_KEY'),
        'region' => env('AZURE_SPEECH_REGION', 'westeurope'),
        'language' => env('AZURE_SPEECH_STT_LANGUAGE', 'hu-HU'),
        'audio_format' => 'webm',
    ],
];
