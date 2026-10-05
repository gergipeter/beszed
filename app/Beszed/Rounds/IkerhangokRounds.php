<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Minimal pairs: two real words differing by one sound (kés / kész). Hear one,
 * tap its picture. Every pair has a `contrast` ("s – sz") and a grade, the
 * content's own 1–3 level field:
 *   1  sounds that are far apart (hal / fal, anya / apa): any child can hear them
 *   2  close sounds: different vowels, r / l, ty / p, b / cs (béka / bika, nyár / nyál)
 *   3  the fine Hungarian contrasts: s / sz / zs, c / cs and short / long vowels
 *      (só / szó, boci / bocsi, bab / báb)
 * $level spans 1–100 (RoundFactory::tier(), 3 bands matching that content grade): a child is mostly given
 * pairs up to their own tier (favorLevel()). The tier also scales the number of options: 2 at first, a 3rd
 * word borrowed from another pair once the top tier (67–100) is reached — with the chance of that 3rd
 * option growing from occasional at level 67 to certain by level 100, rather than switching all at once —
 * so a lucky guess stops being good enough. (Every word and every picture is in the content once, so a
 * borrowed word never doubles one of the pair.)
 */
class IkerhangokRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);
        $tier = $this->tier($level, 3);
        // top tier only: grows from an occasional 3rd option at level 67 to always by level 100.
        $extraChance = $tier < 3 ? 0.0 : $this->scale($level, 0.25, 1.0);

        return $this->cycle($items, $count)->map(function ($it) use ($items, $extraChance) {
            $p = $it->payload;
            $sideA = random_int(0, 1) === 0;
            $target = $sideA ? ['word' => $p['wordA'], 'emoji' => $p['emojiA']] : ['word' => $p['wordB'], 'emoji' => $p['emojiB']];
            $other = $sideA ? ['word' => $p['wordB'], 'emoji' => $p['emojiB']] : ['word' => $p['wordA'], 'emoji' => $p['emojiA']];
            $opts = collect([['id' => 'a', ...$target], ['id' => 'b', ...$other]]);

            if ($items->count() > 1 && (mt_rand() / mt_getrandmax()) < $extraChance) {
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
