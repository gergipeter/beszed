<?php

namespace App\Beszed\Tts;

interface TtsClient
{
    /** Raw mp3 bytes, or null when no server voice is available. */
    public function synthesize(string $text): ?string;

    /** Part of the cache key, so changing voice/prosody regenerates audio. */
    public function voiceId(): string;
}
