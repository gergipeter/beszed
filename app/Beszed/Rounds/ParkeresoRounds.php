<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Memory: find the pairs; every card says its word when flipped. The adaptive level's range is 3–100 (a
 * memory board of 100 pairs would be unplayable), so only the lower part of it drives the pair count: it
 * scales from 3 up to 11 pairs across levels 3–40 (scaleInt()), then holds at 11 — from there on, the rest
 * of the climb to 100 is carried by `difficulty` alone (the client's flip-back timing and grading): easy
 * up to level 40, medium up to level 70, hard above that.
 */
class ParkeresoRounds extends RoundFactory
{
    /** Pairs stop growing once the board would be too crowded to scan; difficulty takes over from here. */
    private const MAX_PAIRS = 11;

    /** Levels (of the 3–100 adaptive range) by which the pair count has reached MAX_PAIRS. */
    private const PAIRS_SATURATE_AT = 40;

    public function build(Collection $items, int $level, int $count): array
    {
        $pairs = max(2, min($this->scaleInt($level, 3, self::MAX_PAIRS, self::PAIRS_SATURATE_AT), $items->count()));
        $rounds = [];
        $difficulty = $this->difficultyByLevel($level);

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

    /** Pair count saturates by level 40; difficulty then carries the rest of the 1–100 climb. */
    private function difficultyByLevel(int $level): string
    {
        return match (true) {
            $level < self::PAIRS_SATURATE_AT => 'easy',
            $level < 70 => 'medium',
            default => 'hard',
        };
    }
}
