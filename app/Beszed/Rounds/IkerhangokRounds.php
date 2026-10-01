<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Minimal pairs: two real words differing by one sound (kéz / kész). Hear one,
 * tap its picture. Content has just one difficulty grade (every pair is
 * equally "close"), so level instead scales the number of options: 2 at
 * first, a 3rd word borrowed from another pair once the child is solid,
 * so a lucky guess stops being good enough.
 */
class IkerhangokRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        return $this->cycle($items, $count)->map(function ($it) use ($items, $level) {
            $p = $it->payload;
            $sideA = random_int(0, 1) === 0;
            $target = $sideA ? ['word' => $p['wordA'], 'emoji' => $p['emojiA']] : ['word' => $p['wordB'], 'emoji' => $p['emojiB']];
            $other = $sideA ? ['word' => $p['wordB'], 'emoji' => $p['emojiB']] : ['word' => $p['wordA'], 'emoji' => $p['emojiA']];
            $opts = collect([['id' => 'a', ...$target], ['id' => 'b', ...$other]]);

            if ($level >= 3 && $items->count() > 1) {
                $extra = $items->reject(fn ($o) => $o->id === $it->id)->random()->payload;
                $extraSide = random_int(0, 1) === 0;
                $opts->push(['id' => 'c', 'word' => $extraSide ? $extra['wordA'] : $extra['wordB'], 'emoji' => $extraSide ? $extra['emojiA'] : $extra['emojiB']]);
            }

            $opts = $opts->shuffle()->values();
            $answer = $opts->firstWhere('word', $target['word'])['id'];

            return $this->round('choice', $target['word'], [
                'layout' => $opts->count() > 2 ? 'three' : 'two',
                'options' => $opts->map(fn ($o) => ['id' => $o['id'], 'emoji' => $o['emoji'], 'label' => $o['word']])->all(),
                'answer' => $answer,
                'onCorrect' => "Igen! {$target['word']}!",
                'onWrong' => "Ez nem az volt. Figyelj még egyszer: {$target['word']}",
            ], $it->id, [$target['word']]);
        })->values()->all();
    }
}
