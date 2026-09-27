<?php

namespace App\Beszed\Reports;

use App\Models\Child;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;

/**
 * Report Generator
 * Creates PDF reports with charts and insights
 */
class ReportGenerator
{
    /**
     * Generate weekly progress report
     */
    public function generateWeeklyReport(Child $child): string
    {
        $data = [
            'child' => $child,
            'week_start' => now()->subWeek()->startOfDay(),
            'week_end' => now()->endOfDay(),
            'metrics' => $this->getWeeklyMetrics($child),
            'speech_data' => $this->getWeeklySpeechData($child),
            'game_data' => $this->getWeeklyGameData($child),
            'recommendations' => $this->generateRecommendations($child),
            'generated_at' => now(),
        ];

        $pdf = Pdf::loadView('reports.weekly', $data);
        $filename = "weekly-report-{$child->id}-" . now()->format('Y-m-d') . ".pdf";
        $path = storage_path("reports/$filename");

        $pdf->save($path);

        return $path;
    }

    /**
     * Generate monthly progress report
     */
    public function generateMonthlyReport(Child $child): string
    {
        $data = [
            'child' => $child,
            'month_start' => now()->subMonth()->startOfDay(),
            'month_end' => now()->endOfDay(),
            'metrics' => $this->getMonthlyMetrics($child),
            'speech_progress' => $this->getMonthlySpeechProgress($child),
            'game_mastery' => $this->getGameMasteryData($child),
            'phoneme_analysis' => $this->getPhonemeAnalysis($child),
            'improvement_score' => $this->calculateImprovementScore($child),
            'next_goals' => $this->generateGoals($child),
            'generated_at' => now(),
        ];

        $pdf = Pdf::loadView('reports.monthly', $data);
        $filename = "monthly-report-{$child->id}-" . now()->format('Y-m') . ".pdf";
        $path = storage_path("reports/$filename");

        $pdf->save($path);

        return $path;
    }

    /**
     * Generate therapist progress note
     */
    public function generateTherapistNote(Child $child): string
    {
        $data = [
            'child' => $child,
            'session_history' => $this->getRecentSessions($child, 10),
            'pronunciation_trend' => $this->getPronunciationTrend($child),
            'fluency_trend' => $this->getFluencyTrend($child),
            'areas_of_strength' => $this->getStrengths($child),
            'areas_for_improvement' => $this->getWeaknesses($child),
            'recommendations_for_therapy' => $this->getTherapyRecommendations($child),
            'parent_engagement' => $this->getParentEngagement($child),
            'generated_at' => now(),
        ];

        $pdf = Pdf::loadView('reports.therapist-note', $data);
        $filename = "therapist-note-{$child->id}-" . now()->format('Y-m-d') . ".pdf";
        $path = storage_path("reports/$filename");

        $pdf->save($path);

        return $path;
    }

    // Helper methods for data gathering
    private function getWeeklyMetrics(Child $child): array
    {
        $week = now()->subWeek();
        $attempts = \DB::table('beszed_attempts')
            ->where('child_id', $child->id)
            ->where('created_at', '>=', $week)
            ->get();

        return [
            'total_attempts' => $attempts->count(),
            'accuracy_rate' => $attempts->count() > 0
                ? round($attempts->where('correct', true)->count() / $attempts->count() * 100, 1)
                : 0,
            'total_time_hours' => round($attempts->sum('duration_ms') / 1000 / 60 / 60, 1),
            'games_played' => $attempts->pluck('game')->unique()->count(),
            'days_active' => $attempts->groupBy(\DB::raw('DATE(created_at)'))->count(),
        ];
    }

    private function getWeeklySpeechData(Child $child): array
    {
        $week = now()->subWeek();
        return \DB::table('beszed_speech_recordings')
            ->where('child_id', $child->id)
            ->where('created_at', '>=', $week)
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('AVG(pronunciation_score) as pronunciation')
            ->selectRaw('AVG(fluency_score) as fluency')
            ->selectRaw('AVG(clarity_score) as clarity')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function getWeeklyGameData(Child $child): array
    {
        $week = now()->subWeek();
        return \DB::table('beszed_attempts')
            ->where('child_id', $child->id)
            ->where('created_at', '>=', $week)
            ->selectRaw('game')
            ->selectRaw('COUNT(*) as attempts')
            ->selectRaw('SUM(CASE WHEN correct THEN 1 ELSE 0 END) as correct')
            ->groupBy('game')
            ->orderByDesc('attempts')
            ->limit(10)
            ->get()
            ->map(fn ($g) => [
                'game' => $g->game,
                'attempts' => $g->attempts,
                'accuracy' => round($g->correct / $g->attempts * 100, 1),
            ])
            ->toArray();
    }

    private function getMonthlyMetrics(Child $child): array
    {
        $month = now()->subMonth();
        $attempts = \DB::table('beszed_attempts')
            ->where('child_id', $child->id)
            ->where('created_at', '>=', $month)
            ->get();

        return [
            'total_sessions' => \DB::table('beszed_sessions')
                ->where('child_id', $child->id)
                ->where('completed_at', '>=', $month)
                ->count(),
            'total_attempts' => $attempts->count(),
            'overall_accuracy' => $attempts->count() > 0
                ? round($attempts->where('correct', true)->count() / $attempts->count() * 100, 1)
                : 0,
            'improvement_from_start' => $this->calculateMonthlyImprovement($child),
            'avg_session_duration' => round(\DB::table('beszed_sessions')
                ->where('child_id', $child->id)
                ->where('completed_at', '>=', $month)
                ->avg('duration_ms') / 1000 / 60, 1),
        ];
    }

    private function calculateImprovementScore(Child $child): int
    {
        $month = now()->subMonth();
        $firstWeek = \DB::table('beszed_attempts')
            ->where('child_id', $child->id)
            ->where('created_at', '>=', $month)
            ->where('created_at', '<', $month->addWeeks(1))
            ->where('correct', true)
            ->count() ?: 1;

        $lastWeek = \DB::table('beszed_attempts')
            ->where('child_id', $child->id)
            ->where('created_at', '>=', now()->subWeek())
            ->where('correct', true)
            ->count() ?: 1;

        return round((($lastWeek - $firstWeek) / $firstWeek) * 100);
    }

    private function getPhonemeAnalysis(Child $child): array
    {
        return \DB::table('beszed_phoneme_progress')
            ->where('child_id', $child->id)
            ->where('attempts', '>', 0)
            ->orderBy('accuracy')
            ->get()
            ->map(fn ($p) => [
                'phoneme' => $p->phoneme,
                'accuracy' => $p->accuracy,
                'attempts' => $p->attempts,
            ])
            ->toArray();
    }

    private function generateRecommendations(Child $child): array
    {
        return [
            'Continue practicing high-difficulty games to strengthen skills',
            'Focus on pronunciation accuracy in weak phoneme areas',
            'Maintain daily practice routine for consistency',
        ];
    }

    private function generateGoals(Child $child): array
    {
        return [
            'Achieve 80% accuracy in pronunciation',
            'Complete advanced levels in 3 games',
            'Improve fluency score by 15%',
        ];
    }

    private function getRecentSessions(Child $child, int $limit): array
    {
        return \DB::table('beszed_sessions')
            ->where('child_id', $child->id)
            ->latest('completed_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function getPronunciationTrend(Child $child): string
    {
        $recent = \DB::table('beszed_speech_recordings')
            ->where('child_id', $child->id)
            ->where('created_at', '>=', now()->subWeeks(2))
            ->avg('pronunciation_score') ?? 0;

        $older = \DB::table('beszed_speech_recordings')
            ->where('child_id', $child->id)
            ->where('created_at', '<', now()->subWeeks(2))
            ->where('created_at', '>=', now()->subMonth())
            ->avg('pronunciation_score') ?? 0;

        return $recent > $older ? 'Improving ↑' : ($recent < $older ? 'Declining ↓' : 'Stable →');
    }

    private function getFluencyTrend(Child $child): string
    {
        $recent = \DB::table('beszed_speech_recordings')
            ->where('child_id', $child->id)
            ->where('created_at', '>=', now()->subWeeks(2))
            ->avg('fluency_score') ?? 0;

        $older = \DB::table('beszed_speech_recordings')
            ->where('child_id', $child->id)
            ->where('created_at', '<', now()->subWeeks(2))
            ->where('created_at', '>=', now()->subMonth())
            ->avg('fluency_score') ?? 0;

        return $recent > $older ? 'Improving ↑' : ($recent < $older ? 'Declining ↓' : 'Stable →');
    }

    private function getStrengths(Child $child): array
    {
        $strong = \DB::table('beszed_phoneme_progress')
            ->where('child_id', $child->id)
            ->where('accuracy', '>=', 80)
            ->pluck('phoneme')
            ->toArray();

        return $strong ?: ['Consistent practice', 'Good engagement'];
    }

    private function getWeaknesses(Child $child): array
    {
        $weak = \DB::table('beszed_phoneme_progress')
            ->where('child_id', $child->id)
            ->where('accuracy', '<', 60)
            ->pluck('phoneme')
            ->toArray();

        return $weak ?: ['All areas showing improvement'];
    }

    private function getTherapyRecommendations(Child $child): array
    {
        return [
            'Continue structured practice with recommended games',
            'Focus on weak phoneme areas identified in analysis',
            'Maintain consistent daily engagement for best results',
            'Celebrate achievements to boost motivation',
        ];
    }

    private function getParentEngagement(Child $child): array
    {
        $week = now()->subWeek();
        $attempts = \DB::table('beszed_attempts')
            ->where('child_id', $child->id)
            ->where('created_at', '>=', $week)
            ->count();

        return [
            'weekly_attempts' => $attempts,
            'consistency' => $attempts > 20 ? 'Excellent' : ($attempts > 10 ? 'Good' : 'Needs improvement'),
            'engagement_level' => round(($attempts / 30) * 100, 0) . '%',
        ];
    }

    private function getMonthlySpeechProgress(Child $child): array
    {
        return \DB::table('beszed_speech_recordings')
            ->where('child_id', $child->id)
            ->where('created_at', '>=', now()->subMonth())
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('AVG(pronunciation_score) as pronunciation')
            ->selectRaw('AVG(fluency_score) as fluency')
            ->selectRaw('COUNT(*) as recordings')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function getGameMasteryData(Child $child): array
    {
        return \DB::table('beszed_content_difficulties')
            ->where('child_id', $child->id)
            ->orderByDesc('win_rate')
            ->limit(10)
            ->get()
            ->map(fn ($g) => [
                'game' => $g->game,
                'skill_level' => round($g->difficulty_rating / 10),
                'win_rate' => round($g->win_rate, 1),
            ])
            ->toArray();
    }

    private function calculateMonthlyImprovement(Child $child): float
    {
        $month = now()->subMonth();
        $first = \DB::table('beszed_attempts')
            ->where('child_id', $child->id)
            ->where('created_at', '>=', $month)
            ->where('created_at', '<', $month->addWeeks(1))
            ->where('correct', true)
            ->count() ?: 1;

        $last = \DB::table('beszed_attempts')
            ->where('child_id', $child->id)
            ->where('created_at', '>=', now()->subWeek())
            ->where('correct', true)
            ->count() ?: 1;

        return round((($last - $first) / $first) * 100, 1);
    }
}
