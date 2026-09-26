<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/** Memory: find the pairs; every card says its word when flipped. $level = number of pairs (3–6). */
class ParkeresoRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $pairs = max(2, min($level, $items->count()));
        $rounds = [];

        for ($r = 0; $r < $count; $r++) {
            $cards = $items->shuffle()->take($pairs)
                ->flatMap(fn ($w) => array_fill(0, 2, $w))
                ->shuffle()->values()
                ->map(fn ($w, $i) => [
                    'id' => "c$i",
                    'pair' => (string) $w->id,
                    'emoji' => $w->payload['emoji'],
                    'label' => $w->payload['word'],
                ])->all();

            $rounds[] = $this->round('memory', 'Keresd meg a párokat! Fordíts fel két kártyát.', [
                'cards' => $cards,
                'onCorrect' => 'Szuper! Megtaláltad az összes párt!',
            ]);
        }

        return $rounds;
    }
}
