<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

class SzamolRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        return $this->cycle($items, $count)->map(function ($it, $i) {
            $f = $it->payload;

            // Alternate: comparing plates / filling the basket.
            if ($i % 2 === 0) {
                $a = random_int(1, 8);
                do {
                    $b = random_int(1, 8);
                } while ($b === $a);
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

            $n = random_int(2, 7);

            return $this->round('tapcount', 'Tegyél a kosárba '.self::NUM[$n]." {$f['accusative']}!", [
                'mode' => 'basket',
                'emoji' => $f['emoji'],
                'name' => $f['name'],
                'accusative' => $f['accusative'],
                'target' => $n,
                'pool' => 10,
                'onCorrect' => 'Igen! Pont '.self::NUM[$n]." {$f['name']} van a kosárban!",
            ], $it->id);
        })->values()->all();
    }
}
