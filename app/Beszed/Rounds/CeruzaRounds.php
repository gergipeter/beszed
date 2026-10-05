<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * $level (1–100): the shape family (wave/arches/hills/zigzag/steps/loops) still
 * comes from the content item (favoured by its own 1–3 level, for variety), but
 * how hard that shape actually is — segment count, amplitude, sharpness — scales
 * smoothly with $level itself (client: paths.js), not with the item's tier. So a
 * "wave" at level 5 is a couple of wide, gentle curves, and the same "wave" at
 * level 90 is many tight, sharp ones: no more all-or-nothing jumps between tiers.
 */
class CeruzaRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);
        $difficulty = $this->scaleInt($level, 1, 100);

        return $this->cycle($items, $count)->map(fn ($it) => $this->round(
            'trace',
            'Vezesd el a méhecskét a virágig! Kövesd a pöttyöket az ujjaddal!',
            ['path' => $it->payload['path'], 'difficulty' => $difficulty, 'onCorrect' => 'Szuper! A méhecske odaért a virághoz!'],
            $it->id,
        ))->values()->all();
    }
}
