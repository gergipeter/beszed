<?php

namespace App\Jobs;

use App\Beszed\Tts\TtsCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SynthesizeSpeech implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(public string $text) {}

    public function handle(TtsCache $cache): void
    {
        $cache->ensure($this->text);
    }
}
