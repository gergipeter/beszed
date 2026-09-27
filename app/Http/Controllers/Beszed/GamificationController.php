<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\Gamification\AchievementEngine;
use App\Beszed\Gamification\LeaderboardService;
use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GamificationController extends Controller
{
    use AuthorizesChild;

    public function __construct(
        private LeaderboardService $leaderboard,
        private AchievementEngine $achievements
    ) {}

    /**
     * Get family leaderboard
     */
    public function familyLeaderboard(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $leaderboard = $this->leaderboard->getFamilyLeaderboard($child->parent_id);

        return response()->json([
            'type' => 'family',
            'entries' => $leaderboard,
            'child_rank' => $this->leaderboard->getChildRank($child, 'family'),
        ]);
    }

    /**
     * Get classroom leaderboard
     */
    public function classroomLeaderboard(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        if (!$child->classroom_id) {
            return response()->json(['error' => 'Child not in a classroom'], 400);
        }

        $leaderboard = $this->leaderboard->getClassroomLeaderboard($child->classroom_id);

        return response()->json([
            'type' => 'classroom',
            'entries' => $leaderboard,
            'child_rank' => $this->leaderboard->getChildRank($child, 'classroom'),
        ]);
    }

    /**
     * Get regional leaderboard
     */
    public function regionalLeaderboard(Request $request): JsonResponse
    {
        $leaderboard = $this->leaderboard->getRegionalLeaderboard(100);

        return response()->json([
            'type' => 'regional',
            'entries' => $leaderboard,
        ]);
    }

    /**
     * Get child's achievements
     */
    public function achievements(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $earned = $this->achievements->getAchievements($child);

        return response()->json([
            'earned' => $earned,
            'total_earned' => count($earned),
        ]);
    }

    /**
     * Check for new achievements (called after attempts)
     */
    public function checkAchievements(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $new = $this->achievements->checkAchievements($child);

        return response()->json([
            'new_achievements' => $new,
            'count' => count($new),
        ]);
    }

    /**
     * Update leaderboard score
     */
    public function updateScore(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $score = $this->leaderboard->calculateScore($child);
        $this->leaderboard->updateScore($child, $score);

        return response()->json([
            'score' => $score,
            'ranks' => [
                'family' => $this->leaderboard->getChildRank($child, 'family'),
                'classroom' => $this->leaderboard->getChildRank($child, 'classroom'),
                'regional' => $this->leaderboard->getChildRank($child, 'regional'),
            ],
        ]);
    }
}
