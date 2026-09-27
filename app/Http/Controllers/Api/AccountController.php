<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BeszedAttempt;
use App\Models\BeszedBadge;
use App\Models\BeszedDailyPath;
use App\Models\BeszedRecording;
use App\Models\BeszedSession;
use App\Models\BeszedShare;
use App\Models\BeszedSkillLevel;
use App\Beszed\Reports\WeeklyReportSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The parent's own account: consent, data export, deletion, preferences. */
class AccountController extends Controller
{
    public function consent(Request $request): JsonResponse
    {
        $request->validate(['accept' => ['accepted']]);

        $user = $request->user();
        $user->forceFill(['consented_at' => now(), 'consent_version' => config('privacy.version')])->save();

        return response()->json(['consent' => ['required' => false, 'version' => $user->consent_version]]);
    }

    /** The parent's e-mails: a note on each milestone (MilestoneEarned), and the weekly report. */
    public function preferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'milestone_emails_enabled' => ['sometimes', 'boolean'],
            'weekly_report_enabled' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $user->forceFill($data)->save();

        return response()->json($user->only('milestone_emails_enabled', 'weekly_report_enabled'));
    }

    /** This week's report for every child who played, e-mailed now (a sample; the real one comes on Sunday). */
    public function weeklyReportSample(Request $request, WeeklyReportSender $sender): JsonResponse
    {
        return response()->json(['sent' => $sender->sendFor($request->user(), force: true)]);
    }

    /** Everything stored about the parent and their children, as a JSON download. */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $children = $user->children()->get();

        $data = [
            'exported_at' => now()->toIso8601String(),
            'parent' => $user->only('name', 'email', 'created_at', 'consented_at', 'consent_version'),
            'children' => $children->map(fn ($c) => [
                'name' => $c->name,
                'birth_date' => $c->birth_date?->toDateString(),
                'created_at' => $c->created_at,
                'levels' => BeszedSkillLevel::where('child_id', $c->id)->get(['game', 'level', 'updated_at']),
                'games_finished' => BeszedSession::where('child_id', $c->id)->orderBy('completed_at')
                    ->get(['game', 'level', 'rounds', 'correct', 'first_try', 'duration_ms', 'completed_at']),
                'answers' => BeszedAttempt::where('child_id', $c->id)->orderBy('created_at')
                    ->get(['game', 'level', 'correct', 'tries', 'duration_ms', 'created_at']),
                'stickers' => BeszedBadge::where('child_id', $c->id)->get(['badge', 'earned_at']),
                'wearing' => $c->beszedProfile?->accessories,
                'sticker_scene' => $c->beszedProfile?->scene,
                'daily_paths' => BeszedDailyPath::where('child_id', $c->id)->orderBy('day')->get(['day', 'games', 'done', 'completed_at']),
                // Links given to a therapist (the link itself is not stored, only its hash).
                'share_links' => BeszedShare::where('child_id', $c->id)->get(['label', 'created_at', 'expires_at', 'revoked_at', 'last_viewed_at', 'views']),
            ])->values(),
            // The audio itself stays downloadable in the app; listed here by line.
            'voice_recordings' => BeszedRecording::where('user_id', $user->id)->get(['line_key', 'mime', 'size', 'updated_at']),
        ];

        return response()->streamDownload(
            fn () => print(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'beszed-adataim-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    /** Deletes the parent, every child and all their results, and the voice recording files. */
    public function destroy(Request $request): Response
    {
        $request->validate(['confirm' => ['required', 'in:TÖRLÉS']]);
        $user = $request->user();
        $files = BeszedRecording::where('user_id', $user->id)->get(['disk', 'path']);

        // Log out BEFORE deleting: logout() cycles the remember-me token by saving the
        // user, which would re-insert an already deleted row.
        // (The SPA calls this with its cookie session; other API clients have none to end.)
        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Children, their results, stickers, levels and the recording rows go with the user (FK cascades).
        DB::transaction(fn () => $user->delete());
        foreach ($files as $file) {
            Storage::disk($file->disk)->delete($file->path);
        }

        return response()->noContent();
    }
}
