<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/** Word-list recall. $level = number of words (2–6). */
class PapagajRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $n = max(2, min($level, $items->count() - 3));
        $rounds = [];

        for ($r = 0; $r < $count; $r++) {
            $words = $this->weightedShuffle($items)->take($n)->values();
            $ids = $words->pluck('id');
            $distract = $items->whereNotIn('id', $ids)->shuffle()->take(max(3, 8 - $n));
            $grid = $words->merge($distract)->shuffle()->map(fn ($w) => [
                'id' => (string) $w->id, 'emoji' => $w->payload['emoji'], 'label' => $w->payload['word'],
            ])->values()->all();
            $names = $words->map(fn ($w) => $w->payload['word'])->all();

            // The how-to only in the first round; after that just the words.
            [$open, $close] = $r === 0
                ? ['Figyelj, és mondd utánam!', 'Mondd vissza hangosan, aztán mutasd meg sorban a képeken!']
                : ['Figyelj!', 'Most te!'];

            $rounds[] = $this->round('sequence', $open.' '.implode(', ', $names).'.', [
                'order' => $ids->map(fn ($id) => (string) $id)->all(),
                'grid' => $grid,
                'onCorrect' => 'Szuper! '.implode(', ', $names).'. Mind megvan!',
                'replayParts' => ['Figyelj még egyszer!', ...$names],
            ], null, [$open, ...$names, $close]);
        }

        return $rounds;
    }
}
