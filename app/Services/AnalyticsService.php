<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsDailyMetrics;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Track an event
     */
    public static function trackEvent(string $eventType, ?int $userId = null, array $properties = []): void
    {
        AnalyticsEvent::track($eventType, $userId, $properties);

        // Clear cache to update metrics
        Cache::forget('analytics:metrics:' . now()->toDateString());
    }

    /**
     * Track game played
     */
    public static function trackGamePlayed(int $userId, array $data = []): void
    {
        self::trackEvent('game_played', $userId, array_merge([
            'category' => 'game',
            'action' => 'play',
        ], $data));
    }

    /**
     * Track achievement unlocked
     */
    public static function trackAchievementUnlocked(int $userId, string $achievement): void
    {
        self::trackEvent('achievement_unlocked', $userId, [
            'category' => 'achievement',
            'action' => 'unlock',
            'achievement' => $achievement,
        ]);
    }

    /**
     * Get daily metrics
     */
    public static function getDailyMetrics(Carbon $date = null): array
    {
        $date = $date ?? now();
        $dateStr = $date->toDateString();

        return Cache::remember('analytics:metrics:' . $dateStr, 3600, function () use ($date) {
            $startOfDay = $date->copy()->startOfDay();
            $endOfDay = $date->copy()->endOfDay();

            $activeUsers = User::whereBetween('last_activity_at', [$startOfDay, $endOfDay])
                ->count();

            $newUsers = User::whereBetween('created_at', [$startOfDay, $endOfDay])
                ->count();

            $gamesPlayed = AnalyticsEvent::where('event_type', 'game_played')
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->count();

            return [
                'date' => $dateStr,
                'active_users' => $activeUsers,
                'new_users' => $newUsers,
                'games_played' => $gamesPlayed,
                'sessions' => AnalyticsEvent::distinct('session_id')
                    ->whereBetween('created_at', [$startOfDay, $endOfDay])
                    ->count(),
            ];
        });
    }

    /**
     * Get user retention by cohort
     */
    public static function getCohortRetention(Carbon $cohortDate): array
    {
        $users = User::whereDate('created_at', $cohortDate->toDateString())->count();

        $d1 = User::whereDate('created_at', $cohortDate->toDateString())
            ->where('last_activity_at', '>=', $cohortDate->copy()->addDay()->startOfDay())
            ->count();

        $d7 = User::whereDate('created_at', $cohortDate->toDateString())
            ->where('last_activity_at', '>=', $cohortDate->copy()->addDays(7)->startOfDay())
            ->count();

        $d30 = User::whereDate('created_at', $cohortDate->toDateString())
            ->where('last_activity_at', '>=', $cohortDate->copy()->addDays(30)->startOfDay())
            ->count();

        return [
            'cohort_date' => $cohortDate->toDateString(),
            'size' => $users,
            'd1_retention' => $users > 0 ? round(($d1 / $users) * 100, 2) : 0,
            'd7_retention' => $users > 0 ? round(($d7 / $users) * 100, 2) : 0,
            'd30_retention' => $users > 0 ? round(($d30 / $users) * 100, 2) : 0,
        ];
    }

    /**
     * Get MRR (Monthly Recurring Revenue)
     */
    public static function getMRR(): int
    {
        return User::where('subscription_plan', '!=', 'free')
            ->count() * 999; // Simplified - should aggregate actual subscription amounts
    }

    /**
     * Get churn rate
     */
    public static function getChurnRate(int $days = 30): float
    {
        $startDate = now()->subDays($days);
        $endDate = now();

        $totalUsers = User::where('created_at', '<', $endDate)->count();

        $churned = User::where('subscription_plan', 'free')
            ->where('last_activity_at', '<', $endDate->copy()->subDays(7))
            ->count();

        return $totalUsers > 0 ? round(($churned / $totalUsers) * 100, 2) : 0;
    }
}
