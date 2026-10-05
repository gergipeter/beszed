<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Rhyme memory: flip two cards; a pair rhymes but is never the same word
 * (dal / hal, not dal / dal). $level = number of rhyme pairs, scaled from 2 up
 * to a cap of 8: a memory board of more than 16 cards is unwieldy on a phone
 * and the pair count is this game's only difficulty axis (the content carries
 * no notion of "how similar" two rhymes are), so it is the whole ladder. The
 * count reaches 8 by level 85 and holds there for the last stretch.
 */
class RimparokRounds extends RoundFactory
{
    /** Pair count saturates at this many (16-card board — see class doc). */
    private const MAX_PAIRS = 8;

    /** $level at which the pair count has fully saturated. */
    private const SATURATE_AT = 85;

    public function build(Collection $items, int $level, int $count): array
    {
        $byRhyme = $items->groupBy(fn ($i) => $i->payload['rhyme']);
        $groups = $byRhyme->filter(fn ($g) => $g->count() >= 2);
        $target = $this->scaleInt(min($level, self::SATURATE_AT), 2, self::MAX_PAIRS, self::SATURATE_AT);
        $pairs = max(2, min($target, $groups->count()));

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
