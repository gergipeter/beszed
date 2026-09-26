<?php

namespace App\Beszed\Tts;

use Symfony\Component\Process\Process;

/**
 * Free, self-hosted neural voice: Piper (https://github.com/rhasspy/piper) runs on
 * this server, so no account, no cost and no text leaves the machine. Output is
 * encoded to MP3 with `lame`. Speed follows the parent's rate setting; Piper has
 * no pitch control, so pitch is ignored.
 */
class PiperTtsClient implements TtsClient
{
    public function __construct(private array $cfg) {}

    public function synthesize(string $text, array $overrides = []): ?string
    {
        ['voice' => $voice, 'length' => $length] = $this->resolve($overrides);
        $model = rtrim($this->cfg['voices_path'], '/')."/{$voice}.onnx";

        if (! is_file($model) || ! is_executable($this->cfg['binary'])) {
            report(new \RuntimeException("Piper TTS is not installed (binary {$this->cfg['binary']}, model $model)."));

            return null;
        }

        $base = tempnam(sys_get_temp_dir(), 'piper');
        $wav = "$base.wav";
        $mp3 = "$base.mp3";

        try {
            $piper = new Process([
                $this->cfg['binary'], '--model', $model, '--output_file', $wav,
                '--length_scale', (string) $length, '--sentence_silence', (string) $this->cfg['sentence_silence'],
            ]);
            $piper->setInput($text)->setTimeout(60)->run();
            if (! $piper->isSuccessful() || ! is_file($wav) || filesize($wav) < 1000) {
                report(new \RuntimeException('Piper TTS failed: '.mb_substr($piper->getErrorOutput(), 0, 300)));

                return null;
            }

            $lame = new Process([$this->cfg['lame'], '--quiet', '-m', 'm', '-b', (string) $this->cfg['bitrate'], $wav, $mp3]);
            $lame->setTimeout(60)->run();
            if (! $lame->isSuccessful() || ! is_file($mp3)) {
                report(new \RuntimeException('MP3 encoding failed: '.mb_substr($lame->getErrorOutput(), 0, 300)));

                return null;
            }

            return file_get_contents($mp3);
        } finally {
            foreach ([$base, $wav, $mp3] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    public function voiceId(array $overrides = []): string
    {
        ['voice' => $voice, 'length' => $length] = $this->resolve($overrides);

        return "piper:{$voice}:{$length}:{$this->cfg['sentence_silence']}";
    }

    /**
     * Voice from the configured list only; rate "-10%" → a 10% slower length scale.
     *
     * @param  array{voice?: string, rate?: string, pitch?: string}  $overrides
     * @return array{voice: string, length: float}
     */
    private function resolve(array $overrides): array
    {
        $allowed = array_column(config('tts.voices'), 'name');
        $voice = in_array($overrides['voice'] ?? null, $allowed, true) ? $overrides['voice'] : $this->cfg['voice'];

        $length = (float) $this->cfg['length_scale'];
        if (preg_match('/^([+-]?\d{1,3})%$/', $overrides['rate'] ?? '', $m)) {
            $length /= max(0.4, 1 + ((int) $m[1]) / 100);
        }

        return ['voice' => $voice, 'length' => round(min(2.0, max(0.6, $length)), 2)];
    }
}
