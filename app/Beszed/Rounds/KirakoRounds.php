<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/** Picture puzzle: swap pieces until the picture is whole. $level 1–3 = 2×2, 3×2, 3×3 pieces. */
class KirakoRounds extends RoundFactory
{
    /** level → [columns, rows] */
    public const GRIDS = [1 => [2, 2], 2 => [3, 2], 3 => [3, 3]];

    public function build(Collection $items, int $level, int $count): array
    {
        [$cols, $rows] = self::GRIDS[$level] ?? self::GRIDS[1];

        return $this->cycle($items, $count)->map(fn ($it) => $this->round(
            'puzzle',
            'Rakd ki a képet! Koppints két darabra, és helyet cserélnek.',
            [
                'emoji' => $it->payload['emoji'],
                'cols' => $cols,
                'rows' => $rows,
                'pieces' => $this->shuffled($cols * $rows),
                'onCorrect' => "Hurrá! Kész a kép! Ez egy {$it->payload['name']}!",
            ],
            $it->id,
        ))->values()->all();
    }

    /** pieces[position] = the piece lying there; never already solved. */
    private function shuffled(int $n): array
    {
        $solved = range(0, $n - 1);
        do {
            $order = collect($solved)->shuffle()->values()->all();
        } while ($order === $solved);

        return $order;
    }
}
