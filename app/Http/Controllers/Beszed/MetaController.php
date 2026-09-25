<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\Lines;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $games = collect(config('beszed.games'))->map(fn ($g, $id) => [
            'id' => $id,
            'name' => $g['name'],
            'emoji' => $g['emoji'],
            'skill' => $g['skill'],
            'color' => $g['color'],
            'rounds' => $g['rounds'],
            'noIdle' => (bool) ($g['no_idle'] ?? false),
        ])->values();

        return response()->json([
            'games' => $games,
            'praise' => config('beszed.praise'),
            'retry' => config('beszed.retry'),
            'lines' => Lines::all(),
            'serverTts' => config('tts.driver') !== 'null',
        ]);
    }
}
