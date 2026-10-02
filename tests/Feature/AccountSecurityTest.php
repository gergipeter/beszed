<?php

use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

function googleSays(string $sub, string $email, bool $verified = true): void
{
    config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
    $raw = ['sub' => $sub, 'name' => 'Anna', 'email' => $email, 'email_verified' => $verified];
    $user = (new GoogleUser)->setRaw($raw)->map(['id' => $sub, 'name' => 'Anna', 'email' => $email, 'avatar' => null]);
    $provider = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($user);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('has no public content API, and none of the unused enterprise endpoints', function () {
    $parent = User::factory()->create();
    $child = Child::create(['user_id' => $parent->id, 'name' => 'Zoé']);

    // Anyone could write live content for children through these.
    getJson('/api/content/zs')->assertNotFound();
    postJson('/api/content/zs', ['prompt' => 'x'])->assertNotFound();
    postJson('/api/content/zs/bulk', ['items' => []])->assertNotFound();
    expect(BeszedContentItem::where('source', 'api')->count())->toBe(0);

    foreach (['gamification/leaderboard/regional', 'gamification/achievements', 'enterprise/fhir/json', 'enterprise/encryption/keys',
        'adaptive/summary', 'dashboard', 'speech/history'] as $path) {
        actingAs($parent)->getJson("/api/beszed/children/{$child->id}/$path")->assertNotFound();
    }
});

it('believes X-Forwarded-For only from a proxy on a private network', function () {
    // Under /api/: the app's catch-all page route (registered first) would answer anything else.
    Route::get('/api/_test/ip', fn () => request()->ip());

    // A visitor straight from the internet cannot say who they are.
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->withHeaders(['X-Forwarded-For' => '198.51.100.7'])
        ->get('/api/_test/ip')->assertSee('203.0.113.9');

    // The tunnel / load balancer on our own network can.
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.4'])->withHeaders(['X-Forwarded-For' => '198.51.100.7'])
        ->get('/api/_test/ip')->assertSee('198.51.100.7');
});

it('gives editor and premium rights only to a confirmed e-mail address', function () {
    config(['beszed_content.admins' => ['editor@example.test']]);
    // Registered through the sign-up form with an editor's address: nobody has proved it is theirs.
    $squatter = User::factory()->unverified()->free()->create(['email' => 'editor@example.test']);

    actingAs($squatter)->getJson('/api/admin/content')->assertForbidden();
    actingAs($squatter)->getJson('/api/me')
        ->assertJsonPath('user.can_edit_content', false)->assertJsonPath('user.premium', false);

    $squatter->forceFill(['email_verified_at' => now()])->save();
    actingAs($squatter->fresh())->getJson('/api/admin/content')->assertOk();
    actingAs($squatter->fresh())->getJson('/api/me')->assertJsonPath('user.premium', true);
});

it('does not let Google hand over an unconfirmed password account to whoever registered it first', function () {
    // Someone signs up with the victim's address and a password of their own; nobody confirms the address.
    $planted = User::factory()->unverified()->create(['email' => 'anna@example.com', 'password' => 'Attacker-1234']);
    $oldToken = $planted->remember_token;

    googleSays('g-victim', 'anna@example.com');
    get('/auth/google/callback?code=abc')->assertRedirect('/');

    $account = $planted->fresh();
    expect($account->google_id)->toBe('g-victim')
        ->and($account->email_verified_at)->not->toBeNull()
        ->and(Hash::check('Attacker-1234', $account->password))->toBeFalse()
        ->and($account->remember_token)->not->toBe($oldToken);
    assertAuthenticatedAs($account);
});

it('keeps a confirmed account\'s password when Google is linked to it', function () {
    $owner = User::factory()->create(['email' => 'anna@example.com', 'password' => 'Mine-Own-1234']);

    googleSays('g-owner', 'anna@example.com');
    get('/auth/google/callback?code=abc')->assertRedirect('/');

    expect(Hash::check('Mine-Own-1234', $owner->fresh()->password))->toBeTrue();
});

it('counts a reset link sent to the address as proof of it, and starts the sessions over', function () {
    $user = User::factory()->unverified()->create(['email' => 'anna@example.com']);
    $oldToken = $user->remember_token;
    $token = Password::createToken($user);

    post('/auth/reset-password', ['token' => $token, 'email' => 'anna@example.com', 'password' => 'Brand-new-1234'])->assertNoContent();

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('Brand-new-1234', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe($oldToken);
});

it('ends a session that was opened with a password that has since changed', function () {
    $user = User::factory()->create();
    $stateful = ['Referer' => 'http://localhost'];

    // Opened with the current password: fine.
    actingAs($user)->withHeaders($stateful)->withSession(['password_hash_web' => $user->getAuthPassword()])
        ->getJson('/api/me')->assertOk();

    // Opened with an older one (the attacker's, before a reset or a Google take-over): signed out.
    actingAs($user)->withHeaders($stateful)->withSession(['password_hash_web' => 'hash-of-an-old-password'])
        ->getJson('/api/me')->assertUnauthorized();
});

it('ends a session that was signed in but never used before the password changed', function () {
    $user = User::factory()->create(['email' => 'anna@example.com', 'password' => 'Right-one-1234']);
    $stateful = ['Referer' => 'http://localhost'];

    // Signed in, and no request after that: Sanctum has not yet noted which password this session belongs to.
    post('/auth/login', ['email' => 'anna@example.com', 'password' => 'Right-one-1234'])->assertNoContent();
    expect(session('password_hash_web'))->not->toBeNull();
    assertAuthenticatedAs($user); // (so the 401 below is the password check, not a missing session)

    // First use, after the owner reset the password (a different session, which this test does not need to model).
    $user->forceFill(['password' => 'Changed-by-owner-1'])->save();
    app('auth')->forgetGuards(); // a real request loads the user afresh; this test app keeps the one from the sign-in
    getJson('/api/me', $stateful)->assertUnauthorized();
});

it('answers a repeated result with the first answer instead of counting it twice', function () {
    $this->seed(Database\Seeders\BeszedContentSeeder::class);
    $parent = User::factory()->create();
    $child = Child::create(['user_id' => $parent->id, 'name' => 'Zoé']);
    $item = BeszedContentItem::forGame('zs')->first();
    $result = ['game' => 'zs', 'content_item_id' => $item->id, 'level' => 1, 'correct' => true, 'tries' => 1];
    $url = "/api/beszed/children/{$child->id}/attempts";
    $key = ['Idempotency-Key' => 'b6f0c9c2-6c1e-4f43-9c43-0f0f6d1a7e11'];

    $first = actingAs($parent)->withHeaders($key)->postJson($url, $result)->assertCreated();
    // The reply was lost, so the app sends it again (now with the time it was played added).
    $again = actingAs($parent)->withHeaders($key)->postJson($url, $result + ['played_at' => now()->subMinute()->toIso8601String()])
        ->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

    expect($child->beszedAttempts()->count())->toBe(1)
        ->and($again->json())->toBe($first->json());

    // A different result (another key) is a different result.
    actingAs($parent)->withHeaders(['Idempotency-Key' => 'a-second-result-key'])->postJson($url, $result)->assertCreated();
    expect($child->beszedAttempts()->count())->toBe(2);

    // Without a key nothing changes: every post counts.
    actingAs($parent)->withoutHeader('Idempotency-Key')->postJson($url, $result)->assertCreated();
    expect($child->beszedAttempts()->count())->toBe(3);
});

it('does not replay one parent\'s answer to another', function () {
    $this->seed(Database\Seeders\BeszedContentSeeder::class);
    $item = BeszedContentItem::forGame('zs')->first();
    $result = ['game' => 'zs', 'content_item_id' => $item->id, 'level' => 1, 'correct' => true, 'tries' => 1];
    $key = ['Idempotency-Key' => 'the-same-key-for-both'];

    foreach ([User::factory()->create(), User::factory()->create()] as $parent) {
        $child = Child::create(['user_id' => $parent->id, 'name' => 'Zoé']);
        actingAs($parent)->withHeaders($key)->postJson("/api/beszed/children/{$child->id}/attempts", $result)
            ->assertCreated()->assertHeaderMissing('Idempotent-Replayed');
        expect($child->beszedAttempts()->count())->toBe(1);
    }
});
