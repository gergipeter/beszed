<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Reading a word: it is written in syllables ("ku-tya") and never said; the child reads it (with a parent's help at first)
 * and taps the picture that goes with it. $level = the content's level: short words, longer two-syllable ones, three syllables.
 */
class OlvasdRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $pool = $items->filter(fn ($i) => ($i->level ?? 1) <= $level)->values();
        $pool = $pool->count() >= 6 ? $pool : $items;
        $this->favorLevel($pool, $level);
        $rounds = [];

        foreach ($this->weightedShuffle($pool)->take($count)->values() as $r => $item) {
            $word = $item->payload['word'];
            $opts = $pool->reject(fn ($i) => $i->id === $item->id || $i->payload['emoji'] === $item->payload['emoji'])
                ->shuffle()->take(2)->push($item)->shuffle()->values();

            $rounds[] = $this->round('choice', $r === 0
                ? 'Olvasd el a szót! Melyik képhez illik? Koppints rá!'
                : 'Olvasd el! Melyik kép illik hozzá?', [
                'stimulus' => ['emoji' => '', 'letter' => implode('·', $item->payload['syllables'])],
                'layout' => 'three',
                'options' => $opts->map(fn ($o) => ['id' => (string) $o->id, 'emoji' => $o->payload['emoji']])->all(),
                'answer' => (string) $item->id,
                'onCorrect' => "Igen! {$this->ucfirst($word)}. Jól olvastad!",
                'onWrong' => 'Nem ez az. Olvasd el még egyszer, szótagonként!',
            ], $item->id);
        }

        return $rounds;
    }
}
