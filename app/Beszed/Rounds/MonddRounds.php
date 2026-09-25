<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/** Sentence repetition, judged by the parent. $level 1–3. */
class MonddRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $atLevel = $items->where('level', $level);
        $pool = $atLevel->isNotEmpty() ? $atLevel : $items;
        $label = ['könnyű', 'közepes', 'nehéz'][$level - 1] ?? '';

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
