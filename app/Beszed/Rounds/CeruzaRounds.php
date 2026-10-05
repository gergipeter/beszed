<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/** $level (1–100): path difficulty (wave/arches/hills → zigzag/steps → loops), favoured via favorLevel() against the content's own 1–3 level field. */
class CeruzaRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);

        return $this->cycle($items, $count)->map(fn ($it) => $this->round(
            'trace',
            'Vezesd el a méhecskét a virágig! Kövesd a pöttyöket az ujjaddal!',
            ['path' => $it->payload['path'], 'onCorrect' => 'Szuper! A méhecske odaért a virághoz!'],
            $it->id,
        ))->values()->all();
    }
}
