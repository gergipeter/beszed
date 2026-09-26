<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Spot the difference: two panels of pictures, alike but for one cell.
 * $level = grid per panel; at the top level the odd picture is a look-alike
 * (same group: a cat for a dog) instead of something from another group.
 */
class KulonbsegRounds extends RoundFactory
{
    /** level → [cols, rows] */
    public const GRIDS = [1 => [2, 2], 2 => [3, 2], 3 => [3, 3]];

    public function build(Collection $items, int $level, int $count): array
    {
        [$cols, $rows] = self::GRIDS[$level] ?? self::GRIDS[1];
        $cells = $cols * $rows;
        $rounds = [];

        foreach ($this->cycle($items, $count)->values() as $r => $changed) {
            $group = $changed->payload['group'];
            $others = $items->reject(fn ($i) => $i->id === $changed->id);
            $alike = $others->filter(fn ($i) => $i->payload['group'] === $group);
            $swap = ($level >= 3 && $alike->isNotEmpty() ? $alike : $others->reject(fn ($i) => $i->payload['group'] === $group))
                ->whenEmpty(fn () => $others)->random();

            $fill = $others->reject(fn ($i) => $i->id === $swap->id)->shuffle()->take($cells - 1);
            $diff = random_int(0, $cells - 1);
            $grid = $fill->values()->map(fn ($i) => $i->payload['emoji'])->all();
            array_splice($grid, $diff, 0, [$changed->payload['emoji']]);
            $other = $grid;
            $other[$diff] = $swap->payload['emoji'];
            // Which side holds the original doesn't matter to the child; mix it up.
            [$left, $right] = random_int(0, 1) ? [$grid, $other] : [$other, $grid];

            $prompt = $r === 0
                ? 'Nézd meg a két képet! Egy helyen más van rajtuk. Keresd meg, és koppints rá!'
                : 'Hol más a két kép? Keresd meg!';

            $rounds[] = $this->round('difference', $prompt, [
                'cols' => $cols,
                'rows' => $rows,
                'left' => $left,
                'right' => $right,
                'diff' => $diff,
                'onCorrect' => "Megtaláltad! Az egyik képen {$changed->payload['name']} van, a másikon {$swap->payload['name']}.",
                'onWrong' => 'Ez a két képen ugyanolyan. Keress tovább!',
            ], $changed->id);
        }

        return $rounds;
    }
}
