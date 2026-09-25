<?php

namespace App\Http\Controllers\Beszed;

use App\Http\Controllers\Controller;
use App\Models\BeszedAttempt;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Per-game summary — printable for the logopédus. */
class ProgressController extends Controller
{
    use AuthorizesChild;

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

        $games = collect(config('beszed.games'))->map(function ($g, $id) use ($stats, $levels) {
            $s = $stats->get($id);
            $rounds = (int) ($s->rounds ?? 0);

            return [
                'id' => $id,
                'name' => $g['name'],
                'emoji' => $g['emoji'],
                'skill' => $g['skill'],
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
