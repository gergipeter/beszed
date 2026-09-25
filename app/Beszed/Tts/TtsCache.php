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

    public function path(string $text): string
    {
        return config('tts.prefix').'/'.sha1($this->client->voiceId().'|'.$this->normalize($text)).'.mp3';
    }

    /** @return string|null storage path, or null if no server voice */
    public function ensure(string $text): ?string
    {
        $disk = Storage::disk(config('tts.disk'));
        $path = $this->path($text);

        if ($disk->exists($path)) {
            return $path;
        }

        $audio = $this->client->synthesize($this->normalize($text));
        if ($audio === null) {
            return null;
        }

        $disk->put($path, $audio);

        return $path;
    }
}
