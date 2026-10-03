<?php

$driver = env('TTS_DRIVER', 'null');

return [
    // piper | azure | null
    //   piper = free neural voice running on this server (installed in the Docker images)
    //   azure = Azure Speech (needs AZURE_SPEECH_KEY)
    //   null  = no server voice; the browser's Web Speech is used
    'driver' => $driver,
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

    'piper' => [
        'binary' => env('PIPER_BINARY', '/opt/piper/piper'),
        'voices_path' => env('PIPER_VOICES', '/opt/piper/voices'),
        'voice' => env('PIPER_VOICE', 'hu_HU-anna-medium'),
        // > 1 = slower; a calm pace for 4–7 year olds.
        'length_scale' => (float) env('PIPER_LENGTH_SCALE', 1.12),
        // Pause between sentences (seconds).
        'sentence_silence' => 0.22,
        'lame' => env('LAME_BINARY', 'lame'),
        'bitrate' => 48,
    ],

    // Voices the parent can pick in Settings (name = Azure voice / Piper model).
    'voices' => $driver === 'piper'
        ? [
            'anna' => ['name' => 'hu_HU-anna-medium', 'label' => 'Anna', 'gender' => 'female'],
            'imre' => ['name' => 'hu_HU-imre-medium', 'label' => 'Imre', 'gender' => 'male'],
        ]
        : [
            'noemi' => ['name' => 'hu-HU-NoemiNeural', 'label' => 'Noémi', 'gender' => 'female'],
            'tamas' => ['name' => 'hu-HU-TamasNeural', 'label' => 'Tamás', 'gender' => 'male'],
        ],

    // Rate/pitch range the parent's sliders may pick from (SSML percentages; Piper uses rate only).
    'rate_range' => ['min' => -40, 'max' => 20],
    'pitch_range' => ['min' => -20, 'max' => 30],

    'max_chars' => 400,
];
