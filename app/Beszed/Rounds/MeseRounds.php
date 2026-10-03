<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Story listening: Csillám tells a short story, then asks three questions about it, each with three pictures to pick from.
 * One story per session. $level = the content's level (the story's length and how much it asks you to work out).
 */
class MeseRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $pool = $items->filter(fn ($i) => ($i->level ?? 1) <= $level)->values();
        $pool = $pool->isEmpty() ? $items : $pool;
        // the hardest level the child has reached comes up most, but an easier story is welcome too
        $this->favorLevel($pool, $level);
        $story = $this->weightedShuffle($pool)->first();
        $p = $story->payload;
        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($p['text']), -1, PREG_SPLIT_NO_EMPTY);
        $rounds = [];

        for ($k = 1; $k <= min(3, $count); $k++) {
            $question = $p["q$k"] ?? null;
            $options = $p["o$k"] ?? null;
            if (! $question || ! $options) {
                continue;
            }
            $opts = collect($options)->map(fn ($o, $i) => ['id' => "o$i", 'emoji' => $o[0], 'label' => $o[1]])->shuffle()->values();
            $first = $k === 1;

            $rounds[] = $this->round('choice',
                $first ? "Hallgasd meg a mesét: {$p['title']}. $question" : "Emlékszel a mesére? $question", [
                    'stimulus' => ['emoji' => $p['emoji'], 'label' => $p['title'], 'say' => implode(' ', $sentences)],
                    'layout' => 'three',
                    'options' => $opts->all(),
                    'answer' => 'o0',
                    'onCorrect' => 'Igen, így van! '.$options[0][1].'.',
                    'onWrong' => 'Nem ez volt a mesében. Gondold végig, mit mondtam!',
                    'replayParts' => ['Figyelj még egyszer!', ...$sentences, $question],
                ], $story->id, $first ? ['Hallgasd meg a mesét!', "{$p['title']}.", ...$sentences, $question] : [$question]);
        }

        return $rounds;
    }
}
