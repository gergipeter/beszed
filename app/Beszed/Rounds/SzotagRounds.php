<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

class SzotagRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        return $this->cycle($items, $count)->map(function ($it) {
            $p = $it->payload;
            $w = $p['word'];
            $syl = $p['syllables'];
            $n = count($syl);

            return $this->round('tapcount', "Doboljuk el: {$w}! Minden szótagra üss egyet a dobra, aztán nyomd meg a pipát!", [
                'mode' => 'drum',
                'stimulus' => ['emoji' => $p['emoji'], 'label' => $w, 'say' => $w],
                'target' => $n,
                'help' => $syl,
                'onCorrect' => 'Igen! '.implode(', ', $syl).'. Ez '.self::NUM[$n].' szótag!',
            ], $it->id);
        })->values()->all();
    }
}
