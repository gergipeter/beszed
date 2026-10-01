<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

class SzamolRounds extends RoundFactory
{
    /** [min, max] range for the numbers compared/counted, and how close the two plates' counts may be. */
    private const RANGE_BY_LEVEL = [1 => [1, 4], 2 => [2, 6], 3 => [3, 9]];
    private const MIN_GAP_BY_LEVEL = [1 => 2, 2 => 1, 3 => 1];

    public function build(Collection $items, int $level, int $count): array
    {
        [$min, $max] = self::RANGE_BY_LEVEL[$level] ?? self::RANGE_BY_LEVEL[3];
        $minGap = self::MIN_GAP_BY_LEVEL[$level] ?? 1;

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
