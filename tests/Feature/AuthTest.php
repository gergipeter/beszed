<?php

use App\Models\Child;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

beforeEach(function () {
    config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
});

function fakeGoogle(array $raw): void
{
    $user = (new GoogleUser)->setRaw($raw)->map([
        'id' => $raw['sub'], 'name' => $raw['name'], 'email' => $raw['email'], 'avatar' => $raw['picture'] ?? null,
    ]);
    $provider = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($user);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('serves the app shell with the sign-in options', function () {
    $this->withoutVite();
    get('/')->assertOk()->assertSee('"google":true', false);
    get('/gyerekek')->assertOk();

    config(['services.google.client_id' => null]);
    get('/login')->assertOk()->assertSee('"google":false', false);
});

it('redirects to Google', function () {
    get('/auth/google/redirect')->assertRedirectContains('accounts.google.com');
});

it('hides Google sign-in when it is not configured', function () {
    config(['services.google.client_id' => null]);
    get('/auth/google/redirect')->assertNotFound();
});

it('creates a parent on the first Google sign-in and logs them in', function () {
    fakeGoogle(['sub' => 'g-1', 'name' => 'Anna Kiss', 'email' => 'anna@example.com', 'email_verified' => true, 'picture' => 'https://x/a.png']);

    get('/auth/google/callback?code=abc&state=xyz')->assertRedirect('/');

    $user = User::where('email', 'anna@example.com')->sole();
    expect($user)->google_id->toBe('g-1')->avatar->toBe('https://x/a.png')->name->toBe('Anna Kiss')
        ->and($user->email_verified_at)->not->toBeNull();
    assertAuthenticatedAs($user);
});

it('links Google to an existing account with the same verified email', function () {
    $existing = User::factory()->create(['email' => 'anna@example.com', 'name' => 'Anna']);
    fakeGoogle(['sub' => 'g-2', 'name' => 'Anna Kiss', 'email' => 'anna@example.com', 'email_verified' => true]);

    get('/auth/google/callback?code=abc')->assertRedirect('/');

    expect(User::count())->toBe(1)->and($existing->fresh()->google_id)->toBe('g-2');
    assertAuthenticatedAs($existing);
});

it('does not take over an account through an unverified email', function () {
    User::factory()->create(['email' => 'anna@example.com']);
    fakeGoogle(['sub' => 'g-3', 'name' => 'X', 'email' => 'anna@example.com', 'email_verified' => false]);

    get('/auth/google/callback?code=abc')->assertRedirect('/login?error=email_taken');
    assertGuest();
});

it('returns to the sign-in page when the parent cancels at Google', function () {
    get('/auth/google/callback?error=access_denied')->assertRedirect('/login?error=cancelled');
    assertGuest();
});

it('offers a demo sign-in only locally', function () {
    $this->post('/auth/demo')->assertNotFound(); // testing env

    app()->detectEnvironment(fn () => 'local');
    // Outside "testing" Laravel checks CSRF again; the SPA sends the XSRF header for real.
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->post('/auth/demo')->assertNoContent();
    assertAuthenticatedAs(User::where('email', 'parent@example.test')->sole());
});

it('tells the app who is signed in, and 401 when nobody is', function () {
    getJson('/api/me')->assertUnauthorized();

    $user = User::factory()->create(['name' => 'Anna']);
    Child::create(['user_id' => $user->id, 'name' => 'Zoé']);

    actingAs($user)->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('user.name', 'Anna')
        ->assertJsonPath('children.0.name', 'Zoé')
        ->assertJsonMissingPath('user.google_id');
});

it('lets a parent add, rename and delete only their own children', function () {
    $user = User::factory()->create();

    $id = actingAs($user)->postJson('/api/children', ['name' => 'Bence', 'birth_date' => '2021-05-01'])
        ->assertCreated()->assertJsonPath('child.name', 'Bence')->json('child.id');
    actingAs($user)->putJson("/api/children/$id", ['name' => 'Benci'])->assertOk()->assertJsonPath('child.name', 'Benci');
    actingAs($user)->postJson('/api/children', ['name' => ''])->assertStatus(422);

    $stranger = User::factory()->create();
    actingAs($stranger)->putJson("/api/children/$id", ['name' => 'X'])->assertForbidden();
    actingAs($stranger)->deleteJson("/api/children/$id")->assertForbidden();

    actingAs($user)->deleteJson("/api/children/$id")->assertNoContent();
    expect(Child::find($id))->toBeNull();
});

it('logs out', function () {
    $user = User::factory()->create();
    actingAs($user)->post('/logout')->assertNoContent();
    assertGuest('web');
});
