<?php

namespace App\Http\Controllers\Beszed;

use App\Http\Controllers\Controller;
use App\Models\BeszedAttempt;
use App\Models\BeszedSession;
use App\Models\Child;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Per-game summary — printable for the logopédus. */
class ProgressController extends Controller
{
    use AuthorizesChild;

    /**
     * Week-by-week trend for the parent charts: games finished, answers, share right
     * at the first try, minutes played. Weeks start on Monday in the rewards timezone.
     */
    public function history(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);
        $data = $request->validate([
            'weeks' => ['nullable', 'integer', 'min:4', 'max:26'],
            'game' => ['nullable', Rule::in(array_keys(config('beszed.games')))],
        ]);
        $weeks = $data['weeks'] ?? 8;
        $game = $data['game'] ?? null;
        $tz = config('beszed.rewards.timezone');

        $first = CarbonImmutable::now($tz)->startOfWeek()->subWeeks($weeks - 1);
        $week = fn ($at) => CarbonImmutable::parse($at)->setTimezone($tz)->startOfWeek()->toDateString();

        $buckets = collect(range(0, $weeks - 1))
            ->mapWithKeys(fn ($i) => [$first->addWeeks($i)->toDateString() => ['answers' => 0, 'first_try' => 0, 'games' => 0, 'ms' => 0]])
            ->all();

        BeszedAttempt::where('child_id', $child->id)
            ->where('created_at', '>=', $first->utc())
            ->when($game, fn ($q) => $q->where('game', $game))
            ->get(['created_at', 'correct', 'tries'])
            ->each(function ($a) use (&$buckets, $week) {
                $k = $week($a->created_at);
                if (isset($buckets[$k])) {
                    $buckets[$k]['answers']++;
                    $buckets[$k]['first_try'] += (int) ($a->correct && $a->tries === 1);
                }
            });

        BeszedSession::where('child_id', $child->id)
            ->where('completed_at', '>=', $first->utc())
            ->when($game, fn ($q) => $q->where('game', $game))
            ->get(['completed_at', 'duration_ms'])
            ->each(function ($s) use (&$buckets, $week) {
                $k = $week($s->completed_at);
                if (isset($buckets[$k])) {
                    $buckets[$k]['games']++;
                    $buckets[$k]['ms'] += (int) $s->duration_ms;
                }
            });

        return response()->json([
            'game' => $game,
            'weeks' => collect($buckets)->map(fn ($b, $start) => [
                'week' => $start,
                'games' => $b['games'],
                'answers' => $b['answers'],
                'firstTryRate' => $b['answers'] ? round($b['first_try'] / $b['answers'], 3) : null,
                'minutes' => round($b['ms'] / 60000, 1),
            ])->values(),
        ]);
    }

    public function show(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);
        $days = (int) $request->integer('days', 30);
        $since = now()->subDays(max(1, min($days, 365)));

        $stats = BeszedAttempt::query()
            ->where('child_id', $child->id)
            ->where('created_at', '>=', $since)
            ->groupBy('game')
            ->select('game',
                DB::raw('COUNT(*) as rounds'),
                DB::raw('SUM(CASE WHEN correct AND tries = 1 THEN 1 ELSE 0 END) as first_try'),
                DB::raw('SUM(CASE WHEN correct THEN 1 ELSE 0 END) as solved'),
                DB::raw('MAX(created_at) as last_played'))
            ->get()->keyBy('game');

        $levels = $child->beszedLevels()->pluck('level', 'game');
        $sessions = $child->beszedSessions()->where('completed_at', '>=', $since)
            ->groupBy('game')->selectRaw('game, COUNT(*) as n')->pluck('n', 'game');

        $games = collect(config('beszed.games'))->map(function ($g, $id) use ($stats, $levels, $sessions) {
            $s = $stats->get($id);
            $rounds = (int) ($s->rounds ?? 0);

            return [
                'id' => $id,
                'name' => $g['name'],
                'emoji' => $g['emoji'],
                'skill' => $g['skill'],
                'sessions' => (int) ($sessions[$id] ?? 0),
                'rounds' => $rounds,
                'firstTryRate' => $rounds ? round($s->first_try / $rounds, 2) : null,
                'solvedRate' => $rounds ? round($s->solved / $rounds, 2) : null,
                'level' => isset($g['adaptive']) ? ($levels[$id] ?? $g['adaptive']['start']) : null,
                'maxLevel' => $g['adaptive']['max'] ?? null,
                'lastPlayed' => $s->last_played ?? null,
            ];
        })->values();

        return response()->json(['child' => $child->only('id', 'name'), 'since' => $since->toDateString(), 'games' => $games]);
    }
}
