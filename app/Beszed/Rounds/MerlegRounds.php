<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * The balance: items sit in the left pan; the child adds items from a tray to the
 * right pan until the beam is level. Level 1: an empty right pan, up to 4 · 2: the
 * right pan already holds some, up to 7 · 3: up to 10, and rounds mix in a "which
 * side is heavier?" question first.
 */
class MerlegRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $level = max(1, min(3, $level));
        $max = [1 => 4, 2 => 7, 3 => 10][$level];

        return $this->cycle($items, $count)->map(function ($it) use ($level, $max) {
            $left = random_int(max(2, (int) ceil($max / 2)), $max);
            $right = $level === 1 ? 0 : random_int(1, $left - 1);
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
