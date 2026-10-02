<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\ProgressReport;
use App\Beszed\ReportNarrative;
use App\Http\Controllers\Controller;
use App\Models\BeszedAttempt;
use App\Models\BeszedSession;
use App\Models\Child;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Per-game, per-skill-area and per-sound summary (printable for the logopédus) and weekly trend. */
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

    public function show(Request $request, Child $child, ProgressReport $report, ReportNarrative $narrative): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $data = $report->for($child, (int) $request->integer('days', 30));
        $data['narrative'] = $narrative->narrative($data['areas']);
        $data['recommendations'] = $narrative->recommendations($data['areas']);
        $data['sounds'] += $narrative->sounds($data['sounds']);

        return response()->json($data);
    }
}
