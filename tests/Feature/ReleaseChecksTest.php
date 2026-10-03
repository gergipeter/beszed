<?php

use App\Console\Commands\ReviewAccount;
use App\Models\User;

use function Pest\Laravel\postJson;

// The helpers for a store release: the review account and the preflight check.

it('creates a review account that can sign in and reach premium without a purchase', function () {
    $this->artisan('beszed:review-account', ['--password' => 'review-pass-2026'])->assertSuccessful();

    $user = User::firstWhere('email', ReviewAccount::EMAIL);
    expect($user->subscription_plan)->toBe('premium')
        ->and($user->needsConsent())->toBeFalse()
        ->and($user->children()->count())->toBe(1);

    postJson('/auth/login', ['email' => ReviewAccount::EMAIL, 'password' => 'review-pass-2026'])->assertNoContent();
    $this->getJson('/api/me')->assertOk()->assertJsonPath('user.premium', true);
});

it('resets the review account instead of creating a second one', function () {
    $this->artisan('beszed:review-account', ['--password' => 'first-pass-2026'])->assertSuccessful();
    $this->artisan('beszed:review-account', ['--password' => 'second-pass-2026'])->assertSuccessful();

    expect(User::where('email', ReviewAccount::EMAIL)->count())->toBe(1);
    postJson('/auth/login', ['email' => ReviewAccount::EMAIL, 'password' => 'first-pass-2026'])->assertUnprocessable();
    postJson('/auth/login', ['email' => ReviewAccount::EMAIL, 'password' => 'second-pass-2026'])->assertNoContent();
});

it('preflight stops on the placeholders, the licence-restricted pictograms and a debug build', function () {
    config(['app.env' => 'production', 'app.debug' => true, 'app.url' => 'http://example.test', 'beszed_content.pictograms' => true]);

    $this->artisan('beszed:preflight')
        ->expectsOutputToContain('BLOCKER')
        ->assertFailed();
});

it('preflight passes when the release settings are in place', function () {
    app()->detectEnvironment(fn () => 'production');
    config([
        'privacy.controller' => 'Példa Kft., 1111 Budapest, Példa u. 1.',
        'privacy.contact' => 'adat@example.hu',
        'privacy.registration' => 'Cg. 01-09-123456',
        'privacy.tax_id' => '12345678-1-42',
        'privacy.hosting' => 'Példa Tárhely Kft., 1111 Budapest, tarhely@example.hu',
        'privacy.conciliation' => 'Budapesti Békéltető Testület, 1016 Budapest, Krisztina krt. 99.',
        'app.debug' => false,
        'app.url' => 'https://beszed.example.hu',
        'beszed_content.pictograms' => false,
        'stt.driver' => 'whisper',
        'tts.driver' => 'piper',
        'billing.revenuecat.secret_key' => 'sk_test',
        'billing.revenuecat.webhook_secret' => 'hook-secret',
    ]);

    $this->artisan('beszed:preflight')->expectsOutputToContain('No blockers')->assertSuccessful();
});
