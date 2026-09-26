<?php

namespace App\Beszed\Stt;

/** No server assessment: the client falls back to the parent judging the attempt. */
class NullSttClient implements SttClient
{
    public function assess(string $audio, string $referenceText): ?PronunciationResult
    {
        return null;
    }
}
