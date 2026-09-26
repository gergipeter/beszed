<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Memory: find the pairs; every card says its word when flipped.
 * $level = number of pairs (3–6).
 * Supports difficulty levels: easy (slower flip-back), medium, hard (faster flip-back, stricter grading)
 */
class ParkeresoRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $pairs = max(2, min($level, $items->count()));
        $rounds = [];
        $difficulty = $this->getDifficultyByLevel($level);

        for ($r = 0; $r < $count; $r++) {
            $cards = $this->weightedShuffle($items)->take($pairs)
                ->flatMap(fn ($w) => array_fill(0, 2, $w))
                ->shuffle()->values()
                ->map(fn ($w, $i) => [
                    'id' => "c$i",
                    'pair' => (string) $w->id,
                    'emoji' => $w->payload['emoji'],
                    'label' => $w->payload['word'],
                ])->all();

            $rounds[] = $this->round('memory', 'Keresd meg a párokat! Fordíts fel két kártyát.', [
                'cards' => $cards,
                'difficulty' => $difficulty,
                'onCorrect' => 'Szuper! Megtaláltad az összes párt!',
            ]);
        }

        return $rounds;
    }

    /** Map player level to game difficulty: level 3–4 = easy, 5 = medium, 6+ = hard */
    private function getDifficultyByLevel(int $level): string
    {
        return match (true) {
            $level <= 4 => 'easy',
            $level === 5 => 'medium',
            default => 'hard',
        };
    }
}
