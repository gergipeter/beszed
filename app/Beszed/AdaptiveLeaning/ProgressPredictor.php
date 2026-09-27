<?php

namespace App\Beszed\AdaptiveLearning;

use App\Models\BeszedAttempt;
use App\Models\BeszedContentDifficulty;
use App\Models\BeszedProgressPrediction;
use App\Models\BeszedSpeechRecording;
use App\Models\Child;
use Carbon\Carbon;

/**
 * Progress Prediction Engine
 * Uses historical data to forecast when child will reach goals
 */
class ProgressPredictor
{
    /**
     * Predict pronunciation progress
     */
    public function predictPronunciationProgress(Child $child): ?array
    {
        // Get last 30 days of pronunciation scores
        $thirtyDaysAgo = now()->subDays(30);
        $history = BeszedSpeechRecording::where('child_id', $child->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->orderBy('created_at')
            ->get(['pronunciation_score', 'created_at']);

        if ($history->count() < 3) {
            return null; // Not enough data
        }

        // Extract scores and dates
        $scores = $history->pluck('pronunciation_score')->toArray();
        $dates = $history->pluck('created_at')->toArray();

        // Calculate trend (linear regression)
        $trend = $this->calculateTrend($scores);

        // Current level
        $currentLevel = end($scores);

        // Days to reach 80% (typical therapy goal)
        $targetLevel = 80;
        $daysToTarget = $this->estimateDaysToTarget($currentLevel, $targetLevel, $trend);

        // Confidence (0-1)
        $confidence = $this->calculateConfidence(count($scores), $trend['r_squared']);

        $prediction = BeszedProgressPrediction::create([
            'child_id' => $child->id,
            'metric' => 'pronunciation',
            'current_level' => intval($currentLevel),
            'predicted_level' => $targetLevel,
            'weeks_to_goal' => intval(ceil($daysToTarget / 7)),
            'confidence' => round($confidence, 2),
            'historical_data' => json_encode(['scores' => $scores]),
            'model_params' => json_encode($trend),
            'predicted_at' => now(),
        ]);

        return [
            'metric' => 'pronunciation',
            'current' => intval($currentLevel),
            'target' => $targetLevel,
            'weeks_to_target' => intval(ceil($daysToTarget / 7)),
            'confidence' => round($confidence * 100, 1),
            'trend' => $trend['slope'] > 0 ? 'improving' : 'declining',
            'trend_strength' => abs($trend['slope']),
        ];
    }

    /**
     * Predict fluency progress
     */
    public function predictFluencyProgress(Child $child): ?array
    {
        $thirtyDaysAgo = now()->subDays(30);
        $history = BeszedSpeechRecording::where('child_id', $child->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->orderBy('created_at')
            ->get(['fluency_score', 'created_at']);

        if ($history->count() < 3) {
            return null;
        }

        $scores = $history->pluck('fluency_score')->toArray();
        $trend = $this->calculateTrend($scores);
        $currentLevel = end($scores);
        $targetLevel = 80;
        $daysToTarget = $this->estimateDaysToTarget($currentLevel, $targetLevel, $trend);
        $confidence = $this->calculateConfidence(count($scores), $trend['r_squared']);

        BeszedProgressPrediction::create([
            'child_id' => $child->id,
            'metric' => 'fluency',
            'current_level' => intval($currentLevel),
            'predicted_level' => $targetLevel,
            'weeks_to_goal' => intval(ceil($daysToTarget / 7)),
            'confidence' => round($confidence, 2),
            'historical_data' => json_encode(['scores' => $scores]),
            'model_params' => json_encode($trend),
            'predicted_at' => now(),
        ]);

        return [
            'metric' => 'fluency',
            'current' => intval($currentLevel),
            'target' => $targetLevel,
            'weeks_to_target' => intval(ceil($daysToTarget / 7)),
            'confidence' => round($confidence * 100, 1),
            'trend' => $trend['slope'] > 0 ? 'improving' : 'declining',
        ];
    }

    /**
     * Predict game skill level progress
     */
    public function predictGameProgress(Child $child, string $game): ?array
    {
        $thirtyDaysAgo = now()->subDays(30);
        $history = BeszedAttempt::where('child_id', $child->id)
            ->where('game', $game)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->orderBy('created_at')
            ->get(['correct', 'created_at']);

        if ($history->count() < 5) {
            return null;
        }

        // Convert to success rate (rolling 7-day average)
        $scores = $this->calculateRollingWinRate($history, 7);
        $trend = $this->calculateTrend($scores);
        $currentLevel = end($scores);

        BeszedProgressPrediction::create([
            'child_id' => $child->id,
            'metric' => "game_$game",
            'current_level' => intval($currentLevel),
            'predicted_level' => 80,
            'weeks_to_goal' => max(0, intval(ceil($this->estimateDaysToTarget($currentLevel, 80, $trend) / 7))),
            'confidence' => round($this->calculateConfidence(count($scores), $trend['r_squared']), 2),
            'historical_data' => json_encode(['game' => $game, 'scores' => $scores]),
            'model_params' => json_encode($trend),
            'predicted_at' => now(),
        ]);

        return [
            'game' => $game,
            'current_level' => intval($currentLevel),
            'weeks_to_mastery' => max(0, intval(ceil($this->estimateDaysToTarget($currentLevel, 80, $trend) / 7))),
            'confidence' => round($this->calculateConfidence(count($scores), $trend['r_squared']) * 100, 1),
        ];
    }

    /**
     * Linear regression: calculate slope and R²
     */
    private function calculateTrend(array $scores): array
    {
        $n = count($scores);
        $sumX = $n * ($n - 1) / 2;
        $sumY = array_sum($scores);
        $sumXY = 0;
        $sumX2 = 0;

        foreach ($scores as $i => $y) {
            $sumXY += $i * $y;
            $sumX2 += $i * $i;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        // R² calculation
        $yMean = $sumY / $n;
        $ssTotal = 0;
        $ssResidual = 0;

        foreach ($scores as $i => $y) {
            $predicted = $intercept + $slope * $i;
            $ssTotal += ($y - $yMean) ** 2;
            $ssResidual += ($y - $predicted) ** 2;
        }

        $rSquared = $ssTotal > 0 ? 1 - ($ssResidual / $ssTotal) : 0;

        return [
            'slope' => round($slope, 4),
            'intercept' => round($intercept, 2),
            'r_squared' => round($rSquared, 3),
        ];
    }

    /**
     * Estimate days to reach target level
     */
    private function estimateDaysToTarget(float $current, float $target, array $trend): float
    {
        if ($trend['slope'] == 0) {
            return 999; // Not moving
        }

        $daysNeeded = ($target - $current) / $trend['slope'];

        return max(1, $daysNeeded);
    }

    /**
     * Calculate prediction confidence (0-1)
     */
    private function calculateConfidence(int $dataPoints, float $rSquared): float
    {
        // More data = higher confidence
        $dataConfidence = min(1.0, $dataPoints / 30);

        // Better R² = higher confidence
        $fitConfidence = max(0.3, $rSquared); // Min 0.3 confidence even with poor fit

        return ($dataConfidence * 0.6) + ($fitConfidence * 0.4);
    }

    /**
     * Calculate rolling win rate
     */
    private function calculateRollingWinRate($attempts, int $windowDays): array
    {
        $byDay = $attempts->groupBy(function ($attempt) {
            return $attempt->created_at->toDateString();
        });

        $scores = [];
        foreach ($byDay as $dayAttempts) {
            $correct = $dayAttempts->where('correct', true)->count();
            $total = $dayAttempts->count();
            $winRate = ($correct / $total) * 100;
            $scores[] = $winRate;
        }

        return $scores;
    }
}
