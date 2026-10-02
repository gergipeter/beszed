<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Sign-in for the iOS and Android apps: the same e-mail sign-up and password sign-in as the web (and the same checks and
 * brute-force limit, see EmailAuthController), but the answer is a Bearer token instead of a cookie session. The app is
 * served from its own origin (capacitor://localhost, https://localhost), where Sanctum's cookies and XSRF header
 * cannot work. The app keeps the token in the device's secure storage and sends it as `Authorization: Bearer …`.
 *
 * Forgot / reset password are EmailAuthController's own routes, repeated under /api/auth (they hold no session).
 */
class TokenAuthController extends Controller
{
    /** A token that is not used for this long stops working; the parent signs in again. */
    private const TOKEN_DAYS = 90;

    public function register(Request $request, EmailAuthController $accounts): JsonResponse
    {
        $request->validate(['device_name' => ['nullable', 'string', 'max:60']]);

        return $this->issue($accounts->createAccount($request), $request, 201);
    }

    public function login(Request $request, EmailAuthController $accounts): JsonResponse
    {
        $request->validate(['device_name' => ['nullable', 'string', 'max:60']]);

        return $this->issue($accounts->authenticate($request, startSession: false), $request);
    }

    /** Ends this device's sign-in: the token it just used stops working. */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    private function issue(User $user, Request $request, int $status = 200): JsonResponse
    {
        $expires = now()->addDays(self::TOKEN_DAYS);
        $token = $user->createToken(Str::limit(trim((string) $request->input('device_name')) ?: 'app', 60, ''), ['*'], $expires);

        return response()->json(['token' => $token->plainTextToken, 'expires_at' => $expires->toIso8601String()], $status);
    }
}
