<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Rhythm: a pattern of short and long beats is played; the child taps it back on
 * a drum. Content items are the patterns (gaps in beats: 1 = "ta", 2 = "táá").
 * $level spans 1–100: the content pool is capped to the matching 1–3 content tier (RoundFactory::tier(),
 * so the longer patterns stay out of reach early on) while beatMs — the pace — scales continuously across
 * the full range (RoundFactory::scaleInt()), from the old level 1's 700ms down to well below the old level
 * 3's 520ms at level 100, so pacing keeps creeping up within a tier, not just at its boundary.
 */
class RitmusRounds extends RoundFactory
{
    /** Milliseconds per beat at level 1 and level 100: slow first, quicker later. */
    private const BEAT_MS = [1 => 700, 100 => 420];

    public function build(Collection $items, int $level, int $count): array
    {
        $tier = $this->tier($level, 3);
        // patterns up to this content tier only (the longer ones would be too hard); all of them if too few are left
        $pool = $items->filter(fn ($i) => ($i->level ?? 1) <= $tier)->values();
        $items = $pool->count() >= 3 ? $pool : $items;
        $beatMs = $this->scaleInt($level, self::BEAT_MS[1], self::BEAT_MS[100]);

        return $this->cycle($items, $count)->map(function ($it, $i) use ($tier, $beatMs) {
            $name = $it->payload['name'];

            return $this->round('rhythm', $i === 0
                ? 'Figyelj! Ritmust tapsolok a dobon. Utána te jössz: koppints ugyanígy, ugyanolyan gyorsan!'
                : 'Figyelj! Most ez a ritmus jön: '.$name.'.', [
                'pattern' => array_map('intval', $it->payload['pattern']),
                'beatMs' => $beatMs,
                'level' => $tier,
                'onCorrect' => 'Szuper! Pontosan ilyen volt a ritmus!',
                'replayParts' => ['Figyeld újra!'],
            ], $it->id);
        })->values()->all();
    }
}
