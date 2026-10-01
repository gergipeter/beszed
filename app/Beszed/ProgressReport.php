<?php

namespace App\Beszed;

use App\Models\BeszedAttempt;
use App\Models\Child;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Per-game and per-skill-area summary of a period: the parent's progress page,
 * its printout and the read-only therapist share link all show this.
 */
class ProgressReport
{
    public function for(Child $child, int $days = 30): array
    {
        $days = max(1, min($days, 365));
        $since = CarbonImmutable::now()->subDays($days);

        $current = $this->stats($child, $since);
        $previous = $this->stats($child, $since->subDays($days), $since);
        $levels = $child->beszedLevels()->pluck('level', 'game');
        $sessions = $child->beszedSessions()->where('completed_at', '>=', $since)
            ->groupBy('game')->selectRaw('game, COUNT(*) as n')->pluck('n', 'game');

        $games = collect(config('beszed.games'))->map(function ($g, $id) use ($current, $levels, $sessions) {
            $s = $current->get($id);
            $rounds = (int) ($s->rounds ?? 0);

            return [
                'id' => $id,
                'name' => $g['name'],
                'emoji' => $g['emoji'],
                'skill' => $g['skill'],
                'sessions' => (int) ($sessions[$id] ?? 0),
                'rounds' => $rounds,
                'stars' => (int) ($s->solved ?? 0),
                'firstTryRate' => $rounds ? round($s->first_try / $rounds, 2) : null,
                'solvedRate' => $rounds ? round($s->solved / $rounds, 2) : null,
                'level' => isset($g['adaptive']) ? ($levels[$id] ?? $g['adaptive']['start']) : null,
                'maxLevel' => $g['adaptive']['max'] ?? null,
                'levelProgress' => isset($g['adaptive'])
                    ? round((($levels[$id] ?? $g['adaptive']['start']) - $g['adaptive']['min']) / max(1, $g['adaptive']['max'] - $g['adaptive']['min']), 2)
                    : null,
                'lastPlayed' => $s->last_played ?? null,
            ];
        })->values();

        return [
            'child' => $child->only('id', 'name') + ['age' => AgeBands::of($child) ? AgeBands::label(AgeBands::of($child)) : null],
            'since' => $since->toDateString(),
            'days' => $days,
            'games' => $games,
            'areas' => $this->areas($games, $current, $previous),
        ];
    }

    /**
     * One row per skill area: answers-weighted first-try share now and in the
     * period before, a trend, and a band. Bands describe how the games went,
     * not how the child compares with others; there are no age norms here.
     */
    private function areas(Collection $games, Collection $current, Collection $previous): array
    {
        $cfg = config('beszed_skills');
        $byId = $games->keyBy('id');

        $rate = function (array $ids, Collection $stats) use ($cfg): array {
            $rows = collect($ids)->map(fn ($id) => $stats->get($id))->filter();
            $rounds = (int) $rows->sum('rounds');

            return [$rounds, $rounds >= $cfg['min_answers'] ? round($rows->sum('first_try') / $rounds, 2) : null];
        };

        return collect($cfg['areas'])->map(function ($area, $key) use ($cfg, $byId, $current, $previous, $rate) {
            $ids = array_values(array_filter($area['games'], fn ($id) => $byId->has($id)));
            [$rounds, $now] = $rate($ids, $current);
            [, $before] = $rate($ids, $previous);

            return [
                'key' => $key,
                'label' => $area['label'],
                'emoji' => $area['emoji'],
                'difer' => $area['difer'],
                'games' => $ids,
                'sessions' => collect($ids)->sum(fn ($id) => $byId[$id]['sessions']),
                'rounds' => $rounds,
                'stars' => collect($ids)->sum(fn ($id) => $byId[$id]['stars']),
                'firstTryRate' => $now,
                'previousRate' => $before,
                'trend' => $this->trend($now, $before, $cfg['trend_delta']),
                'band' => $this->band($now, $cfg['bands']),
            ];
        })->values()->all();
    }

    private function trend(?float $now, ?float $before, float $delta): ?string
    {
        if ($now === null || $before === null) {
            return null;
        }

        $change = round($now - $before, 2);

        return match (true) {
            $change >= $delta => 'up',
            -$change >= $delta => 'down',
            default => 'flat',
        };
    }

    private function band(?float $rate, array $bands): string
    {
        if ($rate === null) {
            return 'noData';
        }
        foreach ($bands as $band => $min) {
            if ($rate >= $min) {
                return $band;
            }
        }

        return array_key_last($bands);
    }

    /** Answers per game in [from, to). */
    private function stats(Child $child, CarbonImmutable $from, ?CarbonImmutable $to = null): Collection
    {
        return BeszedAttempt::query()
            ->where('child_id', $child->id)
            ->where('created_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('created_at', '<', $to))
            ->groupBy('game')
            ->select('game',
                DB::raw('COUNT(*) as rounds'),
                DB::raw('SUM(CASE WHEN correct AND tries = 1 THEN 1 ELSE 0 END) as first_try'),
                DB::raw('SUM(CASE WHEN correct THEN 1 ELSE 0 END) as solved'),
                DB::raw('MAX(created_at) as last_played'))
            ->get()->keyBy('game');
    }
}
