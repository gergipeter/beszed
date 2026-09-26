<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\AgeBands;
use App\Beszed\ProgressReport;
use App\Beszed\ReportNarrative;
use App\Http\Controllers\Controller;
use App\Models\BeszedShare;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Read-only progress links for the speech therapist. The parent creates, lists
 * and revokes them; anyone holding a live link sees the report, nothing else.
 */
class ShareController extends Controller
{
    use AuthorizesChild;

    public const DAYS = [7, 30, 90];

    private const MAX_ACTIVE = 5;

    public function index(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $shares = BeszedShare::where('child_id', $child->id)->latest('id')->limit(20)->get();

        return response()->json(['shares' => $shares->map(fn ($s) => $this->present($s))]);
    }

    public function store(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);
        $data = $request->validate([
            'days' => ['required', 'integer', Rule::in(self::DAYS)],
            'label' => ['nullable', 'string', 'max:80'],
        ]);
        abort_if(BeszedShare::where('child_id', $child->id)->active()->count() >= self::MAX_ACTIVE, 422,
            'Legfeljebb '.self::MAX_ACTIVE.' élő megosztás lehet egyszerre. Vonj vissza egy régebbit.');

        [$share, $token] = BeszedShare::issue($child, $data['days'], $data['label'] ?? null);

        return response()->json(['share' => $this->present($share), 'url' => url("/megosztas/$token")], 201);
    }

    public function destroy(Request $request, Child $child, BeszedShare $share): JsonResponse
    {
        $this->authorizeChild($request, $child);
        abort_unless($share->child_id === $child->id, 404);

        $share->revoked_at ??= now();
        $share->save();

        return response()->json(['share' => $this->present($share)]);
    }

    /** What the therapist sees. Deliberately small: first name, age band, the report. */
    public function show(string $token, ProgressReport $report, ReportNarrative $narrative): JsonResponse
    {
        $share = BeszedShare::findByToken($token);
        abort_unless($share?->isActive(), 404, 'Ez a link lejárt, vagy a szülő visszavonta.');

        $share->forceFill(['last_viewed_at' => now(), 'views' => $share->views + 1])->saveQuietly();
        $child = $share->child;
        $data = $report->for($child, 90);

        return response()
            ->json([
                'child' => ['name' => $child->name, 'age' => AgeBands::label(AgeBands::of($child))],
                'since' => $data['since'],
                'days' => $data['days'],
                'generatedAt' => now()->toIso8601String(),
                'expiresAt' => $share->expires_at->toIso8601String(),
                'games' => $data['games'],
                'areas' => $data['areas'],
                'narrative' => $narrative->narrative($data['areas']),
                'recommendations' => $narrative->recommendations($data['areas']),
            ])
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function present(BeszedShare $s): array
    {
        return [
            'id' => $s->id,
            'label' => $s->label,
            'createdAt' => $s->created_at?->toIso8601String(),
            'expiresAt' => $s->expires_at->toIso8601String(),
            'revokedAt' => $s->revoked_at?->toIso8601String(),
            'lastViewedAt' => $s->last_viewed_at?->toIso8601String(),
            'views' => $s->views,
            'active' => $s->isActive(),
        ];
    }
}
