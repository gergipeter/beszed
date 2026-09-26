<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Rhyme memory: flip two cards; a pair rhymes but is never the same word
 * (dal / hal, not dal / dal). $level = number of rhyme pairs (2-4).
 */
class RimparokRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $byRhyme = $items->groupBy(fn ($i) => $i->payload['rhyme']);
        $groups = $byRhyme->filter(fn ($g) => $g->count() >= 2);
        $pairs = max(2, min($level, $groups->count()));

        if ($groups->count() < $pairs) {
            return [];
        }

        $rounds = [];
        for ($r = 0; $r < $count; $r++) {
            $cards = $groups->shuffle()->take($pairs)->values()
                ->flatMap(fn ($group, $g) => $group->shuffle()->take(2)
                    ->map(fn ($w) => ['w' => $w, 'pair' => "g$g"]))
                ->shuffle()->values()
                ->map(fn ($c, $i) => [
                    'id' => "c$i",
                    'pair' => $c['pair'],
                    'emoji' => $c['w']->payload['emoji'],
                    'label' => $c['w']->payload['word'],
                ])->all();

            $rounds[] = $this->round('memory', 'Keresd meg, mely szavak rímelnek! Fordíts fel két kártyát.', [
                'cards' => $cards,
                'onCorrect' => 'Szuper! Minden rím megvan!',
            ]);
        }

        return $rounds;
    }
}
