<?php

namespace App\Beszed\Tts;

interface TtsClient
{
    /**
     * Raw mp3 bytes, or null when no server voice is available.
     *
     * @param  array{voice?: string, rate?: string, pitch?: string}  $overrides  Per-parent voice settings; missing keys use the configured default.
     */
    public function synthesize(string $text, array $overrides = []): ?string;

    /**
     * Part of the cache key, so changing voice/prosody regenerates audio.
     *
     * @param  array{voice?: string, rate?: string, pitch?: string}  $overrides
     */
    public function voiceId(array $overrides = []): string;
}
