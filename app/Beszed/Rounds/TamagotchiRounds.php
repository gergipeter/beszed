<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Tamagotchi has no drill content to cycle through: it's one continuous
 * pet-care mini-game. A single round hands the engine its one content item
 * (the pet's starting name) as config; the engine runs the whole feed/play/
 * sleep loop itself and reports one 'answer' when the child ends the visit.
 */
class TamagotchiRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $item = $items->first();
        $p = $item->payload;
        $name = $p['petName'] ?? 'Pipi';

        return [$this->round('tamagotchi', 'Vigyázz a kis kedvencedre!', [
            'petName' => $name,
            'onCorrect' => "Ügyes voltál, {$name} nagyon boldog lett!",
        ], $item->id)];
    }
}
