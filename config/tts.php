<?php

return [
    // azure | null  (null = no server voice, the browser's Web Speech is used)
    'driver' => env('TTS_DRIVER', 'null'),
    'disk' => env('TTS_DISK', 'public'),
    'prefix' => 'tts',

    'azure' => [
        'key' => env('AZURE_SPEECH_KEY'),
        'region' => env('AZURE_SPEECH_REGION', 'westeurope'),
        // Check the current Hungarian neural voice list in the Azure docs.
        'voice' => env('AZURE_SPEECH_VOICE', 'hu-HU-NoemiNeural'),
        'format' => 'audio-24khz-48kbitrate-mono-mp3',
        // Kids'-teacher feel: a bit slower, a bit higher.
        'rate' => env('TTS_RATE', '-10%'),
        'pitch' => env('TTS_PITCH', '+8%'),
    ],

    'max_chars' => 400,
];
