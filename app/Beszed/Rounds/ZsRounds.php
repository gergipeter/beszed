<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

class ZsRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);

        return $this->cycle($items, $count)->map(function ($it) {
            $p = $it->payload;
            $w = $p['word'];

            return $this->round('choice', "Figyelj! {$w}. Zümmögő zs van benne, vagy csendes s?", [
                'stimulus' => ['emoji' => $p['emoji'], 'label' => $w, 'say' => $w],
                'layout' => 'two',
                'options' => [
                    ['id' => 'zs', 'emoji' => '🐝', 'label' => 'zs'],
                    ['id' => 's', 'emoji' => '🤫', 'label' => 's'],
                ],
                'answer' => $p['sound'],
                'onCorrect' => $p['sound'] === 'zs' ? "Igen! {$w}, zümmögő zs!" : "Igen! {$w}, csendes s!",
                'onWrong' => "Figyeld még egyszer: {$w}",
            ], $it->id);
        })->values()->all();
    }
}
