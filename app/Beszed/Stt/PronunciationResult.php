<?php

namespace App\Beszed\Stt;

/** Azure Pronunciation Assessment scores, 0–100 each. */
class PronunciationResult
{
    public function __construct(
        public readonly float $accuracy,
        public readonly float $fluency,
        public readonly float $completeness,
        public readonly string $recognizedText,
    ) {}

    /** Maps the score onto the engines' 1–3 self-grade (1 = smooth … 3 = struggled). */
    public function tries(): int
    {
        $overall = ($this->accuracy + $this->fluency + $this->completeness) / 3;

        return match (true) {
            $overall >= 80 => 1,
            $overall >= 55 => 2,
            default => 3,
        };
    }

    /** Below this, we ask the child to try again rather than accept the attempt. */
    public function correct(): bool
    {
        return $this->accuracy >= 40;
    }
}
