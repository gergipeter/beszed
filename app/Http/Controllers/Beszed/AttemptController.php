<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\Leveler;
use App\Http\Controllers\Controller;
use App\Models\BeszedAttempt;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttemptController extends Controller
{
    use AuthorizesChild;

    public function store(Request $request, Child $child, Leveler $leveler): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $data = $request->validate([
            'game' => ['required', Rule::in(array_keys(config('beszed.games')))],
            'content_item_id' => ['nullable', 'integer', 'exists:beszed_content_items,id'],
            'level' => ['required', 'integer', 'min:1', 'max:10'],
            'correct' => ['required', 'boolean'],
            'tries' => ['required', 'integer', 'min:1', 'max:50'],
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:3600000'],
            // Sent when an answer given offline is uploaded later.
            'played_at' => ['nullable', 'date', 'before_or_equal:now', 'after:-30 days'],
        ]);

        $attempt = new BeszedAttempt(collect($data)->except('played_at')->all() + ['child_id' => $child->id]);
        if (isset($data['played_at'])) {
            $attempt->created_at = $data['played_at'];
        }
        $attempt->save();
        $level = $leveler->record($child, $data['game'], $data['correct'], $data['tries']);

        return response()->json([
            'level' => $level,
            'stars' => $child->beszedAttempts()->where('correct', true)->count(),
        ], 201);
    }
}
