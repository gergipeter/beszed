<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Parent sign-in with an e-mail address and a password. This is the sign-in the iOS and Android apps use
 * (Google blocks its sign-in inside an app's web view, and Apple's rule 4.8 asks for an equivalent option
 * next to it): an app that uses only its own sign-in is exempt. The web app can offer Google as well.
 */
class EmailAuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function register(Request $request): Response
    {
        Auth::login($this->createAccount($request), remember: true);
        $request->session()->regenerate();

        return response()->noContent(201);
    }

    /** The sign-up form's checks and the new parent, without signing anyone in (the web and the app's token sign-up share it). */
    public function createAccount(Request $request): User
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'password' => ['required', 'string', PasswordRule::min(10)->letters()->numbers(), 'max:128'],
        ]);
        $email = Str::lower(trim($data['email']));

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Ezzel az e-mail-címmel már van fiók. Jelentkezz be, vagy kérj új jelszót!']);
        }

        return User::create(['name' => trim($data['name']), 'email' => $email, 'password' => $data['password']]);
    }

    public function login(Request $request): Response
    {
        $this->authenticate($request, startSession: true);
        $request->session()->regenerate();

        return response()->noContent();
    }

    /**
     * Checks the e-mail and password, with the sign-in's brute-force limit. With `$startSession` the parent is signed
     * in with a cookie session (web); without, nothing is started and the caller hands out a token (app).
     */
    public function authenticate(Request $request, bool $startSession): User
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:128'],
        ]);
        $email = Str::lower(trim($data['email']));
        // five wrong tries a minute for one address from one place, however many addresses an attacker has
        $key = "login:$email|{$request->ip()}";

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['email' => 'Túl sok próbálkozás. Várj egy percet, és próbáld újra!'])->status(429);
        }

        $credentials = ['email' => $email, 'password' => $data['password']];
        $ok = $startSession ? Auth::attempt($credentials, remember: true) : Auth::validate($credentials);
        if (! $ok) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Hibás e-mail-cím vagy jelszó.']);
        }

        RateLimiter::clear($key);

        return $startSession ? Auth::user() : User::where('email', $email)->firstOrFail();
    }

    /** Always answers the same, so it cannot be used to find out who has an account. */
    public function forgot(Request $request): Response
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:190']]);

        Password::sendResetLink(['email' => Str::lower(trim($data['email']))]);

        return response()->noContent();
    }

    public function reset(Request $request): Response
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'string', PasswordRule::min(10)->letters()->numbers(), 'max:128'],
        ]);

        $status = Password::reset(
            ['email' => Str::lower(trim($data['email'])), 'password' => $data['password'], 'token' => $data['token']],
            function (User $user, string $password) {
                // The reset link went to this address, so whoever used it owns it: the address now counts as confirmed.
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
                $user->tokens()->delete(); // the apps sign in again with the new password
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'A link lejárt vagy már felhasználták. Kérj új jelszó-visszaállító levelet!']);
        }

        return response()->noContent();
    }
}
