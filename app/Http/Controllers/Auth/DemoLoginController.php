<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** One-tap sign-in as the seeded demo parent. Local development only. */
class DemoLoginController extends Controller
{
    public const EMAIL = 'parent@example.test';

    public static function enabled(): bool
    {
        return app()->isLocal();
    }

    public function __invoke(Request $request): Response
    {
        abort_unless(self::enabled(), 404);

        $user = User::firstOrCreate(['email' => self::EMAIL], ['name' => 'Demo szülő', 'password' => Str::random(40)]);
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->noContent();
    }
}
