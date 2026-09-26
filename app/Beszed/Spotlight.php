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

    /** @return array{game: string}|null null when there isn't enough data yet to tell. */
    public function pick(Child $child): ?array
    {
        $stats = BeszedAttempt::where('child_id', $child->id)
            ->where('created_at', '>=', now()->subDays(14))
            ->groupBy('game')
            ->selectRaw('game, COUNT(*) as answers, SUM(CASE WHEN correct AND tries = 1 THEN 1 ELSE 0 END) as first_try')
            ->get()
            ->filter(fn ($s) => $s->answers >= self::MIN_ANSWERS)
            ->filter(fn ($s) => array_key_exists($s->game, config('beszed.games')));

        if ($stats->isEmpty()) {
            return null;
        }

        $weakest = $stats->sortBy(fn ($s) => $s->first_try / $s->answers)->first();

        return ['game' => $weakest->game];
    }
}
