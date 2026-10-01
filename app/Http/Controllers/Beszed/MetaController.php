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
            // top of the adaptive level range; a game with a hand-picked level (Kirakó) shows a chooser up to it
            'maxLevel' => $g['adaptive']['max'] ?? null,
            // picture themes to pick from before playing (Kirakó)
            'categories' => collect($g['categories'] ?? [])->map(fn ($c, $id) => ['id' => $id, 'name' => $c['name'], 'emoji' => $c['emoji']])->values(),
        ])->values();

        // the hub's folders (what each game develops); games not filed anywhere go to a last one
        $folders = collect(config('beszed_folders.folders'))->map(fn ($f, $id) => [
            'id' => $id, 'name' => $f['name'], 'emoji' => $f['emoji'], 'color' => $f['color'], 'develops' => $f['develops'],
            'games' => array_values(array_filter($f['games'], fn ($g) => isset(config('beszed.games')[$g]))),
        ])->values();
        $filed = $folders->pluck('games')->flatten();
        $loose = $games->pluck('id')->diff($filed)->values();
        if ($loose->isNotEmpty()) {
            $folders->push(['id' => 'egyeb', 'name' => 'Egyéb', 'emoji' => '🎈', 'color' => '#E6E6F0', 'develops' => 'Többféle készség', 'games' => $loose->all()]);
        }

        $voices = collect(config('tts.voices'))->map(fn ($v, $id) => [
            'id' => $id, 'label' => $v['label'], 'gender' => $v['gender'],
        ])->values();

        // Identical for every user and unchanged until the next deploy: let the browser reuse it briefly.
        return response()->json([
            'games' => $games,
            'folders' => $folders,
            'praise' => config('beszed.praise'),
            'retry' => config('beszed.retry'),
            'lines' => Lines::all(),
            // Ask the bound client, not the env string: env('TTS_DRIVER') turns "null" into null.
            'serverTts' => ! $tts instanceof NullTtsClient,
            'serverStt' => ! $stt instanceof NullSttClient,
            'voices' => $voices,
            'rateRange' => config('tts.rate_range'),
            'pitchRange' => config('tts.pitch_range'),
            // the free plan's top level (same for everyone, so the response stays cacheable); null = none
            'freeMaxLevel' => config('beszed_plans.free_max_level'),
        ])->header('Cache-Control', 'private, max-age=300, stale-while-revalidate=3600');
    }
}
