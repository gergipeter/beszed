<?php

namespace App\Beszed\AdaptiveLearning;

use App\Models\BeszedAttempt;
use App\Models\BeszedContentDifficulty;
use App\Models\BeszedContentItem;
use App\Models\Child;

/**
 * Adaptive Difficulty System
 * Uses Elo-style rating to adjust content difficulty per child
 */
class DifficultyRater
{
    const BASE_RATING = 1000;
    const K_FACTOR = 32; // Adjustment factor per game
    const WIN_THRESHOLD = 0.7; // 70% accuracy = win

    /**
     * Rate content difficulty for a child
     */
    public function rateContentForChild(BeszedContentItem $item, Child $child): float
    {
        $difficulty = BeszedContentDifficulty::firstOrCreate(
            ['child_id' => $child->id, 'content_item_id' => $item->id],
            [
                'game' => $item->game,
                'difficulty_rating' => self::BASE_RATING,
                'attempts' => 0,
                'successes' => 0,
                'win_rate' => 0.0,
            ]
        );

        return $difficulty->difficulty_rating;
    }

    /**
     * Update difficulty based on child's performance
     */
    public function updateDifficultyAfterAttempt(
        BeszedContentItem $item,
        Child $child,
        bool $correct,
        int $tries = 1,
        int $duration_ms = 0
    ): void {
        $difficulty = BeszedContentDifficulty::where('child_id', $child->id)
            ->where('content_item_id', $item->id)
            ->first();

        if (!$difficulty) {
            $difficulty = BeszedContentDifficulty::create([
                'child_id' => $child->id,
                'content_item_id' => $item->id,
                'game' => $item->game,
                'difficulty_rating' => self::BASE_RATING,
                'attempts' => 0,
                'successes' => 0,
                'win_rate' => 0.0,
            ]);
        }

        // Update stats
        $difficulty->attempts += 1;
        if ($correct) {
            $difficulty->successes += 1;
        }

        $difficulty->win_rate = ($difficulty->successes / $difficulty->attempts) * 100;
        $difficulty->last_attempted_at = now();

        // Adjust Elo rating
        $difficulty->difficulty_rating = $this->calculateNewRating(
            $difficulty->difficulty_rating,
            $correct,
            $tries,
            $duration_ms
        );

        $difficulty->save();
    }

    /**
     * Calculate new Elo rating
     * Considers: correctness, number of tries, time taken
     */
    private function calculateNewRating(
        float $currentRating,
        bool $correct,
        int $tries = 1,
        int $duration_ms = 0
    ): float {
        // Base outcome (1 = win, 0 = loss)
        $outcome = $correct ? 1 : 0;

        // Expected outcome (50% for equal ratings)
        $expectedOutcome = 0.5;

        // Adjustment for multiple tries (fewer tries = higher difficulty)
        $triesMultiplier = match (true) {
            $tries === 1 => 1.0,  // Perfect on first try
            $tries === 2 => 0.8,  // Took 2 tries
            $tries === 3 => 0.6,  // Took 3 tries
            default => 0.4,       // 4+ tries
        };

        // Adjustment for speed (faster = higher difficulty)
        $speedMultiplier = match (true) {
            $duration_ms < 10000 => 1.2,   // Very fast (<10s)
            $duration_ms < 20000 => 1.0,   // Normal (10-20s)
            $duration_ms < 45000 => 0.8,   // Slow (20-45s)
            default => 0.6,                // Very slow (>45s)
        };

        // Combined multiplier
        $multiplier = $triesMultiplier * $speedMultiplier;

        // Elo formula: new rating = current + K * multiplier * (outcome - expected)
        $ratingChange = self::K_FACTOR * $multiplier * ($outcome - $expectedOutcome);

        return max(600, min(1400, $currentRating + $ratingChange)); // Clamp 600-1400
    }

    /**
     * Get recommended difficulty range for child in a game
     */
    public function getRecommendedDifficultyRange(Child $child, string $game): array
    {
        // Get child's average difficulty rating in this game
        $avgRating = BeszedContentDifficulty::where('child_id', $child->id)
            ->where('game', $game)
            ->whereNotNull('difficulty_rating')
            ->avg('difficulty_rating') ?? self::BASE_RATING;

        // Recommended range: child's level ± 100 Elo points
        return [
            'min' => max(600, $avgRating - 100),
            'ideal' => $avgRating,
            'max' => min(1400, $avgRating + 100),
        ];
    }

    /**
     * Get items that match child's difficulty level
     */
    public function getItemsForChild(Child $child, string $game, int $limit = 5)
    {
        $range = $this->getRecommendedDifficultyRange($child, $game);

        return BeszedContentItem::where('game', $game)
            ->where('active', true)
            ->where('status', 'live')
            ->whereNotIn('id', function ($query) use ($child, $game) {
                $query->select('content_item_id')
                    ->from('beszed_content_difficulties')
                    ->where('child_id', $child->id)
                    ->where('game', $game);
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Calculate child's overall skill level in a game (0-100)
     */
    public function getChildSkillLevel(Child $child, string $game): int
    {
        $winRate = BeszedContentDifficulty::where('child_id', $child->id)
            ->where('game', $game)
            ->avg('win_rate') ?? 0;

        // Scale 0-100 based on win rate and difficulty
        $avgRating = BeszedContentDifficulty::where('child_id', $child->id)
            ->where('game', $game)
            ->avg('difficulty_rating') ?? self::BASE_RATING;

        $difficultyScore = ($avgRating - 600) / 8; // Normalize to 0-100
        $combinedScore = ($winRate * 0.6) + ($difficultyScore * 0.4);

        return max(0, min(100, intval($combinedScore)));
    }
}
