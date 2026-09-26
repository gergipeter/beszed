<?php

namespace App\Beszed\Tts;

/** No server TTS: the client falls back to the browser's Web Speech voice. */
class NullTtsClient implements TtsClient
{
    public function synthesize(string $text, array $overrides = []): ?string
    {
        return null;
    }

    public function voiceId(array $overrides = []): string
    {
        return 'null';
    }
}
