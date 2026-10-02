<?php

namespace App\Beszed;

use App\Models\BeszedAttempt;
use App\Models\Child;

/**
 * "Hang a nap" (sound of the day): a nudge towards the game the child
 * currently struggles with most. Picked fresh on every ask — unlike
 * DailyPath, nothing is persisted or tracked as done; tapping it just
 * launches that game like any tile on the hub.
 */
class Spotlight
{
    /** Answers in the last two weeks before a game counts as weak enough to suggest. */
    private const MIN_ANSWERS = 5;

    /** First-try share below which a game counts as weak (under two medals: config beszed.rewards.medals). */
    private const WEAK_BELOW = 0.75;

    /** @return array{game: string}|null null when there isn't enough data yet, or the child does well everywhere. */
    public function pick(Child $child): ?array
    {
        $stats = BeszedAttempt::where('child_id', $child->id)
            ->where('created_at', '>=', now()->subDays(14))
            ->groupBy('game')
            ->selectRaw('game, COUNT(*) as answers, SUM(CASE WHEN correct AND tries = 1 THEN 1 ELSE 0 END) as first_try')
            ->get()
            ->filter(fn ($s) => $s->answers >= self::MIN_ANSWERS)
            ->filter(fn ($s) => array_key_exists($s->game, config('beszed.games')))
            // only a game the child was really weaker at: never one they already do well
            ->filter(fn ($s) => $s->first_try / $s->answers < self::WEAK_BELOW);

        if ($stats->isEmpty()) {
            return null;
        }

        $weakest = $stats->sortBy([fn ($a, $b) => $a->first_try / $a->answers <=> $b->first_try / $b->answers, fn ($a, $b) => $b->answers <=> $a->answers])->first();

        return ['game' => $weakest->game];
    }
}
