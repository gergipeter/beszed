<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\DailyPath;
use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "Mai kaland": today's three suggested games and which are done. */
class DailyPathController extends Controller
{
    use AuthorizesChild;

    public function show(Request $request, Child $child, DailyPath $paths): JsonResponse
    {
        $this->authorizeChild($request, $child);

        return response()->json($paths->present($paths->today($child)));
    }
}
