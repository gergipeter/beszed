<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\SpeechAnalysis\SpeechAnalyzer;
use App\Http\Controllers\Controller;
use App\Models\BeszedPhonemeProgress;
use App\Models\BeszedSpeechRecording;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SpeechController extends Controller
{
    use AuthorizesChild;

    public function __construct(private SpeechAnalyzer $analyzer) {}

    /**
     * Analyze uploaded speech recording
     */
    public function analyze(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $data = $request->validate([
            'audio' => ['required', 'file', 'mimes:wav,mp3,m4a,ogg', 'max:10240'],
            'game' => ['nullable', 'string', 'max:32'],
        ]);

        // Store audio file
        $audioPath = $data['audio']->store('speech-recordings', 'public');

        // Analyze
        $result = $this->analyzer->analyze($audioPath, $child, $data['game'] ?? null);

        return response()->json($result, 201);
    }

    /**
     * Get speech history for child
     */
    public function history(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $recordings = BeszedSpeechRecording::where('child_id', $child->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'total' => $recordings->total(),
            'recordings' => $recordings->map(fn ($r) => [
                'id' => $r->id,
                'game' => $r->game,
                'transcription' => $r->transcription,
                'scores' => [
                    'pronunciation' => $r->pronunciation_score,
                    'fluency' => $r->fluency_score,
                    'clarity' => $r->clarity_score,
                ],
                'recorded_at' => $r->created_at,
            ]),
        ]);
    }

    /**
     * Get phoneme progress tracking
     */
    public function phonemeProgress(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $phonemes = BeszedPhonemeProgress::where('child_id', $child->id)
            ->orderByDesc('accuracy')
            ->get();

        return response()->json([
            'total_phonemes' => $phonemes->count(),
            'phonemes' => $phonemes->map(fn ($p) => [
                'phoneme' => $p->phoneme,
                'accuracy' => $p->accuracy,
                'attempts' => $p->attempts,
                'correct' => $p->correct,
                'incorrect' => $p->incorrect,
                'last_practiced' => $p->last_practiced_at,
            ]),
            'strengths' => $phonemes->where('accuracy', '>=', 80)->pluck('phoneme'),
            'weaknesses' => $phonemes->where('accuracy', '<', 60)->pluck('phoneme'),
        ]);
    }

    /**
     * Get recording details + analysis
     */
    public function show(Request $request, Child $child, BeszedSpeechRecording $recording): JsonResponse
    {
        $this->authorizeChild($request, $child);
        abort_unless($recording->child_id === $child->id, 403);

        return response()->json([
            'id' => $recording->id,
            'game' => $recording->game,
            'audio_url' => $recording->audio_url,
            'transcription' => $recording->transcription,
            'scores' => [
                'pronunciation' => $recording->pronunciation_score,
                'fluency' => $recording->fluency_score,
                'clarity' => $recording->clarity_score,
                'overall' => intval(
                    ($recording->pronunciation_score +
                     $recording->fluency_score +
                     $recording->clarity_score) / 3
                ),
            ],
            'phonemes' => json_decode($recording->phoneme_analysis, true),
            'feedback' => json_decode($recording->feedback, true),
            'recorded_at' => $recording->created_at,
        ]);
    }

    /**
     * Get progress trends (30 days)
     */
    public function trends(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $thirtyDaysAgo = now()->subDays(30);

        $daily = BeszedSpeechRecording::where('child_id', $child->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('AVG(pronunciation_score) as pronunciation')
            ->selectRaw('AVG(fluency_score) as fluency')
            ->selectRaw('AVG(clarity_score) as clarity')
            ->selectRaw('COUNT(*) as attempts')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        $overallTrend = BeszedSpeechRecording::where('child_id', $child->id)
            ->selectRaw('AVG(pronunciation_score) as pronunciation')
            ->selectRaw('AVG(fluency_score) as fluency')
            ->selectRaw('AVG(clarity_score) as clarity')
            ->first();

        return response()->json([
            'daily_trends' => $daily,
            'overall' => $overallTrend,
            'period' => '30 days',
        ]);
    }
}
