<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\AdaptiveLearning\DifficultyRater;
use App\Beszed\AdaptiveLearning\GameRecommender;
use App\Beszed\AdaptiveLearning\ProgressPredictor;
use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdaptiveController extends Controller
{
    use AuthorizesChild;

    public function __construct(
        private DifficultyRater $rater,
        private GameRecommender $recommender,
        private ProgressPredictor $predictor
    ) {}

    /**
     * Get personalized game recommendations
     */
    public function recommendGames(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $limit = $request->input('limit', 5);
        $recommendations = $this->recommender->recommendGames($child, $limit);

        return response()->json([
            'recommendations' => $recommendations,
            'total' => $recommendations->count(),
        ]);
    }

    /**
     * Get child's skill level in a game
     */
    public function gameSkillLevel(Request $request, Child $child, string $game): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $skillLevel = $this->rater->getChildSkillLevel($child, $game);
        $range = $this->rater->getRecommendedDifficultyRange($child, $game);

        return response()->json([
            'game' => $game,
            'skill_level' => $skillLevel,
            'skill_label' => match (true) {
                $skillLevel < 20 => 'Beginner',
                $skillLevel < 40 => 'Novice',
                $skillLevel < 60 => 'Intermediate',
                $skillLevel < 80 => 'Advanced',
                default => 'Master',
            },
            'difficulty_range' => $range,
            'next_challenges' => $this->rater->getItemsForChild($child, $game, 3),
        ]);
    }

    /**
     * Predict pronunciation progress
     */
    public function predictPronunciation(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $prediction = $this->predictor->predictPronunciationProgress($child);

        if (!$prediction) {
            return response()->json(['error' => 'Not enough data yet'], 422);
        }

        return response()->json($prediction);
    }

    /**
     * Predict fluency progress
     */
    public function predictFluency(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $prediction = $this->predictor->predictFluencyProgress($child);

        if (!$prediction) {
            return response()->json(['error' => 'Not enough data yet'], 422);
        }

        return response()->json($prediction);
    }

    /**
     * Predict progress in a specific game
     */
    public function predictGameProgress(Request $request, Child $child, string $game): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $prediction = $this->predictor->predictGameProgress($child, $game);

        if (!$prediction) {
            return response()->json(['error' => 'Not enough data yet'], 422);
        }

        return response()->json($prediction);
    }

    /**
     * Get overall therapy progress summary
     */
    public function progressSummary(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $pronunciation = $this->predictor->predictPronunciationProgress($child);
        $fluency = $this->predictor->predictFluencyProgress($child);
        $recommendations = $this->recommender->recommendGames($child, 3);

        return response()->json([
            'child' => [
                'name' => $child->name,
                'id' => $child->id,
            ],
            'predictions' => [
                'pronunciation' => $pronunciation,
                'fluency' => $fluency,
            ],
            'recommendations' => $recommendations->toArray(),
            'overall_progress' => $this->calculateOverallProgress($child),
            'next_steps' => $this->getNextSteps($child),
        ]);
    }

    /**
     * Calculate overall therapy progress (0-100)
     */
    private function calculateOverallProgress(Child $child): int
    {
        $pronunciation = $this->predictor->predictPronunciationProgress($child);
        $fluency = $this->predictor->predictFluencyProgress($child);

        $scores = [];
        if ($pronunciation) {
            $scores[] = $pronunciation['current'];
        }
        if ($fluency) {
            $scores[] = $fluency['current'];
        }

        return $scores ? intval(array_sum($scores) / count($scores)) : 0;
    }

    /**
     * Get next recommended actions for therapy
     */
    private function getNextSteps(Child $child): array
    {
        $steps = [];

        // Check weak phonemes
        $weakPhonemes = $child->phonemeProgress()
            ->where('accuracy', '<', 60)
            ->orderBy('accuracy')
            ->limit(2)
            ->get();

        foreach ($weakPhonemes as $phoneme) {
            $steps[] = [
                'action' => 'practice_phoneme',
                'phoneme' => $phoneme->phoneme,
                'current_accuracy' => $phoneme->accuracy,
                'priority' => 'high',
            ];
        }

        // Check games to progress
        $readyToProgress = $child->contentDifficulties()
            ->where('win_rate', '>=', 70)
            ->where('attempts', '>=', 5)
            ->limit(2)
            ->get();

        foreach ($readyToProgress as $diff) {
            $steps[] = [
                'action' => 'progress_to_next_level',
                'game' => $diff->game,
                'current_level' => $diff->difficulty_rating,
                'priority' => 'medium',
            ];
        }

        return $steps;
    }
}
