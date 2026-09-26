<?php

namespace App\Beszed;

use App\Models\BeszedAttempt;
use App\Models\BeszedDailyPath;
use App\Models\Child;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * "Mai kaland": three games Csillám suggests for the day. The first is the one
 * that needs practice most (lowest first-try share lately), the others add
 * games not played for a while, each from a different skill area. The choice
 * is fixed for the day and the same on every device.
 */
class DailyPath
{
    public const SIZE = 3;

    /** Answers in the last two weeks before a game counts as weak or strong. */
    private const MIN_ANSWERS = 5;

    /** Today's path, created on first ask. */
    public function today(Child $child): BeszedDailyPath
    {
        $day = $this->day(CarbonImmutable::now());

        return BeszedDailyPath::firstOrCreate(
            ['child_id' => $child->id, 'day' => $day],
            ['games' => $this->pick($child, $day), 'done' => []],
        );
    }

    /**
     * A finished game ticks its step on the path of the day it was played
     * (offline results arrive later with their own time).
     *
     * @return array{day: string, games: string[], done: string[], completed: bool, ticked: bool, just_completed: bool}|null
     */
    public function markPlayed(Child $child, string $game, CarbonImmutable $playedAt): ?array
    {
        $path = BeszedDailyPath::where('child_id', $child->id)->where('day', $this->day($playedAt))->first();
        if (! $path || ! in_array($game, $path->games, true)) {
            return null;
        }

        $ticked = ! in_array($game, $path->done, true);
        $justCompleted = false;
        if ($ticked) {
            $path->done = [...$path->done, $game];
            if (! $path->completed_at && array_diff($path->games, $path->done) === []) {
                $path->completed_at = now();
                $justCompleted = true;
            }
            $path->save();
        }

        return $this->present($path) + ['ticked' => $ticked, 'just_completed' => $justCompleted];
    }

    /** @return array{day: string, games: string[], done: string[], completed: bool} */
    public function present(BeszedDailyPath $path): array
    {
        return [
            'day' => (string) $path->day,
            'games' => $path->games,
            'done' => array_values(array_intersect($path->games, $path->done)),
            'completed' => (bool) $path->completed_at,
        ];
    }

    /** @return string[] game ids */
    private function pick(Child $child, string $day): array
    {
        $games = array_keys(config('beszed.games'));
        $areaOf = collect(config('beszed_skills.areas'))
            ->flatMap(fn ($a, $key) => collect($a['games'])->mapWithKeys(fn ($g) => [$g => $key]))->all();
        $stats = $this->recent($child);

        // Seeded by child and day: stable all day, different tomorrow.
        mt_srand(crc32("{$child->id}|$day"));
        $jitter = collect($games)->mapWithKeys(fn ($g) => [$g => mt_rand(0, 1000) / 1000])->all();
        mt_srand();

        $yesterday = BeszedDailyPath::where('child_id', $child->id)
            ->where('day', CarbonImmutable::parse($day)->subDay()->toDateString())->first()?->games ?? [];

        $score = function (string $g) use ($stats, $jitter, $yesterday): float {
            $s = $stats->get($g);
            $answers = (int) ($s->answers ?? 0);
            // Weak games score high; unknown ones sit in the middle.
            $weakness = $answers >= self::MIN_ANSWERS ? 1 - $s->first_try / $answers : 0.5;
            $daysAway = $s?->last_played ? min(14, CarbonImmutable::parse($s->last_played)->diffInDays(now())) : 14;

            return $weakness + $daysAway / 14 * 0.5 + $jitter[$g] * 0.4 - (in_array($g, $yesterday, true) ? 0.5 : 0);
        };

        $picked = [];
        $usedAreas = [];
        $ranked = collect($games)->sortByDesc($score)->values();
        foreach ($ranked as $g) {
            $area = $areaOf[$g] ?? $g;
            if (! in_array($area, $usedAreas, true)) {
                $picked[] = $g;
                $usedAreas[] = $area;
            }
            if (count($picked) === self::SIZE) {
                break;
            }
        }

        return $picked;
    }

    /** Answers, first-try answers and last play per game over the last two weeks. */
    private function recent(Child $child): Collection
    {
        return BeszedAttempt::where('child_id', $child->id)
            ->where('created_at', '>=', now()->subDays(14))
            ->groupBy('game')
            ->selectRaw('game, COUNT(*) as answers, SUM(CASE WHEN correct AND tries = 1 THEN 1 ELSE 0 END) as first_try, MAX(created_at) as last_played')
            ->get()->keyBy('game');
    }

    /** The child's local calendar day (rewards timezone), like streaks and the daily goal. */
    private function day(CarbonImmutable $at): string
    {
        return $at->setTimezone(config('beszed.rewards.timezone'))->toDateString();
    }
}
