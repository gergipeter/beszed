<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * $level (1–100): the number range compared/counted grows smoothly from 1–4 at level 1 to 3–9 at level 100
 * (scaleInt()), and the minimum gap allowed between the two plates' counts (so "which is more?" isn't a
 * coin flip at the very start) eases from 2 down to 1 over the first third of the range.
 */
class SzamolRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $min = $this->scaleInt($level, 1, 3);
        $max = $this->scaleInt($level, 4, 9);
        $minGap = $level <= 33 ? 2 : 1;

        return $this->cycle($items, $count)->map(function ($it, $i) use ($min, $max, $minGap) {
            $f = $it->payload;

            // Alternate: comparing plates / filling the basket.
            if ($i % 2 === 0) {
                $a = random_int($min, $max);
                do {
                    $b = random_int($min, $max);
                } while (abs($b - $a) < $minGap);
                $less = random_int(1, 100) <= 35;
                $answer = $less ? ($a < $b ? '0' : '1') : ($a > $b ? '0' : '1');

                return $this->round('choice', 'Melyik tányéron van '.($less ? 'kevesebb' : 'több')." {$f['name']}?", [
                    'layout' => 'two',
                    'variant' => 'plates',
                    'options' => [
                        ['id' => '0', 'emojis' => array_fill(0, $a, $f['emoji'])],
                        ['id' => '1', 'emojis' => array_fill(0, $b, $f['emoji'])],
                    ],
                    'answer' => $answer,
                ], $it->id);
            }

            $n = random_int($min, $max);

            return $this->round('tapcount', 'Tegyél a kosárba '.self::NUM[$n]." {$f['accusative']}!", [
                'mode' => 'basket',
                'emoji' => $f['emoji'],
                'name' => $f['name'],
                'accusative' => $f['accusative'],
                'target' => $n,
                'pool' => max(10, $max + 3),
                'onCorrect' => 'Igen! Pont '.self::NUM[$n]." {$f['name']} van a kosárban!",
            ], $it->id);
        })->values()->all();
    }
}
