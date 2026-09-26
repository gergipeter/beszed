<?php

namespace App\Beszed\Rewards;

/** Player level from total stars: level n needs step · n · (n − 1) stars (0, 10, 30, 60, 100… for step 5). */
final class PlayerLevel
{
    public static function threshold(int $level, int $step): int
    {
        return $step * $level * ($level - 1);
    }

    /** @return array{number:int, stars:int, from:int, to:int, progress:float} */
    public static function forStars(int $stars, int $step): array
    {
        $level = 1;
        while (self::threshold($level + 1, $step) <= $stars) {
            $level++;
        }
        $from = self::threshold($level, $step);
        $to = self::threshold($level + 1, $step);

        return [
            'number' => $level,
            'stars' => $stars,
            'from' => $from,
            'to' => $to,
            'progress' => round(($stars - $from) / ($to - $from), 3),
        ];
    }
}
