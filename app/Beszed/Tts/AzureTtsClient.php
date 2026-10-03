<?php

namespace App\Beszed\Tts;

use Illuminate\Support\Facades\Http;

class AzureTtsClient implements TtsClient
{
    public function __construct(private array $cfg) {}

    public function synthesize(string $text, array $overrides = []): ?string
    {
        ['voice' => $voice, 'rate' => $rate, 'pitch' => $pitch] = $this->resolve($overrides);

        $esc = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        // Explicit breaks at punctuation: without them the neural voice rushes
        // through pauses, which is a big part of what reads as "robotic".
        $esc = preg_replace('/([.!?…])(\s|$)/u', "$1<break time='260ms'/>$2", $esc);
        $esc = preg_replace('/([,;:])(\s)/u', "$1<break time='80ms'/>$2", $esc);
        $ssml = "<speak version='1.0' xml:lang='hu-HU' xmlns='http://www.w3.org/2001/10/synthesis'>"
            ."<voice name='{$voice}'>"
            ."<prosody rate='{$rate}' pitch='{$pitch}'>{$esc}</prosody>"
            .'</voice></speak>';

        $res = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->cfg['key'],
            'X-Microsoft-OutputFormat' => $this->cfg['format'],
            'User-Agent' => config('app.name', 'betuvarazs'),
        ])->withBody($ssml, 'application/ssml+xml')
            ->timeout(15)
            ->retry(2, 300, throw: false)
            ->post("https://{$this->cfg['region']}.tts.speech.microsoft.com/cognitiveservices/v1");

        if (! $res->successful()) {
            report(new \RuntimeException("Azure TTS failed ({$res->status()}): ".mb_substr($res->body(), 0, 200)));

            return null;
        }

        return $res->body();
    }

    public function voiceId(array $overrides = []): string
    {
        ['voice' => $voice, 'rate' => $rate, 'pitch' => $pitch] = $this->resolve($overrides);

        // "b2" = pause timing revision: bump it when the SSML breaks change, so cached audio is regenerated.
        return "azure:b2:{$voice}:{$rate}:{$pitch}";
    }

    /**
     * Merges validated overrides onto the configured defaults. Callers (TtsController)
     * are responsible for validating `$overrides` against config('tts.voices')/rate_range/pitch_range
     * before this point; this only guards against a malformed value reaching the SSML body.
     *
     * @param  array{voice?: string, rate?: string, pitch?: string}  $overrides
     * @return array{voice: string, rate: string, pitch: string}
     */
    private function resolve(array $overrides): array
    {
        $percent = '/^[+-]?\d{1,3}%$/';
        $voice = $overrides['voice'] ?? $this->cfg['voice'];
        $rate = $overrides['rate'] ?? $this->cfg['rate'];
        $pitch = $overrides['pitch'] ?? $this->cfg['pitch'];

        return [
            'voice' => preg_match('/^[A-Za-z-]{1,64}$/', $voice) ? $voice : $this->cfg['voice'],
            'rate' => preg_match($percent, $rate) ? $rate : $this->cfg['rate'],
            'pitch' => preg_match($percent, $pitch) ? $pitch : $this->cfg['pitch'],
        ];
    }
}
