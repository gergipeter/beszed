<?php

namespace App\Beszed\AdaptiveLearning;

use App\Models\BeszedAttempt;
use App\Models\BeszedContentDifficulty;
use App\Models\BeszedGameRecommendation;
use App\Models\BeszedPhonemeProgress;
use App\Models\Child;
use Illuminate\Support\Collection;

/**
 * Game Recommendation Engine
 * Suggests optimal games based on child's strengths, weaknesses, and skill level
 */
class GameRecommender
{
    public function __construct(private DifficultyRater $rater) {}

    /**
     * Get personalized game recommendations for a child
     */
    public function recommendGames(Child $child, int $limit = 5): Collection
    {
        $recommendations = [];

        // 1. Games to practice weak phonemes
        $weakPhonemeGames = $this->getWeakPhonemeGames($child);
        foreach ($weakPhonemeGames as $game) {
            $recommendations[] = [
                'game' => $game,
                'reason' => 'weakness',
                'rationale' => 'Practice weak phonemes',
                'score' => 85,
            ];
        }

        // 2. Games child is ready to progress in
        $progressionGames = $this->getProgressionGames($child);
        foreach ($progressionGames as $game) {
            $recommendations[] = [
                'game' => $game,
                'reason' => 'progression',
                'rationale' => 'Ready for next level',
                'score' => 80,
            ];
        }

        // 3. Games child should maintain (skill at 50-70%)
        $maintenanceGames = $this->getMaintenanceGames($child);
        foreach ($maintenanceGames as $game) {
            $recommendations[] = [
                'game' => $game,
                'reason' => 'maintenance',
                'rationale' => 'Maintain current skills',
                'score' => 70,
            ];
        }

        // 4. Fun/engagement games
        $funGames = $this->getFunGames($child);
        foreach ($funGames as $game) {
            $recommendations[] = [
                'game' => $game,
                'reason' => 'fun',
                'rationale' => 'High engagement',
                'score' => 60,
            ];
        }

        // Sort by score, limit, and save to DB
        $sorted = collect($recommendations)
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        foreach ($sorted as $rec) {
            BeszedGameRecommendation::create([
                'child_id' => $child->id,
                'game' => $rec['game'],
                'score' => $rec['score'],
                'reason' => $rec['reason'],
                'rationale' => $rec['rationale'],
                'recommended_at' => now(),
            ]);
        }

        return $sorted;
    }

    /**
     * Games that target weak phonemes
     */
    private function getWeakPhonemeGames(Child $child): array
    {
        // Get weak phonemes
        $weakPhonemes = BeszedPhonemeProgress::where('child_id', $child->id)
            ->where('accuracy', '<', 60)
            ->orderBy('accuracy')
            ->limit(3)
            ->pluck('phoneme');

        if ($weakPhonemes->isEmpty()) {
            return [];
        }

        // Map phonemes to games
        $phonemeGameMap = [
            '/s/' => ['mondd', 'papagaj'],
            '/r/' => ['mondd', 'papagaj'],
            '/z/' => ['mondd'],
            '/θ/' => ['mondd', 'papagaj'],
            '/tʃ/' => ['hallgasd', 'mondd'],
        ];

        $games = [];
        foreach ($weakPhonemes as $phoneme) {
            if (isset($phonemeGameMap[$phoneme])) {
                $games = array_merge($games, $phonemeGameMap[$phoneme]);
            }
        }

        return array_unique($games);
    }

    /**
     * Games child is ready to progress to the next level
     */
    private function getProgressionGames(Child $child): array
    {
        // Games where child has 70%+ win rate = ready for next level
        return BeszedContentDifficulty::where('child_id', $child->id)
            ->where('win_rate', '>=', 70)
            ->where('attempts', '>=', 5) // Must have played enough
            ->pluck('game')
            ->unique()
            ->toArray();
    }

    /**
     * Games child should maintain skill in
     */
    private function getMaintenanceGames(Child $child): array
    {
        // Games where child has 50-70% win rate
        return BeszedContentDifficulty::where('child_id', $child->id)
            ->whereBetween('win_rate', [50, 70])
            ->where('attempts', '>=', 3)
            ->pluck('game')
            ->unique()
            ->toArray();
    }

    /**
     * Games for engagement/fun (high success rate = confidence boost)
     */
    private function getFunGames(Child $child): array
    {
        // Games where child has 80%+ win rate = feels good!
        return BeszedContentDifficulty::where('child_id', $child->id)
            ->where('win_rate', '>=', 80)
            ->pluck('game')
            ->unique()
            ->toArray();
    }

    /**
     * Get games NOT yet played
     */
    public function getNewGamesSuggestions(Child $child, int $limit = 3): Collection
    {
        $allGames = collect(config('beszed.games'))->keys();
        $playedGames = BeszedAttempt::where('child_id', $child->id)
            ->pluck('game')
            ->unique();

        $newGames = $allGames->diff($playedGames)->take($limit);

        return $newGames->map(fn ($game) => [
            'game' => $game,
            'reason' => 'new',
            'score' => 50,
        ]);
    }
}
