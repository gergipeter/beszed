<?php

namespace App\Beszed\Stt;

use Illuminate\Support\Facades\Http;

class AzureSttClient implements SttClient
{
    public function __construct(private array $cfg) {}

    public function assess(string $audio, string $referenceText): ?PronunciationResult
    {
        $assessmentConfig = base64_encode(json_encode([
            'ReferenceText' => $referenceText,
            'GradingSystem' => 'HundredMark',
            'Granularity' => 'Phoneme',
            'Dimension' => 'Comprehensive',
        ]));

        $res = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->cfg['key'],
            'Pronunciation-Assessment' => $assessmentConfig,
            'Content-Type' => "audio/{$this->cfg['audio_format']}; codecs=opus",
            'Accept' => 'application/json',
        ])->withBody($audio, "audio/{$this->cfg['audio_format']}")
            ->timeout(15)
            ->retry(2, 300, throw: false)
            ->post("https://{$this->cfg['region']}.stt.speech.microsoft.com/speech/recognition/conversation/cognitiveservices/v1", [
                'language' => $this->cfg['language'],
                'format' => 'detailed',
            ]);

        if (! $res->successful()) {
            report(new \RuntimeException("Azure pronunciation assessment failed ({$res->status()}): ".mb_substr($res->body(), 0, 200)));

            return null;
        }

        $body = $res->json();
        $best = $body['NBest'][0] ?? null;
        $assessment = $best['PronunciationAssessment'] ?? null;

        if ($best === null || $assessment === null) {
            return null;
        }

        return new PronunciationResult(
            accuracy: (float) $assessment['AccuracyScore'],
            fluency: (float) $assessment['FluencyScore'],
            completeness: (float) $assessment['CompletenessScore'],
            recognizedText: $best['Display'] ?? '',
        );
    }
}
