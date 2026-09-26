<?php

namespace App\Beszed\Tts;

use Illuminate\Support\Facades\Storage;

/** Every sentence is synthesized once, then served from storage forever. */
class TtsCache
{
    public function __construct(private TtsClient $client) {}

    public function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** @param  array{voice?: string, rate?: string, pitch?: string}  $overrides */
    public function path(string $text, array $overrides = []): string
    {
        return config('tts.prefix').'/'.sha1($this->client->voiceId($overrides).'|'.$this->normalize($text)).'.mp3';
    }

    /**
     * @param  array{voice?: string, rate?: string, pitch?: string}  $overrides
     * @return string|null storage path, or null if no server voice
     */
    public function ensure(string $text, array $overrides = []): ?string
    {
        $disk = Storage::disk(config('tts.disk'));
        $path = $this->path($text, $overrides);

        if ($disk->exists($path)) {
            return $path;
        }

        $audio = $this->client->synthesize($this->normalize($text), $overrides);
        if ($audio === null) {
            return null;
        }

        $disk->put($path, $audio);

        return $path;
    }
}
