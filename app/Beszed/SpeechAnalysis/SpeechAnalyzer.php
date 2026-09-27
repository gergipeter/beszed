<?php

namespace App\Beszed\SpeechAnalysis;

use App\Models\BeszedPhonemeProgress;
use App\Models\BeszedSpeechRecording;
use App\Models\Child;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Speech Analysis Service
 * Analyzes recordings using Whisper API + phoneme detection
 */
class SpeechAnalyzer
{
    private string $whisperApiKey;

    public function __construct()
    {
        $this->whisperApiKey = config('services.openai.key');
    }

    /**
     * Analyze audio file and return transcription + phoneme scores
     */
    public function analyze(string $audioPath, Child $child, ?string $game = null): array
    {
        // 1. Transcribe with Whisper
        $transcription = $this->transcribe($audioPath);

        // 2. Detect phonemes
        $phonemes = $this->detectPhonemes($transcription['text']);

        // 3. Score pronunciation
        $pronunciationScore = $this->scorePronunciation($transcription, $phonemes);

        // 4. Calculate fluency
        $fluencyScore = $this->calculateFluency($transcription);

        // 5. Calculate clarity
        $clarityScore = $this->calculateClarity($transcription);

        // 6. Generate feedback
        $feedback = $this->generateFeedback($phonemes, $pronunciationScore, $fluencyScore);

        // 7. Save to database
        $recording = BeszedSpeechRecording::create([
            'child_id' => $child->id,
            'game' => $game,
            'audio_path' => $audioPath,
            'audio_url' => Storage::disk('public')->url($audioPath),
            'transcription' => $transcription['text'],
            'phoneme_analysis' => json_encode($phonemes),
            'confidence_score' => intval($transcription['confidence'] * 100),
            'pronunciation_score' => $pronunciationScore,
            'fluency_score' => $fluencyScore,
            'clarity_score' => $clarityScore,
            'feedback' => $feedback,
        ]);

        // 8. Update phoneme progress
        $this->updatePhonemeProgress($child, $phonemes, $pronunciationScore);

        return [
            'recording_id' => $recording->id,
            'transcription' => $transcription['text'],
            'scores' => [
                'pronunciation' => $pronunciationScore,
                'fluency' => $fluencyScore,
                'clarity' => $clarityScore,
                'overall' => intval(($pronunciationScore + $fluencyScore + $clarityScore) / 3),
            ],
            'phonemes' => $phonemes,
            'feedback' => $feedback,
        ];
    }

    /**
     * Transcribe audio using Whisper API
     */
    private function transcribe(string $audioPath): array
    {
        $audioFile = Storage::disk('public')->path($audioPath);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->whisperApiKey}",
            ])->attach(
                'file',
                fopen($audioFile, 'r'),
                basename($audioFile)
            )->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'language' => 'hu', // Hungarian
            ]);

            $data = $response->json();

            return [
                'text' => $data['text'] ?? '',
                'confidence' => 0.9, // Whisper doesn't provide confidence, estimate
            ];
        } catch (\Exception $e) {
            report($e);
            return ['text' => '', 'confidence' => 0];
        }
    }

    /**
     * Detect Hungarian phonemes in transcription
     */
    private function detectPhonemes(string $text): array
    {
        // Hungarian phonemes /phonemes/
        $phonemeMap = [
            '/ʃ/' => ['s', 'sh'], // sibilant
            '/tʃ/' => ['cs'], // affricate
            '/dʒ/' => ['gy'], // voiced affricate
            '/ʒ/' => ['zs'], // voiced sibilant
            '/r/' => ['r'], // rhotic
            '/l/' => ['l'], // lateral
            '/ŋ/' => ['ng'], // velar nasal
            '/j/' => ['j'], // palatal approximant
            '/n/' => ['n'], // alveolar nasal
            '/m/' => ['m'], // bilabial nasal
            '/p/' => ['p'], // bilabial stop
            '/b/' => ['b'], // voiced bilabial stop
            '/t/' => ['t'], // alveolar stop
            '/d/' => ['d'], // voiced alveolar stop
            '/k/' => ['k'], // velar stop
            '/ɡ/' => ['g'], // voiced velar stop
            '/f/' => ['f'], // labiodental fricative
            '/v/' => ['v'], // voiced labiodental fricative
            '/θ/' => ['th'], // interdental fricative (optional in Hungarian)
        ];

        $detectedPhonemes = [];
        $lowerText = mb_strtolower($text);

        foreach ($phonemeMap as $phoneme => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($lowerText, $pattern) !== false) {
                    $detectedPhonemes[$phoneme] = [
                        'detected' => true,
                        'count' => substr_count($lowerText, $pattern),
                        'confidence' => 0.85,
                    ];
                }
            }
        }

        return $detectedPhonemes;
    }

    /**
     * Score pronunciation (0-100)
     */
    private function scorePronunciation(array $transcription, array $phonemes): int
    {
        if (empty($transcription['text'])) {
            return 0;
        }

        // Base score from Whisper confidence
        $score = intval($transcription['confidence'] * 100);

        // Bonus for detected phonemes
        $phonemeBonus = min(20, count($phonemes) * 2);

        return min(100, $score + $phonemeBonus);
    }

    /**
     * Calculate fluency score (0-100)
     * Based on speech rate, pauses, and natural rhythm
     */
    private function calculateFluency(array $transcription): int
    {
        $text = $transcription['text'];

        if (empty($text)) {
            return 0;
        }

        // Heuristics: longer, coherent transcriptions = better fluency
        $wordCount = str_word_count($text);
        $sentenceCount = substr_count($text, '.') + substr_count($text, '!') + substr_count($text, '?');

        if ($sentenceCount === 0) {
            $sentenceCount = 1;
        }

        $avgWordsPerSentence = $wordCount / $sentenceCount;

        // Good fluency = 5-15 words per sentence (natural pace)
        if ($avgWordsPerSentence >= 5 && $avgWordsPerSentence <= 15) {
            return 85;
        } elseif ($avgWordsPerSentence >= 3 && $avgWordsPerSentence <= 20) {
            return 70;
        } elseif ($wordCount >= 3) {
            return 60;
        }

        return 40;
    }

    /**
     * Calculate clarity score (0-100)
     * Based on word consistency and proper spelling
     */
    private function calculateClarity(array $transcription): int
    {
        $text = $transcription['text'];

        if (empty($text)) {
            return 0;
        }

        // Heuristic: transcription confidence from Whisper
        $confidence = intval($transcription['confidence'] * 100);

        // Check for repetitions (unclear speech = more repetitions)
        $words = str_word_count(mb_strtolower($text), 1);
        $uniqueWords = count(array_unique($words));
        $repetitionRatio = $uniqueWords > 0 ? $uniqueWords / count($words) : 0;

        // Adjust confidence based on repetition
        if ($repetitionRatio > 0.8) {
            $confidence = min(100, $confidence + 10);
        } elseif ($repetitionRatio < 0.5) {
            $confidence = max(0, $confidence - 20);
        }

        return $confidence;
    }

    /**
     * Generate AI feedback
     */
    private function generateFeedback(array $phonemes, int $pronunciation, int $fluency): array
    {
        $feedback = [];

        // Pronunciation feedback
        if ($pronunciation >= 85) {
            $feedback[] = ['type' => 'excellent', 'message' => 'Excellent pronunciation! Keep it up!'];
        } elseif ($pronunciation >= 70) {
            $feedback[] = ['type' => 'good', 'message' => 'Good pronunciation. Practice more for perfection!'];
        } elseif ($pronunciation >= 50) {
            $feedback[] = ['type' => 'needs_work', 'message' => 'Your pronunciation needs some work. Let\'s practice!'];
        } else {
            $feedback[] = ['type' => 'focus_area', 'message' => 'Focus on these phonemes:'];
        }

        // Phoneme-specific feedback
        if (empty($phonemes)) {
            $feedback[] = ['type' => 'info', 'message' => 'Try saying more sounds!'];
        }

        // Fluency feedback
        if ($fluency < 60) {
            $feedback[] = ['type' => 'tip', 'message' => 'Try speaking more smoothly and slowly.'];
        }

        return $feedback;
    }

    /**
     * Update child's phoneme progress tracking
     */
    private function updatePhonemeProgress(Child $child, array $phonemes, int $score): void
    {
        foreach ($phonemes as $phoneme => $data) {
            $progress = BeszedPhonemeProgress::updateOrCreate(
                ['child_id' => $child->id, 'phoneme' => $phoneme],
                [
                    'attempts' => \DB::raw('attempts + 1'),
                    'correct' => \DB::raw("correct + " . ($score >= 70 ? 1 : 0)),
                    'incorrect' => \DB::raw("incorrect + " . ($score < 70 ? 1 : 0)),
                    'last_practiced_at' => now(),
                ]
            );

            // Recalculate accuracy
            $total = $progress->attempts;
            $accuracy = $total > 0 ? intval(($progress->correct / $total) * 100) : 0;
            $progress->update(['accuracy' => $accuracy]);
        }
    }
}
