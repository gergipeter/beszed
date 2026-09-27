<?php

namespace App\Beszed\Gamification;

use App\Models\Child;
use Illuminate\Support\Facades\Redis;

/**
 * Leaderboard Service
 * Rankings across family, classroom, regional
 */
class LeaderboardService
{
    private const FAMILY_KEY = 'leaderboard:family:{parent_id}';
    private const CLASSROOM_KEY = 'leaderboard:classroom:{classroom_id}';
    private const REGIONAL_KEY = 'leaderboard:regional';

    /**
     * Add child score to leaderboards
     */
    public function updateScore(Child $child, int $score): void
    {
        // Add to family leaderboard
        $this->addToLeaderboard(
            str_replace('{parent_id}', $child->parent_id, self::FAMILY_KEY),
            $child->id,
            $score,
            $child->name
        );

        // Add to classroom if assigned
        if ($child->classroom_id) {
            $this->addToLeaderboard(
                str_replace('{classroom_id}', $child->classroom_id, self::CLASSROOM_KEY),
                $child->id,
                $score,
                $child->name
            );
        }

        // Add to regional leaderboard
        $this->addToLeaderboard(
            self::REGIONAL_KEY,
            $child->id,
            $score,
            $child->name
        );
    }

    /**
     * Get family leaderboard
     */
    public function getFamilyLeaderboard(int $parentId, int $limit = 10): array
    {
        $key = str_replace('{parent_id}', $parentId, self::FAMILY_KEY);
        return $this->getLeaderboard($key, $limit);
    }

    /**
     * Get classroom leaderboard
     */
    public function getClassroomLeaderboard(int $classroomId, int $limit = 10): array
    {
        $key = str_replace('{classroom_id}', $classroomId, self::CLASSROOM_KEY);
        return $this->getLeaderboard($key, $limit);
    }

    /**
     * Get regional leaderboard
     */
    public function getRegionalLeaderboard(int $limit = 100): array
    {
        return $this->getLeaderboard(self::REGIONAL_KEY, $limit);
    }

    /**
     * Get child's rank in a leaderboard
     */
    public function getChildRank(Child $child, string $type = 'family'): ?array
    {
        $key = match ($type) {
            'family' => str_replace('{parent_id}', $child->parent_id, self::FAMILY_KEY),
            'classroom' => $child->classroom_id
                ? str_replace('{classroom_id}', $child->classroom_id, self::CLASSROOM_KEY)
                : null,
            'regional' => self::REGIONAL_KEY,
        };

        if (!$key) {
            return null;
        }

        $rank = Redis::zrevrank($key, $child->id);
        $score = Redis::zscore($key, $child->id);
        $name = Redis::hget("leaderboard:names:{$key}", $child->id);

        return $rank !== null ? [
            'rank' => $rank + 1,
            'score' => intval($score ?? 0),
            'name' => $name,
        ] : null;
    }

    /**
     * Calculate score from metrics
     */
    public function calculateScore(Child $child): int
    {
        $pronunciation = \DB::table('beszed_speech_recordings')
            ->where('child_id', $child->id)
            ->avg('pronunciation_score') ?? 0;

        $games = \DB::table('beszed_content_difficulties')
            ->where('child_id', $child->id)
            ->sum('win_rate') ?? 0;

        $attempts = \DB::table('beszed_attempts')
            ->where('child_id', $child->id)
            ->where('correct', true)
            ->count();

        $achievements = \DB::table('beszed_badges')
            ->where('child_id', $child->id)
            ->count() * 100;

        // Score = pronunciation (40%) + game wins (40%) + attempts (10%) + achievements (10%)
        $score = round(
            ($pronunciation * 0.4) +
            ($games * 0.4) +
            (min($attempts, 100) / 100 * 100 * 0.1) +
            ($achievements * 0.1)
        );

        return intval($score);
    }

    /**
     * Add score to a leaderboard
     */
    private function addToLeaderboard(string $key, int $childId, int $score, string $name): void
    {
        Redis::zadd($key, $score, $childId);
        Redis::zadd("{$key}:names", $score, "$childId:$name");

        // Keep only top 1000 to save memory
        Redis::zremrangebyrank($key, 0, -1001);
    }

    /**
     * Get leaderboard entries
     */
    private function getLeaderboard(string $key, int $limit): array
    {
        $entries = Redis::zrevrange($key, 0, $limit - 1, 'withscores');

        $result = [];
        for ($i = 0; $i < count($entries); $i += 2) {
            $childId = $entries[$i];
            $score = intval($entries[$i + 1]);

            $child = Child::find($childId);
            if ($child) {
                $result[] = [
                    'rank' => count($result) + 1,
                    'child_id' => $childId,
                    'name' => $child->name,
                    'score' => $score,
                ];
            }
        }

        return $result;
    }
}
