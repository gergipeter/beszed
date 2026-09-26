<?php

namespace App\Beszed\Stt;

interface SttClient
{
    /**
     * Score how well $audio (wav/ogg/webm bytes) matches $referenceText, spoken in Hungarian.
     *
     * @return PronunciationResult|null null when no server assessment is available.
     */
    public function assess(string $audio, string $referenceText): ?PronunciationResult;
}
