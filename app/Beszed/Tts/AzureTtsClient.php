<?php

namespace App\Beszed\Tts;

use Illuminate\Support\Facades\Http;

class AzureTtsClient implements TtsClient
{
    public function __construct(private array $cfg) {}

    public function synthesize(string $text): ?string
    {
        $esc = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $ssml = "<speak version='1.0' xml:lang='hu-HU' xmlns='http://www.w3.org/2001/10/synthesis'>"
            ."<voice name='{$this->cfg['voice']}'>"
            ."<prosody rate='{$this->cfg['rate']}' pitch='{$this->cfg['pitch']}'>{$esc}</prosody>"
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

    public function voiceId(): string
    {
        return "azure:{$this->cfg['voice']}:{$this->cfg['rate']}:{$this->cfg['pitch']}";
    }
}
