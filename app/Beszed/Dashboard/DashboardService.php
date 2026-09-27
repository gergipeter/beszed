<?php

namespace App\Beszed\Dashboard;

use App\Models\BeszedAttempt;
use App\Models\BeszedSession;
use App\Models\BeszedSpeechRecording;
use App\Models\Child;
use Carbon\Carbon;

/**
 * Dashboard Service
 * Real-time analytics and metrics for dashboards
 */
class DashboardService
{
    /**
     * Get parent dashboard data for a child
     */
    public function getParentDashboard(Child $child): array
    {
        return [
            'child' => [
                'name' => $child->name,
                'age' => $this->calculateAge($child->birth_date),
                'id' => $child->id,
            ],
            'overview' => $this->getOverviewMetrics($child),
            'today' => $this->getTodayMetrics($child),
            'week' => $this->getWeekMetrics($child),
            'month' => $this->getMonthMetrics($child),
            'speech' => $this->getSpeechMetrics($child),
            'games' => $this->getGameMetrics($child),
            'pet' => $this->getPetMetrics($child),
            'milestones' => $this->getMilestones($child),
            'alerts' => $this->getAlerts($child),
        ];
    }

    /**
     * Get therapist dashboard (multiple children)
     */
    public function getTherapistDashboard(array $childIds): array
    {
        $children = Child::whereIn('id', $childIds)->get();

        return [
            'cohort' => [
                'total_children' => $children->count(),
                'active_today' => $this->getActiveTodayCount($childIds),
                'avg_engagement' => $this->getAverageEngagement($childIds),
            ],
            'children' => $children->map(function ($child) {
                return [
                    'id' => $child->id,
                    'name' => $child->name,
                    'progress' => $this->getOverallProgress($child),
                    'speech_score' => $this->getLatestSpeechScore($child),
                    'games_played_this_week' => $this->getGamesPlayedThisWeek($child),
                    'pronunciation' => $this->getAveragePronunciation($child),
                    'fluency' => $this->getAverageFluency($child),
                ];
            }),
            'trends' => $this->getCohortTrends($childIds),
            'recommendations' => $this->getTherapyRecommendations($childIds),
        ];
    }

    /**
     * Overview metrics (cumulative)
     */
    private function getOverviewMetrics(Child $child): array
    {
        $totalAttempts = BeszedAttempt::where('child_id', $child->id)->count();
        $correctAttempts = BeszedAttempt::where('child_id', $child->id)
            ->where('correct', true)
            ->count();
        $totalTime = BeszedAttempt::where('child_id', $child->id)
            ->sum('duration_ms') / 1000 / 60; // Convert to minutes

        return [
            'total_attempts' => $totalAttempts,
            'correct_attempts' => $correctAttempts,
            'accuracy_rate' => $totalAttempts > 0 ? round(($correctAttempts / $totalAttempts) * 100, 1) : 0,
            'total_time_minutes' => round($totalTime, 0),
            'games_played' => BeszedAttempt::where('child_id', $child->id)
                ->distinct('game')
                ->count('game'),
            'days_active' => BeszedAttempt::where('child_id', $child->id)
                ->selectRaw('DATE(created_at) as date')
                ->distinct()
                ->count(),
        ];
    }

    /**
     * Today's metrics
     */
    private function getTodayMetrics(Child $child): array
    {
        $today = now()->startOfDay();
        $attempts = BeszedAttempt::where('child_id', $child->id)
            ->where('created_at', '>=', $today)
            ->get();

        $correct = $attempts->where('correct', true)->count();
        $total = $attempts->count();

        return [
            'attempts' => $total,
            'correct' => $correct,
            'accuracy' => $total > 0 ? round(($correct / $total) * 100, 1) : 0,
            'time_spent_minutes' => round($attempts->sum('duration_ms') / 1000 / 60, 0),
            'games_today' => $attempts->pluck('game')->unique()->count(),
            'sessions' => BeszedSession::where('child_id', $child->id)
                ->where('completed_at', '>=', $today)
                ->count(),
        ];
    }

    /**
     * This week's metrics
     */
    private function getWeekMetrics(Child $child): array
    {
        $weekAgo = now()->subWeek()->startOfDay();
        $attempts = BeszedAttempt::where('child_id', $child->id)
            ->where('created_at', '>=', $weekAgo)
            ->get();

        // Daily breakdown
        $byDay = $attempts->groupBy(function ($attempt) {
            return $attempt->created_at->toDateString();
        })->map(function ($dayAttempts) {
            $correct = $dayAttempts->where('correct', true)->count();
            $total = $dayAttempts->count();
            return [
                'attempts' => $total,
                'accuracy' => $total > 0 ? round(($correct / $total) * 100, 1) : 0,
            ];
        });

        return [
            'daily_breakdown' => $byDay,
            'total_attempts' => $attempts->count(),
            'total_sessions' => BeszedSession::where('child_id', $child->id)
                ->where('completed_at', '>=', $weekAgo)
                ->count(),
            'days_active' => $byDay->count(),
            'avg_daily_attempts' => round($attempts->count() / 7, 1),
            'total_time_hours' => round($attempts->sum('duration_ms') / 1000 / 60 / 60, 1),
        ];
    }

    /**
     * This month's metrics
     */
    private function getMonthMetrics(Child $child): array
    {
        $monthAgo = now()->subMonth()->startOfDay();
        $attempts = BeszedAttempt::where('child_id', $child->id)
            ->where('created_at', '>=', $monthAgo)
            ->get();

        $correct = $attempts->where('correct', true)->count();
        $total = $attempts->count();

        return [
            'total_attempts' => $total,
            'accuracy_rate' => $total > 0 ? round(($correct / $total) * 100, 1) : 0,
            'total_time_hours' => round($attempts->sum('duration_ms') / 1000 / 60 / 60, 1),
            'avg_session_duration' => BeszedSession::where('child_id', $child->id)
                ->where('completed_at', '>=', $monthAgo)
                ->avg('duration_ms') ?? 0,
            'games_played' => $attempts->pluck('game')->unique()->count(),
            'improvement' => $this->calculateMonthlyImprovement($child),
        ];
    }

    /**
     * Speech analysis metrics
     */
    private function getSpeechMetrics(Child $child): array
    {
        $latest = BeszedSpeechRecording::where('child_id', $child->id)
            ->latest()
            ->first();

        $month = BeszedSpeechRecording::where('child_id', $child->id)
            ->where('created_at', '>=', now()->subMonth())
            ->get();

        return [
            'latest_scores' => $latest ? [
                'pronunciation' => $latest->pronunciation_score,
                'fluency' => $latest->fluency_score,
                'clarity' => $latest->clarity_score,
            ] : null,
            'avg_pronunciation' => round($month->avg('pronunciation_score') ?? 0, 1),
            'avg_fluency' => round($month->avg('fluency_score') ?? 0, 1),
            'avg_clarity' => round($month->avg('clarity_score') ?? 0, 1),
            'recordings_this_month' => $month->count(),
            'trend' => $this->calculateSpeechTrend($child),
        ];
    }

    /**
     * Game metrics
     */
    private function getGameMetrics(Child $child): array
    {
        $games = BeszedAttempt::where('child_id', $child->id)
            ->where('created_at', '>=', now()->subMonth())
            ->selectRaw('game')
            ->selectRaw('COUNT(*) as attempts')
            ->selectRaw('SUM(CASE WHEN correct THEN 1 ELSE 0 END) as correct')
            ->groupBy('game')
            ->orderByDesc('attempts')
            ->limit(10)
            ->get();

        return [
            'most_played' => $games->map(function ($game) {
                $accuracy = $game->attempts > 0
                    ? round(($game->correct / $game->attempts) * 100, 1)
                    : 0;
                return [
                    'game' => $game->game,
                    'attempts' => $game->attempts,
                    'accuracy' => $accuracy,
                ];
            }),
            'total_unique_games' => $games->count(),
        ];
    }

    /**
     * Pet metrics
     */
    private function getPetMetrics(Child $child): array
    {
        $pet = $child->pets()->where('is_alive', true)->latest()->first();

        if (!$pet) {
            return ['status' => 'no_active_pet'];
        }

        return [
            'name' => $pet->name,
            'species' => $pet->species,
            'level' => $pet->level,
            'stage' => $pet->stage,
            'health' => $pet->health,
            'hunger' => $pet->hunger,
            'happiness' => $pet->happiness,
            'energy' => $pet->energy,
            'hygiene' => $pet->hygiene,
            'age_hours' => $pet->born_at->diffInHours(now()),
        ];
    }

    /**
     * Get milestones
     */
    private function getMilestones(Child $child): array
    {
        $milestones = [];

        // Check achievements
        $badges = $child->beszedBadges()->latest()->limit(5)->get();
        foreach ($badges as $badge) {
            $milestones[] = [
                'type' => 'badge',
                'title' => $badge->badge,
                'date' => $badge->earned_at,
            ];
        }

        // Check level milestones
        $topLevel = BeszedContentDifficulty::where('child_id', $child->id)
            ->max('difficulty_rating') ?? 1000;

        if ($topLevel > 1200) {
            $milestones[] = [
                'type' => 'level_milestone',
                'title' => 'Expert Level Reached!',
                'description' => 'Completed advanced content',
            ];
        }

        return $milestones;
    }

    /**
     * Get alerts for parents
     */
    private function getAlerts(Child $child): array
    {
        $alerts = [];

        // No activity alert
        $lastActivity = BeszedAttempt::where('child_id', $child->id)
            ->latest('created_at')
            ->first();

        if (!$lastActivity || $lastActivity->created_at->diffInDays(now()) > 7) {
            $alerts[] = [
                'type' => 'low_engagement',
                'severity' => 'warning',
                'message' => 'No activity this week. Consider scheduling a session!',
            ];
        }

        // Low accuracy alert
        $weekAccuracy = BeszedAttempt::where('child_id', $child->id)
            ->where('created_at', '>=', now()->subWeek())
            ->where('correct', true)
            ->count();

        $weekTotal = BeszedAttempt::where('child_id', $child->id)
            ->where('created_at', '>=', now()->subWeek())
            ->count();

        if ($weekTotal > 10 && $weekAccuracy / $weekTotal < 0.5) {
            $alerts[] = [
                'type' => 'low_accuracy',
                'severity' => 'info',
                'message' => 'Accuracy is below 50%. Consider adjusting difficulty.',
            ];
        }

        return $alerts;
    }

    // Helper methods
    private function calculateAge(?Carbon $birthDate): int
    {
        return $birthDate ? $birthDate->age : 0;
    }

    private function getActiveTodayCount(array $childIds): int
    {
        return BeszedAttempt::whereIn('child_id', $childIds)
            ->where('created_at', '>=', now()->startOfDay())
            ->distinct('child_id')
            ->count('child_id');
    }

    private function getAverageEngagement(array $childIds): float
    {
        $week = now()->subWeek();
        $children = count($childIds);
        $attempts = BeszedAttempt::whereIn('child_id', $childIds)
            ->where('created_at', '>=', $week)
            ->count();

        return $children > 0 ? round($attempts / $children, 1) : 0;
    }

    private function getOverallProgress(Child $child): int
    {
        $month = BeszedAttempt::where('child_id', $child->id)
            ->where('created_at', '>=', now()->subMonth())
            ->get();

        if ($month->isEmpty()) {
            return 0;
        }

        $correct = $month->where('correct', true)->count();
        return round(($correct / $month->count()) * 100);
    }

    private function getLatestSpeechScore(Child $child): int
    {
        $latest = BeszedSpeechRecording::where('child_id', $child->id)
            ->latest()
            ->first();

        return $latest
            ? round(($latest->pronunciation_score + $latest->fluency_score + $latest->clarity_score) / 3)
            : 0;
    }

    private function getGamesPlayedThisWeek(Child $child): int
    {
        return BeszedAttempt::where('child_id', $child->id)
            ->where('created_at', '>=', now()->subWeek())
            ->distinct('game')
            ->count('game');
    }

    private function getAveragePronunciation(Child $child): float
    {
        return round(BeszedSpeechRecording::where('child_id', $child->id)
            ->where('created_at', '>=', now()->subMonth())
            ->avg('pronunciation_score') ?? 0, 1);
    }

    private function getAverageFluency(Child $child): float
    {
        return round(BeszedSpeechRecording::where('child_id', $child->id)
            ->where('created_at', '>=', now()->subMonth())
            ->avg('fluency_score') ?? 0, 1);
    }

    private function calculateMonthlyImprovement(Child $child): array
    {
        $firstWeek = BeszedAttempt::where('child_id', $child->id)
            ->where('created_at', '>=', now()->subMonth())
            ->where('created_at', '<', now()->subWeeks(3))
            ->where('correct', true)
            ->count() ?: 1;

        $lastWeek = BeszedAttempt::where('child_id', $child->id)
            ->where('created_at', '>=', now()->subWeek())
            ->where('correct', true)
            ->count() ?: 1;

        $improvement = round((($lastWeek - $firstWeek) / $firstWeek) * 100, 1);

        return [
            'percentage' => $improvement,
            'trend' => $improvement > 0 ? 'up' : 'down',
        ];
    }

    private function calculateSpeechTrend(Child $child): string
    {
        $twoWeeksAgo = now()->subWeeks(2);
        $recent = BeszedSpeechRecording::where('child_id', $child->id)
            ->where('created_at', '>=', $twoWeeksAgo)
            ->avg('pronunciation_score') ?? 0;

        $older = BeszedSpeechRecording::where('child_id', $child->id)
            ->where('created_at', '<', $twoWeeksAgo)
            ->where('created_at', '>=', now()->subMonths(1))
            ->avg('pronunciation_score') ?? 0;

        return $recent > $older ? 'improving' : ($recent < $older ? 'declining' : 'stable');
    }

    private function getCohortTrends(array $childIds): array
    {
        // Group by day over last 2 weeks
        $twoWeeksAgo = now()->subWeeks(2);
        $attempts = BeszedAttempt::whereIn('child_id', $childIds)
            ->where('created_at', '>=', $twoWeeksAgo)
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN correct THEN 1 ELSE 0 END) as correct')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        return $attempts->map(fn ($a) => [
            'date' => $a->date,
            'total_attempts' => $a->total,
            'accuracy' => round(($a->correct / $a->total) * 100, 1),
        ])->toArray();
    }

    private function getTherapyRecommendations(array $childIds): array
    {
        // Return top recommendations across cohort
        return [
            'focus_on_pronunciation' => BeszedSpeechRecording::whereIn('child_id', $childIds)
                ->where('pronunciation_score', '<', 70)
                ->where('created_at', '>=', now()->subWeek())
                ->count() > 0,
            'increase_frequency' => $this->getActiveTodayCount($childIds) < count($childIds) / 2,
            'vary_games' => true, // Always good to vary
        ];
    }
}
