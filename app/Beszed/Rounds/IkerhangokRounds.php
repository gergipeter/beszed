<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/** Minimal pairs: two real words differing by one sound (kéz / kész). Hear one, tap its picture. */
class IkerhangokRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        return $this->cycle($items, $count)->map(function ($it) {
            $p = $it->payload;
            $sideA = random_int(0, 1) === 0;
            $target = $sideA ? ['word' => $p['wordA'], 'emoji' => $p['emojiA']] : ['word' => $p['wordB'], 'emoji' => $p['emojiB']];
            $other = $sideA ? ['word' => $p['wordB'], 'emoji' => $p['emojiB']] : ['word' => $p['wordA'], 'emoji' => $p['emojiA']];
            $opts = collect([['id' => 'a', ...$target], ['id' => 'b', ...$other]])->shuffle()->values();
            $answer = $opts->firstWhere('word', $target['word'])['id'];

            return $this->round('choice', $target['word'], [
                'layout' => 'two',
                'options' => $opts->map(fn ($o) => ['id' => $o['id'], 'emoji' => $o['emoji'], 'label' => $o['word']])->all(),
                'answer' => $answer,
                'onCorrect' => "Igen! {$target['word']}!",
                'onWrong' => "Ez nem az volt. Figyelj még egyszer: {$target['word']}",
            ], $it->id, [$target['word']]);
        })->values()->all();
    }
}
