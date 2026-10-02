<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Minimal pairs: two real words differing by one sound (kés / kész). Hear one,
 * tap its picture. Every pair has a `contrast` ("s – sz") and a grade, the
 * game's level:
 *   1  sounds that are far apart (hal / fal, anya / apa): any child can hear them
 *   2  close sounds: different vowels, r / l, ty / p, b / cs (béka / bika, nyár / nyál)
 *   3  the fine Hungarian contrasts: s / sz / zs, c / cs and short / long vowels
 *      (só / szó, boci / bocsi, bab / báb)
 * A child is mostly given pairs up to their own level. The level also scales the
 * number of options: 2 at first, a 3rd word borrowed from another pair at the top
 * level, so a lucky guess stops being good enough. (Every word and every picture
 * is in the content once, so a borrowed word never doubles one of the pair.)
 */
class IkerhangokRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);

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
