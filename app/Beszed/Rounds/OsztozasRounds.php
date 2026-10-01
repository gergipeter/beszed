<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Fair sharing: items wait in a basket; the child shares them out on plates so each
 * plate gets the same. Level 1: 2 plates, 2–8 items · 2: 2 or 3 plates, up to 9 ·
 * 3: there may be one left over, which stays in the basket.
 */
class OsztozasRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $level = max(1, min(3, $level));

        return $this->cycle($items, $count)->map(function ($it) use ($level) {
            $plates = $level === 1 ? 2 : random_int(2, 3);
            $each = $level === 1 ? random_int(1, 4) : random_int(2, $plates === 2 ? 4 : 3);
            $left = $level === 3 ? 1 : 0;
            $n = $plates * $each + $left;
            $p = $it->payload;

            $prompt = $left
                ? "Van {$n} {$p['name']}. Oszd szét {$plates} tányérra úgy, hogy mindegyiken ugyanannyi legyen. A maradék maradjon a kosárban!"
                : "Van {$n} {$p['name']}. Oszd szét igazságosan {$plates} tányérra, hogy mindegyiken ugyanannyi legyen!";

            return $this->round('share', $prompt, [
                'emoji' => $p['emoji'],
                'name' => $p['name'],
                'count' => $n,
                'plates' => $plates,
                'each' => $each,
                'left' => $left,
                'onCorrect' => "Igazságos! Mindegyik tányéron {$each} van".($left ? ', és egy maradt a kosárban!' : '!'),
            ], $it->id);
        })->values()->all();
    }
}
