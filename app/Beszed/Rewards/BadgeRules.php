<?php

namespace App\Beszed\Rewards;

/** Decides whether a sticker's rule (config/beszed.php → rewards.badges.*.rule) is met. */
final class BadgeRules
{
    public const TYPES = ['sessions', 'stars', 'streak', 'perfect', 'all_games', 'game', 'daily_goal', 'daily_path', 'weekly', 'zone'];

    /** @param  string[]  $games  ids of every game in the module */
    public static function passes(array $rule, Stats $stats, int $dailyGoal, array $games): bool
    {
        [$type, $a, $b] = $rule + [null, null, null];

        return match ($type) {
            'sessions' => $stats->sessions >= $a,
            'stars' => $stats->stars >= $a,
            'streak' => $stats->streak >= $a,
            'perfect' => $stats->perfectSessions >= $a,
            'all_games' => array_diff($games, array_keys($stats->gameSessions)) === [],
            'game' => ($stats->gameSessions[$a] ?? 0) >= $b,
            'daily_goal' => $stats->today >= $dailyGoal,
            'daily_path' => $stats->dailyPaths >= $a,
            'weekly' => $stats->weeksWon >= $a,
            // a garden zone: every one of its games finished at least once (config beszed_folders.zones)
            'zone' => ($zone = config("beszed_folders.zones.$a", [])) !== []
                && array_diff(array_intersect($zone, $games), array_keys($stats->gameSessions)) === [],
            default => false,
        };
    }
}
