<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\SessionBuilder;
use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SessionController extends Controller
{
    use AuthorizesChild;

    public function show(Request $request, Child $child, SessionBuilder $builder): JsonResponse
    {
        $this->authorizeChild($request, $child);
        $data = $request->validate([
            'game' => ['required', Rule::in(array_keys(config('beszed.games')))],
            // a game with picture themes (Kirakó): which one the child picked
            'category' => ['nullable', 'string', 'max:30'],
        ]);
        $categories = config("beszed.games.{$data['game']}.categories", []);
        $category = isset($categories[$data['category'] ?? '']) ? $data['category'] : null;

        return response()->json($builder->build($child, $data['game'], ['category' => $category]));
    }
}
