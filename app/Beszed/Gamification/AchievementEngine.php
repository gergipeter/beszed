<?php

namespace App\Beszed\Gamification;

use App\Models\Child;
use Illuminate\Support\Facades\DB;

/**
 * Achievement Engine
 * Badge tracking and milestone management
 */
class AchievementEngine
{
    private const ACHIEVEMENTS = [
        // Pronunciation achievements
        'pronunciation_50' => [
            'name' => 'Clear Speaker',
            'description' => 'Reach 50% pronunciation accuracy',
            'icon' => '🎤',
            'check' => 'pronunciation',
            'threshold' => 50,
        ],
        'pronunciation_75' => [
            'name' => 'Voice Master',
            'description' => 'Reach 75% pronunciation accuracy',
            'icon' => '🎵',
            'check' => 'pronunciation',
            'threshold' => 75,
        ],
        'pronunciation_90' => [
            'name' => 'Perfect Voice',
            'description' => 'Reach 90% pronunciation accuracy',
            'icon' => '👑',
            'check' => 'pronunciation',
            'threshold' => 90,
        ],

        // Game achievements
        'first_game' => [
            'name' => 'Game Starter',
            'description' => 'Complete your first game',
            'icon' => '🎮',
            'check' => 'games_completed',
            'threshold' => 1,
        ],
        'five_games' => [
            'name' => 'Game Explorer',
            'description' => 'Complete 5 different games',
            'icon' => '🗺️',
            'check' => 'games_completed',
            'threshold' => 5,
        ],
        'ten_games' => [
            'name' => 'Game Master',
            'description' => 'Master 10 different games',
            'icon' => '🏆',
            'check' => 'games_completed',
            'threshold' => 10,
        ],

        // Fluency achievements
        'fluent_50' => [
            'name' => 'Smooth Talker',
            'description' => 'Reach 50% fluency score',
            'icon' => '💨',
            'check' => 'fluency',
            'threshold' => 50,
        ],
        'fluent_75' => [
            'name' => 'Fluency Expert',
            'description' => 'Reach 75% fluency score',
            'icon' => '⚡',
            'check' => 'fluency',
            'threshold' => 75,
        ],

        // Streak achievements
        'week_streak' => [
            'name' => 'Week Warrior',
            'description' => 'Practice 7 days in a row',
            'icon' => '⏰',
            'check' => 'daily_streak',
            'threshold' => 7,
        ],
        'month_streak' => [
            'name' => 'Month Master',
            'description' => 'Practice 30 days in a row',
            'icon' => '📅',
            'check' => 'daily_streak',
            'threshold' => 30,
        ],

        // Attempt achievements
        'ten_attempts' => [
            'name' => 'Persistent Learner',
            'description' => 'Complete 10 attempts',
            'icon' => '💪',
            'check' => 'attempts',
            'threshold' => 10,
        ],
        'hundred_attempts' => [
            'name' => 'Dedicated Player',
            'description' => 'Complete 100 attempts',
            'icon' => '🚀',
            'check' => 'attempts',
            'threshold' => 100,
        ],
        'thousand_attempts' => [
            'name' => 'Legend',
            'description' => 'Complete 1000 attempts',
            'icon' => '🌟',
            'check' => 'attempts',
            'threshold' => 1000,
        ],

        // Pet achievements
        'pet_created' => [
            'name' => 'Pet Owner',
            'description' => 'Create your first pet',
            'icon' => '🐣',
            'check' => 'pet_created',
            'threshold' => 1,
        ],
        'pet_elder' => [
            'name' => 'Pet Care Pro',
            'description' => 'Raise pet to elder stage',
            'icon' => '👴',
            'check' => 'pet_stage',
            'threshold' => 5,
        ],

        // Speed achievements
        'speedster' => [
            'name' => 'Speedster',
            'description' => 'Complete 5 attempts in under 10 seconds each',
            'icon' => '⚡',
            'check' => 'speed_attempts',
            'threshold' => 5,
        ],

        // Accuracy achievements
        'perfect_round' => [
            'name' => 'Perfect Session',
            'description' => 'Get 100% accuracy in a session',
            'icon' => '✨',
            'check' => 'perfect_session',
            'threshold' => 1,
        ],
    ];

    /**
     * Check for new achievements
     */
    public function checkAchievements(Child $child): array
    {
        $newAchievements = [];

        foreach (self::ACHIEVEMENTS as $key => $achievement) {
            if ($this->hasAchievement($child, $key)) {
                continue; // Already earned
            }

            if ($this->meetsThreshold($child, $achievement['check'], $achievement['threshold'])) {
                $this->awardAchievement($child, $key, $achievement);
                $newAchievements[] = $achievement;
            }
        }

        return $newAchievements;
    }

    /**
     * Get all achievements for a child
     */
    public function getAchievements(Child $child): array
    {
        return DB::table('beszed_badges')
            ->where('child_id', $child->id)
            ->get()
            ->map(fn ($badge) => [
                'badge' => $badge->badge,
                'earned_at' => $badge->earned_at,
                'details' => self::ACHIEVEMENTS[$badge->badge] ?? [],
            ])
            ->toArray();
    }

    /**
     * Award achievement
     */
    private function awardAchievement(Child $child, string $badge, array $achievement): void
    {
        DB::table('beszed_badges')->insertOrIgnore([
            'child_id' => $child->id,
            'badge' => $badge,
            'name' => $achievement['name'],
            'description' => $achievement['description'],
            'icon' => $achievement['icon'],
            'earned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Check if child already has achievement
     */
    private function hasAchievement(Child $child, string $badge): bool
    {
        return DB::table('beszed_badges')
            ->where('child_id', $child->id)
            ->where('badge', $badge)
            ->exists();
    }

    /**
     * Check if child meets threshold
     */
    private function meetsThreshold(Child $child, string $check, int $threshold): bool
    {
        return match ($check) {
            'pronunciation' => DB::table('beszed_speech_recordings')
                ->where('child_id', $child->id)
                ->avg('pronunciation_score') >= $threshold,

            'fluency' => DB::table('beszed_speech_recordings')
                ->where('child_id', $child->id)
                ->avg('fluency_score') >= $threshold,

            'games_completed' => DB::table('beszed_attempts')
                ->where('child_id', $child->id)
                ->distinct('game')
                ->count('game') >= $threshold,

            'daily_streak' => $this->getDailyStreak($child) >= $threshold,

            'attempts' => DB::table('beszed_attempts')
                ->where('child_id', $child->id)
                ->count() >= $threshold,

            'pet_created' => $child->pets()->count() >= $threshold,

            'pet_stage' => $child->pets()
                ->where('is_alive', true)
                ->max('stage') >= $threshold,

            'speed_attempts' => DB::table('beszed_attempts')
                ->where('child_id', $child->id)
                ->where('duration_ms', '<', 10000)
                ->count() >= $threshold,

            'perfect_session' => DB::table('beszed_sessions')
                ->where('child_id', $child->id)
                ->where('accuracy', '=', 100)
                ->exists(),

            default => false,
        };
    }

    /**
     * Calculate current daily streak
     */
    private function getDailyStreak(Child $child): int
    {
        $dates = DB::table('beszed_attempts')
            ->where('child_id', $child->id)
            ->where('created_at', '>=', now()->subMonths(2))
            ->selectRaw('DATE(created_at) as date')
            ->distinct()
            ->orderByDesc('date')
            ->pluck('date')
            ->toArray();

        if (empty($dates)) {
            return 0;
        }

        $streak = 0;
        $currentDate = now()->startOfDay();

        foreach ($dates as $date) {
            $date = \Carbon\Carbon::parse($date)->startOfDay();

            if ($currentDate->diffInDays($date) === $streak) {
                $streak++;
                $currentDate = $date;
            } else {
                break;
            }
        }

        return $streak;
    }
}
