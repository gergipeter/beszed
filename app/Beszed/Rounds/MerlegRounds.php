<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * The balance: items sit in the left pan; the child adds items from a tray to the
 * right pan until the beam is level. $level (1–100): the highest count in play grows
 * smoothly from 4 to 10 (scaleInt()); tier(level, 3) decides the pan's starting point —
 * tier 1 → an empty right pan (plain counting-on) · tiers 2–3 → the right pan already
 * holds some (subtraction). A "which side is heavier?" question is not yet asked here.
 */
class MerlegRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $tier = $this->tier($level, 3);
        $max = $this->scaleInt($level, 4, 10);

        return $this->cycle($items, $count)->map(function ($it) use ($tier, $max) {
            $left = random_int(max(2, (int) ceil($max / 2)), $max);
            $right = $tier === 1 ? 0 : random_int(1, $left - 1);
            $need = $left - $right;
            $p = $it->payload;
            $prompt = $right === 0
                ? "A bal serpenyőben {$left} {$p['name']} van. Tegyél annyit a jobb serpenyőbe, hogy egyensúlyban legyen a mérleg!"
                : "A bal oldalon {$left} {$p['name']} van, a jobb oldalon {$right}. Hány {$p['name']} kell még a jobb oldalra, hogy egyensúlyban legyen?";

            return $this->round('balance', $prompt, [
                'emoji' => $p['emoji'],
                'left' => $left,
                'right' => $right,
                // a few more in the tray than needed, so the child has to count
                'tray' => $need + 3,
                'onCorrect' => "Egyensúlyban! {$left} egyenlő {$left}, mert {$right} meg {$need} az {$left}!",
            ], $it->id);
        })->values()->all();
    }
}
