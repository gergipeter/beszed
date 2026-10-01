<?php

namespace App\Http\Controllers\Api;

use App\Beszed\Entitlements;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The signed-in parent, their children, and whether they still have to consent (401 when signed out). */
class MeController extends Controller
{
    public function __invoke(Request $request, Entitlements $plans): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user->only('id', 'name', 'email', 'avatar', 'milestone_emails_enabled', 'weekly_report_enabled') + ['can_edit_content' => $user->can('edit-content'), 'premium' => $plans->premium($user)],
            'consent' => ['required' => $user->needsConsent(), 'version' => config('privacy.version')],
            'children' => ChildController::present($user->children()->orderBy('id')->get()),
            // the óvodai jelek a child can pick from
            'signs' => collect(config('beszed.signs'))->map(fn ($s, $id) => ['id' => $id, 'name' => $s['name'], 'emoji' => $s['emoji']])->values(),
        ]);
    }
}
