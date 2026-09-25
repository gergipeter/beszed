<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\Tts\TtsCache;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class TtsController extends Controller
{
    public function __invoke(Request $request, TtsCache $cache): Response
    {
        $text = $request->validate(['t' => ['required', 'string', 'max:'.config('tts.max_chars')]])['t'];

        $path = $cache->ensure($text);
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
}
