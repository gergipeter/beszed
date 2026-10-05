<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Word-list recall. $level (1-100) scales the number of words to repeat back
 * from 2 up to a cap of 8 — more than that is unplayable for a small child to
 * hold in memory — reaching the cap by level 60. The number of distractor
 * tiles on the grid (where the child points out the words afterwards) scales
 * separately and continuously across the whole 1-100 range (3 to 6 extra
 * tiles), so the search gets harder even once the list length holds steady
 * past level 60.
 */
class PapagajRounds extends RoundFactory
{
    /** Word count saturates at this many (see class doc). */
    private const MAX_WORDS = 8;

    /** $level at which the word count has fully saturated. */
    private const SATURATE_AT = 60;

    public function build(Collection $items, int $level, int $count): array
    {
        $n = max(2, min($this->scaleInt(min($level, self::SATURATE_AT), 2, self::MAX_WORDS, self::SATURATE_AT), $items->count() - 3));
        $gridExtra = $this->scaleInt($level, 3, 6);
        $rounds = [];

        for ($r = 0; $r < $count; $r++) {
            $words = $this->weightedShuffle($items)->take($n)->values();
            $ids = $words->pluck('id');
            $distract = $items->whereNotIn('id', $ids)->shuffle()->take($gridExtra);
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
