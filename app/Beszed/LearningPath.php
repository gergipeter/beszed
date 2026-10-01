<?php

namespace App\Beszed;

use App\Models\BeszedAttempt;
use App\Models\Child;
use Illuminate\Support\Facades\DB;

/**
 * "Utazás": the games of each skill area as a track, in the order config/beszed_skills.php lists
 * them, with where the child stands on every step and what to play next.
 *
 * A step is `new` (never played), `learning`, or `mastered`: at the game's top level, with
 * clean first-try answers lately. `held` marks a step where the free plan's top level is reached
 * and the game has more levels behind it. Like the progress report, this describes how the
 * games went; it is not an assessment.
 */
class LearningPath
{
    /** Recent answers (days) that count towards mastery. */
    private const RECENT_DAYS = 30;

    public function __construct(private Leveler $leveler) {}

    public function for(Child $child): array
    {
        $cfg = config('beszed_skills');
        $games = config('beszed.games');
        $cap = $this->leveler->cap($child);

        $total = BeszedAttempt::where('child_id', $child->id)->groupBy('game')->selectRaw('game, COUNT(*) as n')->pluck('n', 'game');
        $recent = BeszedAttempt::where('child_id', $child->id)->where('created_at', '>=', now()->subDays(self::RECENT_DAYS))
            ->groupBy('game')
            ->select('game', DB::raw('COUNT(*) as rounds'), DB::raw('SUM(CASE WHEN correct AND tries = 1 THEN 1 ELSE 0 END) as first_try'), DB::raw('MAX(created_at) as last_played'))
            ->get()->keyBy('game');
        $levels = $child->beszedLevels()->pluck('level', 'game');

        $areas = collect($cfg['areas'])->map(function ($area, $key) use ($cfg, $games, $cap, $total, $recent, $levels) {
            $steps = collect($area['games'])->filter(fn ($id) => isset($games[$id]))->values()
                ->map(fn ($id) => $this->step($id, $games[$id], $cfg, $cap, (int) ($total[$id] ?? 0), $recent->get($id), $levels[$id] ?? null));
            $next = $steps->first(fn ($s) => $s['state'] !== 'mastered');

            return [
                'key' => $key,
                'label' => $area['label'],
                'emoji' => $area['emoji'],
                'steps' => $steps->all(),
                'mastered' => $steps->where('state', 'mastered')->count(),
                'total' => $steps->count(),
                'next' => $next['id'] ?? null,
            ];
        })->values();

        return ['areas' => $areas->all(), 'recommended' => $this->recommended($areas->all())];
    }

    private function step(string $id, array $game, array $cfg, ?int $cap, int $answers, ?object $recent, ?int $stored): array
    {
        $adaptive = $game['adaptive'] ?? null;
        $level = $adaptive ? ($stored ?? $adaptive['start']) : null;
        $rounds = (int) ($recent->rounds ?? 0);
        $rate = $rounds >= $cfg['min_answers'] ? round($recent->first_try / $rounds, 2) : null;
        $atTop = ! $adaptive || $level >= $adaptive['max'];

        return [
            'id' => $id,
            'name' => $game['name'],
            'emoji' => $game['emoji'],
            'color' => $game['color'],
            'level' => $adaptive && $cap !== null ? min($level, $cap) : $level,
            'maxLevel' => $adaptive['max'] ?? null,
            'firstTryRate' => $rate,
            'state' => match (true) {
                $answers === 0 => 'new',
                $atTop && $rate !== null && $rate >= $cfg['bands']['strong'] => 'mastered',
                default => 'learning',
            },
            'held' => $adaptive && $cap !== null && $adaptive['max'] > $cap && $level >= $cap,
            'lastPlayed' => $recent->last_played ?? null,
        ];
    }

    /**
     * What to play next: the started step with the lowest first-try share (it needs practice most);
     * with nothing started, the first step of the area with the fewest finished steps.
     *
     * @return array{area: string, game: string}|null
     */
    private function recommended(array $areas): ?array
    {
        $candidates = collect($areas)->map(function ($a) {
            $step = collect($a['steps'])->firstWhere('id', $a['next']);

            return $step ? ['area' => $a['key'], 'game' => $step['id'], 'state' => $step['state'], 'rate' => $step['firstTryRate'] ?? 0.5, 'done' => $a['mastered']] : null;
        })->filter();

        $learning = $candidates->where('state', 'learning')->sortBy('rate')->first();
        $pick = $learning ?? $candidates->sortBy('done')->first();

        return $pick ? ['area' => $pick['area'], 'game' => $pick['game']] : null;
    }
}
