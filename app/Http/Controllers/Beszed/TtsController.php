<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\Tts\TtsCache;
use App\Http\Controllers\Controller;
use App\Models\BeszedVoiceSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class TtsController extends Controller
{
    public function __invoke(Request $request, TtsCache $cache): Response
    {
        $text = $request->validate(['t' => ['required', 'string', 'max:'.config('tts.max_chars')]])['t'];

        $path = $cache->ensure($text, $this->overridesFor($request));
        abort_if($path === null, 404); // client falls back to Web Speech

        $diskName = config('tts.disk');
        $disk = Storage::disk($diskName);

        if (config("filesystems.disks.$diskName.driver") === 's3') {
            return redirect()->away($disk->temporaryUrl($path, now()->addHour()));
        }

        return $disk->response($path, null, [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }

    /** @return array{voice?: string, rate?: string, pitch?: string} */
    private function overridesFor(Request $request): array
    {
        $settings = BeszedVoiceSettings::where('user_id', $request->user()->id)->first();
        if (! $settings) {
            return [];
        }

        $voices = config('tts.voices');
        $overrides = [];

        if ($settings->voice && isset($voices[$settings->voice])) {
            $overrides['voice'] = $voices[$settings->voice]['name'];
        }
        if ($settings->rate) {
            $overrides['rate'] = $settings->rate;
        }
        if ($settings->pitch) {
            $overrides['pitch'] = $settings->pitch;
        }

        return $overrides;
    }
}
