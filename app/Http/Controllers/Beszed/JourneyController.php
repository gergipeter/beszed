<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\LearningPath;
use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "Utazás": the child's track through each skill area, and what to play next. */
class JourneyController extends Controller
{
    use AuthorizesChild;

    public function show(Request $request, Child $child, LearningPath $path): JsonResponse
    {
        $this->authorizeChild($request, $child);

        return response()->json($path->for($child));
    }
}
