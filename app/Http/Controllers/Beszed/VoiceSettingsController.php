<?php

namespace App\Http\Controllers\Beszed;

use App\Http\Controllers\Controller;
use App\Models\BeszedVoiceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** How Csillám sounds: voice, rate, pitch, server-vs-browser, mute. One row per parent. */
class VoiceSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->present(
            BeszedVoiceSettings::where('user_id', $request->user()->id)->first()
        ));
    }

    public function update(Request $request): JsonResponse
    {
        $rates = config('tts.rate_range');
        $pitches = config('tts.pitch_range');

        $data = $request->validate([
            'voice' => ['nullable', Rule::in(array_keys(config('tts.voices')))],
            'rate' => ['nullable', 'integer', "between:{$rates['min']},{$rates['max']}"],
            'pitch' => ['nullable', 'integer', "between:{$pitches['min']},{$pitches['max']}"],
            'prefer_server_tts' => ['required', 'boolean'],
            'muted' => ['required', 'boolean'],
        ]);

        $settings = BeszedVoiceSettings::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'voice' => $data['voice'] ?? null,
                'rate' => array_key_exists('rate', $data) && $data['rate'] !== null ? "{$data['rate']}%" : null,
                'pitch' => array_key_exists('pitch', $data) && $data['pitch'] !== null ? "{$data['pitch']}%" : null,
                'prefer_server_tts' => $data['prefer_server_tts'],
                'muted' => $data['muted'],
            ],
        );

        return response()->json($this->present($settings));
    }

    private function present(?BeszedVoiceSettings $settings): array
    {
        return [
            'voice' => $settings?->voice,
            'rate' => $settings?->rate ? (int) rtrim($settings->rate, '%') : null,
            'pitch' => $settings?->pitch ? (int) rtrim($settings->pitch, '%') : null,
            'preferServerTts' => $settings?->prefer_server_tts ?? true,
            'muted' => $settings?->muted ?? false,
        ];
    }
}
