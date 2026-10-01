<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

// Sign-in with an e-mail address and a password: the sign-in of the iOS / Android apps.

const GOOD_PASSWORD = 'kiscica-2026-ok';

function registerBody(array $over = []): array
{
    return $over + ['name' => 'Anna Kiss', 'email' => 'Anna@Example.com', 'password' => GOOD_PASSWORD];
}

it('registers a parent, signs them in and keeps the e-mail in lower case', function () {
    postJson('/auth/register', registerBody())->assertCreated();

    $user = User::firstWhere('email', 'anna@example.com');
    expect($user)->not->toBeNull()->and($user->name)->toBe('Anna Kiss')
        ->and($user->password)->not->toBe(GOOD_PASSWORD); // hashed
    assertAuthenticatedAs($user);
    getJson('/api/me')->assertOk()->assertJsonPath('user.email', 'anna@example.com');
});

it('refuses an address that already has an account, and weak passwords', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    postJson('/auth/register', registerBody())->assertUnprocessable()->assertJsonValidationErrors('email');
    postJson('/auth/register', registerBody(['email' => 'new@example.com', 'password' => 'short1']))->assertUnprocessable()->assertJsonValidationErrors('password');
    postJson('/auth/register', registerBody(['email' => 'new@example.com', 'password' => 'onlyletterspassword']))->assertUnprocessable()->assertJsonValidationErrors('password');
    postJson('/auth/register', registerBody(['email' => 'not-an-email']))->assertUnprocessable()->assertJsonValidationErrors('email');
    assertGuest();
});

it('signs in with the right password, whatever the case of the address', function () {
    $user = User::factory()->create(['email' => 'anna@example.com', 'password' => GOOD_PASSWORD]);

    postJson('/auth/login', ['email' => '  ANNA@example.com ', 'password' => GOOD_PASSWORD])->assertNoContent();

    assertAuthenticatedAs($user);
});

it('says the same for a wrong password and an unknown address', function () {
    User::factory()->create(['email' => 'anna@example.com', 'password' => GOOD_PASSWORD]);

    $wrong = postJson('/auth/login', ['email' => 'anna@example.com', 'password' => 'not-the-password'])->assertUnprocessable();
    $unknown = postJson('/auth/login', ['email' => 'nobody@example.com', 'password' => GOOD_PASSWORD])->assertUnprocessable();

    expect($wrong->json('errors.email'))->toBe($unknown->json('errors.email'));
    assertGuest();
});

it('stops guessing after five wrong tries for one address', function () {
    RateLimiter::clear('login:anna@example.com|127.0.0.1');
    User::factory()->create(['email' => 'anna@example.com', 'password' => GOOD_PASSWORD]);

    foreach (range(1, 5) as $i) {
        postJson('/auth/login', ['email' => 'anna@example.com', 'password' => "wrong-$i"])->assertUnprocessable();
    }
    // even the right password is refused for a minute
    postJson('/auth/login', ['email' => 'anna@example.com', 'password' => GOOD_PASSWORD])->assertStatus(429);
    assertGuest();
});

it('sends a reset link to the app page, and tells nobody whether the address exists', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'anna@example.com']);

    postJson('/auth/forgot-password', ['email' => 'anna@example.com'])->assertNoContent();
    postJson('/auth/forgot-password', ['email' => 'nobody@example.com'])->assertNoContent();

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $url = ResetPassword::$createUrlCallback ? call_user_func(ResetPassword::$createUrlCallback, $user, $notification->token) : '';

        return str_contains($url, '/jelszo-visszaallitas?token='.$notification->token) && str_contains($url, 'anna%40example.com');
    });
    Notification::assertCount(1);
});

it('sets a new password from the link, once', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'anna@example.com', 'password' => 'old-password-123']);
    postJson('/auth/forgot-password', ['email' => 'anna@example.com']);
    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
        $token = $n->token;

        return true;
    });

    postJson('/auth/reset-password', ['token' => $token, 'email' => 'anna@example.com', 'password' => 'brand-new-pass-9'])->assertNoContent();
    postJson('/auth/login', ['email' => 'anna@example.com', 'password' => 'brand-new-pass-9'])->assertNoContent();
    assertAuthenticatedAs($user);

    // the link works once
    postJson('/auth/reset-password', ['token' => $token, 'email' => 'anna@example.com', 'password' => 'another-pass-77'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('refuses a made-up reset token', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    postJson('/auth/reset-password', ['token' => 'made-up', 'email' => 'anna@example.com', 'password' => GOOD_PASSWORD])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('tells the app that e-mail sign-in is available', function () {
    $this->withoutVite();

    $this->get('/login')->assertOk()->assertSee('"email":true', false);
});
