<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Spelling a word with letters: the picture and the spoken word are given, the letters lie shuffled, and the child taps
 * them in order (the two-letter sounds cs, sz, gy… are one tile, as in Hungarian). Reading and writing readiness.
 * $level = the content's level: 3 letters, 4 letters, 5-6 letters.
 */
class SzokirakoRounds extends RoundFactory
{
    /** The letters of a word, the two- and three-letter sounds as one unit. */
    public static function letters(string $word): array
    {
        preg_match_all('/dzs|cs|dz|gy|ly|ny|sz|ty|zs|./u', mb_strtolower($word), $m);

        return $m[0];
    }

    public function build(Collection $items, int $level, int $count): array
    {
        $pool = $items->filter(fn ($i) => ($i->level ?? 1) <= $level)->values();
        $pool = $pool->count() >= 5 ? $pool : $items;
        $this->favorLevel($pool, $level);
        $rounds = [];

        foreach ($this->weightedShuffle($pool)->take($count)->values() as $r => $item) {
            $word = $item->payload['word'];
            $pieces = collect(self::letters($word))->map(fn ($l, $k) => ['id' => "l$k", 'emoji' => '', 'label' => $l]);
            $order = $pieces->pluck('id')->all();
            $try = 0;
            do {
                $shuffled = $pieces->shuffle()->values();
            } while ($pieces->count() > 1 && $shuffled->pluck("label")->all() === $pieces->pluck("label")->all() && ++$try < 25);

            $rounds[] = $this->round('order', ($r === 0 ? 'Rakd ki betűkből a szót! ' : 'Rakd ki a szót! ').$this->ucfirst($word).'.', [
                'stimulus' => ['emoji' => $item->payload['emoji'], 'label' => ''],
                'items' => $shuffled->all(),
                'order' => $order,
                'wrong' => "Hmm, ez a betű még nem ide kerül. Mondd ki lassan: $word. Melyik hang jön először?",
                'onCorrect' => "Ügyes! {$this->ucfirst($word)}: kirakva, betűről betűre!",
            ], $item->id, [($r === 0 ? 'Rakd ki betűkből a szót!' : 'Rakd ki a szót!'), $word.'.']);
        }

        return $rounds;
    }
}
