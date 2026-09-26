<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\Stt\SttClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Scores the child's spoken attempt against the round's reference sentence (Mondd utánam). */
class PronunciationController extends Controller
{
    public function __invoke(Request $request, SttClient $stt): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:400'],
            'audio' => ['required', 'file', 'max:'.config('beszed.pronunciation.max_kb'),
                'mimetypes:audio/webm,audio/ogg,audio/mp4,audio/x-m4a,audio/aac,audio/wav,audio/x-wav,video/webm'],
        ]);

        $result = $stt->assess($data['audio']->get(), $data['text']);

        if ($result === null) {
            return response()->json(['available' => false]);
        }

        return response()->json([
            'available' => true,
            'correct' => $result->correct(),
            'tries' => $result->tries(),
            'accuracy' => $result->accuracy,
            'fluency' => $result->fluency,
            'completeness' => $result->completeness,
            'recognizedText' => $result->recognizedText,
        ]);
    }
}
