<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

class SzotagRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);

        return $this->cycle($items, $count)->map(function ($it, $i) {
            $p = $it->payload;
            $w = $p['word'];
            $syl = $p['syllables'];
            $n = count($syl);

            // The how-to only in the first round; after that just the word.
            $prompt = $i === 0 ? "Doboljuk el: {$w}! Minden szótagra üss egyet a dobra, aztán nyomd meg a pipát!" : "Doboljuk el: {$w}!";

            return $this->round('tapcount', $prompt, [
                'mode' => 'drum',
                'stimulus' => ['emoji' => $p['emoji'], 'label' => $w, 'say' => $w],
                'target' => $n,
                'help' => $syl,
                'onCorrect' => 'Igen! '.implode(', ', $syl).'. Ez '.self::NUM[$n].' szótag!',
            ], $it->id);
        })->values()->all();
    }
}
