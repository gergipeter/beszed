<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\Rewards\Rewards;
use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Levels, streak, daily goal, medals, stickers and Csillám's wardrobe. */
class RewardController extends Controller
{
    use AuthorizesChild;

    public function show(Request $request, Child $child, Rewards $rewards): JsonResponse
    {
        $this->authorizeChild($request, $child);

        return response()->json($rewards->summary($child));
    }

    /** A game was played to the end. */
    public function store(Request $request, Child $child, Rewards $rewards): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $data = $request->validate([
            'game' => ['required', Rule::in(array_keys(config('beszed.games')))],
            'level' => ['required', 'integer', 'min:1', 'max:10'],
            'rounds' => ['required', 'integer', 'min:1', 'max:50'],
            'correct' => ['required', 'integer', 'min:0', 'lte:rounds'],
            'first_try' => ['required', 'integer', 'min:0', 'lte:correct'],
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:36000000'],
            // Sent when a game finished offline is uploaded later.
            'played_at' => ['nullable', 'date', 'before_or_equal:now', 'after:-30 days'],
        ]);

        return response()->json($rewards->complete($child, $data), 201);
    }

    /** A parent hands over one of the "manual" stickers from the parents' menu. */
    public function gift(Request $request, Child $child, Rewards $rewards): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $manual = collect(config('beszed.rewards.badges'))->filter(fn ($b) => ($b['rule'][0] ?? null) === 'manual')->keys()->all();
        $data = $request->validate(['badge' => ['required', Rule::in($manual)]]);

        return response()->json($rewards->giftBadge($child, $data['badge']));
    }

    public function wear(Request $request, Child $child, Rewards $rewards): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $data = $request->validate([
            'slot' => ['required', Rule::in(collect(config('beszed.rewards.accessories'))->pluck('slot')->unique()->values()->all())],
            'accessory' => ['present', 'nullable', Rule::in(array_keys(config('beszed.rewards.accessories')))],
        ]);

        abort_unless($rewards->wear($child, $data['slot'], $data['accessory']), 422, 'Ez még nincs kinyitva.');

        return response()->json($rewards->summary($child));
    }

    public function scene(Request $request, Child $child, Rewards $rewards): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $data = $request->validate([
            'background' => ['present', 'nullable', Rule::in(array_keys(config('beszed.rewards.backgrounds')))],
            'stickers' => ['required', 'array', 'max:' . config('beszed.rewards.scene_max_stickers')],
            'stickers.*.badge' => ['required', 'string'],
            'stickers.*.x' => ['required', 'numeric', 'between:0,100'],
            'stickers.*.y' => ['required', 'numeric', 'between:0,100'],
            'stickers.*.rotate' => ['nullable', 'numeric', 'between:-180,180'],
            'stickers.*.scale' => ['nullable', 'numeric', 'between:0.5,2.5'],
        ]);

        return response()->json($rewards->saveScene($child, $data['background'], $data['stickers']));
    }
}
