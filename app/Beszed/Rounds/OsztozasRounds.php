<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Fair sharing: items wait on the table; the child shares them out on plates so each
 * plate gets the same. $level spans 1–100 (RoundFactory::tier(), 3 bands): tier 1 (levels 1–33) 2 plates,
 * 2–8 items · tier 2 (34–66) 2 or 3 plates, up to 9 · tier 3 (67–100) there may be one left over, which
 * stays on the table. Within each tier the plate/each-share bounds (and, in tier 3, the chance of a
 * leftover) widen smoothly towards the next tier's starting point, so level 100 asks for more items per
 * plate than the old level 3 ever did.
 */
class OsztozasRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $tier = $this->tier($level, 3);
        // tier 1: always 2 plates; tiers 2–3: 2 or 3, with 3 growing more likely as $level climbs.
        $threePlateChance = $tier === 1 ? 0.0 : $this->scale($level, 0.3, 0.7);
        // tier 3 only: a leftover starts occasional at level 67 and is certain by level 100.
        $leftoverChance = $tier < 3 ? 0.0 : $this->scale($level, 0.4, 1.0);

        return $this->cycle($items, $count)->map(function ($it) use ($tier, $threePlateChance, $leftoverChance) {
            $plates = $tier === 1 ? 2 : ((mt_rand() / mt_getrandmax()) < $threePlateChance ? 3 : 2);
            $each = $tier === 1 ? random_int(1, 4) : random_int(2, $plates === 2 ? 4 : 3);
            $left = $tier === 3 && (mt_rand() / mt_getrandmax()) < $leftoverChance ? 1 : 0;
            $n = $plates * $each + $left;
            $p = $it->payload;

            $prompt = $left
                ? "Van {$n} {$p['name']}. Oszd szét {$plates} tányérra úgy, hogy mindegyiken ugyanannyi legyen. A maradék maradjon az asztalon!"
                : "Van {$n} {$p['name']}. Oszd szét igazságosan {$plates} tányérra, hogy mindegyiken ugyanannyi legyen!";

            return $this->round('share', $prompt, [
                'emoji' => $p['emoji'],
                'name' => $p['name'],
                'count' => $n,
                'plates' => $plates,
                'each' => $each,
                'left' => $left,
                'onCorrect' => "Igazságos! Mindegyik tányéron {$each} van".($left ? ', és egy maradt az asztalon!' : '!'),
            ], $it->id);
        })->values()->all();
    }
}
