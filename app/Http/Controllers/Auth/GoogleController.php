<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as GoogleUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/** Parent sign-in with Google (Socialite). New Google accounts become new parents. */
class GoogleController extends Controller
{
    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirect(): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        if ($request->filled('error')) {
            return redirect('/login?error=cancelled');
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            // Expired state, replayed code, Google outage…
            report($e);

            return redirect('/login?error=failed');
        }

        $user = $this->findOrCreate($google);
        if (! $user) {
            return redirect('/login?error=email_taken');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    /** By Google id; else links an existing account with the same verified email; else creates one. */
    private function findOrCreate(GoogleUser $google): ?User
    {
        $verified = (bool) ($google->getRaw()['email_verified'] ?? false);
        $user = User::where('google_id', $google->getId())->first();
        $linking = false;

        if (! $user && $google->getEmail()) {
            $byEmail = User::where('email', $google->getEmail())->first();
            if ($byEmail && ! $verified) {
                return null; // someone else's address; don't take the account over
            }
            $user = $byEmail;
            $linking = $byEmail !== null;
        }

        $user ??= new User(['email' => $google->getEmail(), 'name' => $google->getName() ?: $google->getEmail()]);
        $user->fill(['google_id' => $google->getId(), 'avatar' => $google->getAvatar()]);
        if ($linking && ! $user->email_verified_at) {
            // A password account nobody confirmed (the sign-up form sends no e-mail) may have been opened by someone
            // else with this address. Google has just shown the address is this visitor's, so lock the old password
            // and every session or "remember me" cookie made with it out. The parent can set a new one by e-mail.
            $user->password = Str::random(40);
            $user->remember_token = Str::random(60);
            $user->tokens()->delete();
        }
        if ($verified && ! $user->email_verified_at) {
            $user->email_verified_at = now();
        }
        $user->save();

        return $user;
    }
}
