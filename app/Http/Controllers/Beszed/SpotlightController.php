<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\Spotlight;
use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "Hang a nap": the game the child's weakest at right now, for a nudge on the hub. */
class SpotlightController extends Controller
{
    use AuthorizesChild;

    public function show(Request $request, Child $child, Spotlight $spotlight): JsonResponse
    {
        $this->authorizeChild($request, $child);

        return response()->json(['game' => $spotlight->pick($child)['game'] ?? null]);
    }
}
