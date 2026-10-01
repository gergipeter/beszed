<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Rhythm: a pattern of short and long beats is played; the child taps it back on
 * a drum. Content items are the patterns (gaps in beats: 1 = "ta", 2 = "táá");
 * the level picks the length (3 → 5 beats) and how fast it goes.
 */
class RitmusRounds extends RoundFactory
{
    /** Milliseconds per beat: slow first, quicker later. */
    private const BEAT_MS = [1 => 700, 2 => 600, 3 => 520];

    public function build(Collection $items, int $level, int $count): array
    {
        $level = max(1, min(3, $level));
        // patterns up to this level only (the longer ones would be too hard); all of them if too few are left
        $pool = $items->filter(fn ($i) => ($i->level ?? 1) <= $level)->values();
        $items = $pool->count() >= 3 ? $pool : $items;

        return $this->cycle($items, $count)->map(function ($it, $i) use ($level) {
            $name = $it->payload['name'];

            return $this->round('rhythm', $i === 0
                ? 'Figyelj! Ritmust tapsolok a dobon. Utána te jössz: koppints ugyanígy, ugyanolyan gyorsan!'
                : 'Figyelj! Most ez a ritmus jön: '.$name.'.', [
                'pattern' => array_map('intval', $it->payload['pattern']),
                'beatMs' => self::BEAT_MS[$level],
                'level' => $level,
                'onCorrect' => 'Szuper! Pontosan ilyen volt a ritmus!',
                'replayParts' => ['Figyeld újra!'],
            ], $it->id);
        })->values()->all();
    }
}
