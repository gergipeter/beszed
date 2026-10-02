<?php

namespace App\Beszed\Stt;

use Illuminate\Support\Facades\Http;

/**
 * Self-hosted Whisper (large-v3) behind an OpenAI-compatible /v1/audio/transcriptions endpoint
 * (faster-whisper-server / speaches, whisper.cpp server, ...). The child's voice stays on our own server.
 *
 * Whisper returns a transcript, not phoneme scores, so the attempt is judged by "did they say the right
 * words": the transcript is compared with the reference sentence. Fluency can't be measured this way and
 * follows completeness.
 */
class WhisperSttClient implements SttClient
{
    /** A heard word this similar to a reference word counts as that word. */
    private const WORD_MATCH = 0.75;

    public function __construct(private array $cfg) {}

    public function assess(string $audio, string $referenceText): ?PronunciationResult
    {
        $res = Http::withHeaders(array_filter(['Authorization' => $this->cfg['key'] ? "Bearer {$this->cfg['key']}" : null]))
            ->attach('file', $audio, 'attempt.'.$this->cfg['audio_format'])
            ->timeout($this->cfg['timeout'])
            ->retry(1, 300, throw: false)
            ->post(rtrim($this->cfg['url'], '/').'/v1/audio/transcriptions', [
                'model' => $this->cfg['model'],
                'language' => $this->cfg['language'],
                'response_format' => 'json',
                'temperature' => 0,
            ]);

        if (! $res->successful()) {
            report(new \RuntimeException("Whisper transcription failed ({$res->status()}): ".mb_substr($res->body(), 0, 200)));

            return null;
        }

        $heard = trim((string) $res->json('text', ''));

        return self::score($referenceText, $heard);
    }

    /** Scores a transcript against the sentence the child was asked to say. */
    public static function score(string $reference, string $heard): PronunciationResult
    {
        $wanted = self::words($reference);
        $said = self::words($heard);

        if ($wanted === [] || $said === []) {
            return new PronunciationResult(0, 0, 0, $heard);
        }

        $accuracy = 100 * self::similarity(implode(' ', $wanted), implode(' ', $said));

        $found = 0;
        foreach ($wanted as $word) {
            foreach ($said as $candidate) {
                if (self::similarity($word, $candidate) >= self::WORD_MATCH) {
                    $found++;
                    break;
                }
            }
        }
        $completeness = 100 * $found / count($wanted);

        return new PronunciationResult(round($accuracy, 1), round($completeness, 1), round($completeness, 1), $heard);
    }

    /** @return string[] lower-case words without punctuation. */
    private static function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
    }

    /** 1 = identical, 0 = nothing in common (letter-level, so ő/ű/á count as one letter). */
    private static function similarity(string $a, string $b): float
    {
        $x = mb_str_split($a);
        $y = mb_str_split($b);
        $longest = max(count($x), count($y));

        return $longest === 0 ? 1.0 : 1 - self::distance($x, $y) / $longest;
    }

    /** Levenshtein distance between two letter arrays (PHP's levenshtein() is byte-based). */
    private static function distance(array $x, array $y): int
    {
        $previous = range(0, count($y));
        foreach ($x as $i => $letter) {
            $row = [$i + 1];
            foreach ($y as $j => $other) {
                $row[] = min($previous[$j + 1] + 1, $row[$j] + 1, $previous[$j] + ($letter === $other ? 0 : 1));
            }
            $previous = $row;
        }

        return $previous[count($y)];
    }
}
