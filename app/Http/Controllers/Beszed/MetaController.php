<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\Lines;
use App\Beszed\Stt\NullSttClient;
use App\Beszed\Stt\SttClient;
use App\Beszed\Tts\NullTtsClient;
use App\Beszed\Tts\TtsClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    public function __invoke(TtsClient $tts, SttClient $stt): JsonResponse
    {
        $games = collect(config('beszed.games'))->map(fn ($g, $id) => [
            'id' => $id,
            'name' => $g['name'],
            'emoji' => $g['emoji'],
            'skill' => $g['skill'],
            'color' => $g['color'],
            'tier' => $g['tier'] ?? 'simple',
            'stage' => $g['stage'] ?? 'meadow',
            'rounds' => $g['rounds'],
            'noIdle' => (bool) ($g['no_idle'] ?? false),
        ])->values();

        $voices = collect(config('tts.voices'))->map(fn ($v, $id) => [
            'id' => $id, 'label' => $v['label'], 'gender' => $v['gender'],
        ])->values();

        return response()->json([
            'games' => $games,
            'praise' => config('beszed.praise'),
            'retry' => config('beszed.retry'),
            'lines' => Lines::all(),
            // Ask the bound client, not the env string: env('TTS_DRIVER') turns "null" into null.
            'serverTts' => ! $tts instanceof NullTtsClient,
            'serverStt' => ! $stt instanceof NullSttClient,
            'voices' => $voices,
            'rateRange' => config('tts.rate_range'),
            'pitchRange' => config('tts.pitch_range'),
        ]);
    }
}
