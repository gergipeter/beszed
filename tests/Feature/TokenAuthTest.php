<?php

use App\Models\Child;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

use function Pest\Laravel\assertGuest;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
| The iOS / Android app signs in with a Bearer token (it runs on its own origin, where cookies cannot work).
*/
function asApp(string $token): array
{
    return ['Authorization' => "Bearer $token", 'Origin' => 'capacitor://app.beszed.local'];
}

it('signs a parent up and hands back a token that opens the API', function () {
    $response = postJson('/api/auth/register', [
        'name' => 'Anna Kiss', 'email' => 'Anna@Example.com', 'password' => 'Hosszu-jelszo-12', 'device_name' => 'iPhone',
    ])->assertCreated()->assertJsonStructure(['token', 'expires_at']);

    expect(User::where('email', 'anna@example.com')->sole()->tokens)->toHaveCount(1);
    assertGuest(); // no cookie session was started

    getJson('/api/me', asApp($response->json('token')))->assertOk()->assertJsonPath('user.email', 'anna@example.com');
});

it('signs in with the password, with the same checks as the web', function () {
    User::factory()->create(['email' => 'anna@example.com', 'password' => 'Hosszu-jelszo-12']);

    postJson('/api/auth/login', ['email' => 'anna@example.com', 'password' => 'wrong-password'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    $token = postJson('/api/auth/login', ['email' => 'ANNA@example.com', 'password' => 'Hosszu-jelszo-12', 'device_name' => 'Pixel'])
        ->assertOk()->json('token');
    getJson('/api/me', asApp($token))->assertOk();
});

it('locks sign-in after five wrong tries', function () {
    User::factory()->create(['email' => 'anna@example.com', 'password' => 'Hosszu-jelszo-12']);

    foreach (range(1, 5) as $_) {
        postJson('/api/auth/login', ['email' => 'anna@example.com', 'password' => 'nope-nope-nope'])->assertUnprocessable();
    }
    // even the right password waits for the minute to pass
    postJson('/api/auth/login', ['email' => 'anna@example.com', 'password' => 'Hosszu-jelszo-12'])->assertStatus(429);
});

it('does not sign up an address that already has an account', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    postJson('/api/auth/register', ['name' => 'X', 'email' => 'anna@example.com', 'password' => 'Hosszu-jelszo-12'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('refuses the API without a token, and after the token is revoked', function () {
    getJson('/api/me', ['Origin' => 'capacitor://app.beszed.local'])->assertUnauthorized();

    $user = User::factory()->create(['email' => 'anna@example.com', 'password' => 'Hosszu-jelszo-12']);
    $token = postJson('/api/auth/login', ['email' => 'anna@example.com', 'password' => 'Hosszu-jelszo-12'])->json('token');

    $this->withHeaders(asApp($token))->deleteJson('/api/auth/token')->assertNoContent();
    expect($user->tokens()->count())->toBe(0);
    app('auth')->forgetGuards();
    getJson('/api/me', asApp($token))->assertUnauthorized();
});

it('stops accepting a token once it has expired', function () {
    $user = User::factory()->create();
    $token = $user->createToken('app', ['*'], now()->subMinute())->plainTextToken;

    getJson('/api/me', asApp($token))->assertUnauthorized();
});

it('signs the app out everywhere when the password is reset', function () {
    $user = User::factory()->create(['email' => 'anna@example.com', 'password' => 'Hosszu-jelszo-12']);
    $token = postJson('/api/auth/login', ['email' => 'anna@example.com', 'password' => 'Hosszu-jelszo-12'])->json('token');
    $reset = Password::createToken($user);

    postJson('/api/auth/reset-password', ['token' => $reset, 'email' => 'anna@example.com', 'password' => 'Ujabb-jelszo-1234'])->assertNoContent();

    expect($user->fresh()->tokens()->count())->toBe(0)
        ->and(Hash::check('Ujabb-jelszo-1234', $user->fresh()->password))->toBeTrue();
    app('auth')->forgetGuards();
    getJson('/api/me', asApp($token))->assertUnauthorized();
});

it('asks for a reset link without telling whether the address has an account', function () {
    postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertNoContent();
    User::factory()->create(['email' => 'anna@example.com']);
    postJson('/api/auth/forgot-password', ['email' => 'anna@example.com'])->assertNoContent();
});

it('removes the app\'s tokens with the account', function () {
    $user = User::factory()->create(['email' => 'anna@example.com', 'password' => 'Hosszu-jelszo-12']);
    Child::create(['user_id' => $user->id, 'name' => 'Zoé']);
    $token = postJson('/api/auth/login', ['email' => 'anna@example.com', 'password' => 'Hosszu-jelszo-12'])->json('token');

    $this->withHeaders(asApp($token))->deleteJson('/api/me', ['confirm' => 'TÖRLÉS'])->assertNoContent();

    expect(User::count())->toBe(0)
        ->and(DB::table('personal_access_tokens')->count())->toBe(0);
});

it('lets the app\'s origin call the API (CORS), without cookies', function () {
    $this->withHeaders([
        'Origin' => 'capacitor://app.beszed.local',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'authorization,content-type,idempotency-key',
    ])->options('/api/beszed/children/1/attempts')
        ->assertSuccessful()
        ->assertHeader('Access-Control-Allow-Origin', 'capacitor://app.beszed.local')
        ->assertHeaderMissing('Access-Control-Allow-Credentials');

    // some other site gets no CORS permission
    $this->withHeaders(['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'GET'])
        ->options('/api/me')->assertHeaderMissing('Access-Control-Allow-Origin');
});
