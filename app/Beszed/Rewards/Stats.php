<?php

namespace App\Beszed\Rewards;

use App\Models\BeszedAttempt;
use App\Models\BeszedDailyPath;
use App\Models\BeszedSession;
use App\Models\Child;
use Carbon\CarbonImmutable;

/** Everything the reward rules look at, gathered with a few queries. */
final class Stats
{
    /**
     * @param  array<string, int>  $gameSessions  finished sessions per game
     * @param  array<string, float>  $gameBest  best first-try share per game
     * @param  string[]  $recentDays  the last 14 local calendar days played, oldest first
     */
    public function __construct(
        public readonly int $stars,
        public readonly int $sessions,
        public readonly int $perfectSessions,
        public readonly int $streak,
        public readonly bool $playedToday,
        public readonly int $today,
        public readonly array $gameSessions,
        public readonly array $gameBest,
        public readonly int $dailyPaths = 0,
        public readonly array $recentDays = [],
    ) {}

    public static function for(Child $child, string $timezone): self
    {
        $perGame = BeszedSession::query()
            ->where('child_id', $child->id)
            ->groupBy('game')
            ->selectRaw('game, COUNT(*) as n, MAX(first_try * 1.0 / rounds) as best, SUM(CASE WHEN first_try = rounds THEN 1 ELSE 0 END) as perfect')
            ->get()->keyBy('game');

        $now = CarbonImmutable::now($timezone);
        // A year back is plenty for a streak; converts each finish time to a local calendar day.
        $days = BeszedSession::query()
            ->where('child_id', $child->id)
            ->where('completed_at', '>=', $now->subYear())
            ->pluck('completed_at')
            ->map(fn ($at) => CarbonImmutable::parse($at)->setTimezone($timezone)->toDateString());

        $played = $days->unique()->flip();
        $today = $now->toDateString();
        $cursor = $played->has($today) ? $now : $now->subDay();
        $streak = 0;
        while ($played->has($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        $recentDays = collect(range(13, 0))
            ->map(fn ($daysAgo) => $now->subDays($daysAgo)->toDateString())
            ->map(fn ($date) => ['date' => $date, 'played' => $played->has($date)])
            ->all();

        return new self(
            stars: BeszedAttempt::where('child_id', $child->id)->where('correct', true)->count(),
            sessions: (int) $perGame->sum('n'),
            perfectSessions: (int) $perGame->sum('perfect'),
            streak: $streak,
            playedToday: $played->has($today),
            today: $days->filter(fn ($d) => $d === $today)->count(),
            gameSessions: $perGame->map(fn ($g) => (int) $g->n)->all(),
            gameBest: $perGame->map(fn ($g) => (float) $g->best)->all(),
            dailyPaths: BeszedDailyPath::where('child_id', $child->id)->whereNotNull('completed_at')->count(),
            recentDays: $recentDays,
        );
    }
}
