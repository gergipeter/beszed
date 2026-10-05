<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Sentence repetition, judged by the parent. The adaptive level (1–100) maps onto the content's own 1–3
 * level in three equal tiers (tier()): tier 1 easy sentences, tier 2 medium, tier 3 hard.
 */
class MonddRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $tier = $this->tier($level, 3);
        $atLevel = $items->where('level', $tier);
        $pool = $atLevel->isNotEmpty() ? $atLevel : $items;
        $label = ['könnyű', 'közepes', 'nehéz'][$tier - 1] ?? '';

        return $this->cycle($pool->values(), $count)->map(fn ($it) => $this->round(
            'judged',
            "Figyelj, és mondd utánam! {$it->payload['text']}",
            [
                'text' => $it->payload['text'],
                'chunks' => $it->payload['chunks'],
                'emoji' => $it->payload['emoji'],
                'levelLabel' => "{$label} mondat",
            ],
            $it->id,
        ))->values()->all();
    }
}
