<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\Lines;
use App\Http\Controllers\Controller;
use App\Models\BeszedRecording;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** Parent's own-voice lines. Stored per user account, private. */
class RecordingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $recs = BeszedRecording::where('user_id', $request->user()->id)->get()
            ->mapWithKeys(fn ($r) => [$r->line_key => route('beszed.recordings.audio', [$r->line_key, 'v' => $r->updated_at->timestamp])]);

        return response()->json(['recordings' => $recs]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'line_key' => ['required', Rule::in(Lines::keys())],
            // Browsers record webm/ogg/mp4; phones' voice-memo files are usually m4a.
            'file' => ['required', 'file', 'max:'.config('beszed.recordings.max_kb'),
                'mimetypes:audio/webm,audio/ogg,audio/mp4,audio/x-m4a,audio/aac,audio/mpeg,audio/wav,audio/x-wav,video/webm,video/mp4'],
        ]);

        $user = $request->user();
        $diskName = config('beszed.recordings.disk');
        $old = BeszedRecording::where('user_id', $user->id)->where('line_key', $data['line_key'])->first();

        $path = $data['file']->store("beszed/recordings/{$user->id}", $diskName);

        if ($old) {
            Storage::disk($old->disk)->delete($old->path);
        }

        $rec = BeszedRecording::updateOrCreate(
            ['user_id' => $user->id, 'line_key' => $data['line_key']],
            ['disk' => $diskName, 'path' => $path, 'mime' => $data['file']->getMimeType(), 'size' => $data['file']->getSize()],
        );

        return response()->json([
            'line_key' => $rec->line_key,
            'url' => route('beszed.recordings.audio', [$rec->line_key, 'v' => $rec->updated_at->timestamp]),
        ], 201);
    }

    public function audio(Request $request, string $lineKey): Response
    {
        $rec = BeszedRecording::where('user_id', $request->user()->id)->where('line_key', $lineKey)->firstOrFail();

        return Storage::disk($rec->disk)->response($rec->path, null, [
            'Content-Type' => $rec->mime ?: 'audio/mp4',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }

    public function destroy(Request $request, string $lineKey): Response
    {
        $rec = BeszedRecording::where('user_id', $request->user()->id)->where('line_key', $lineKey)->firstOrFail();
        Storage::disk($rec->disk)->delete($rec->path);
        $rec->delete();

        return response()->noContent();
    }
}
