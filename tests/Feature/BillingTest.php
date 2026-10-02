<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    config([
        'billing.revenuecat.secret_key' => 'sk_test',
        'billing.revenuecat.webhook_secret' => 'hook-secret',
        'billing.revenuecat.api_url' => 'https://rc.test/v1',
    ]);
    $this->parent = User::factory()->free()->create();
});

function subscriber(?string $expires, string $product = 'beszed.premium.yearly', string $store = 'app_store'): array
{
    return ['subscriber' => [
        'entitlements' => $expires === 'none' ? [] : ['premium' => ['expires_date' => $expires, 'product_identifier' => $product]],
        'subscriptions' => [$product => ['store' => $store]],
    ]];
}

it('turns a verified purchase into the premium plan', function () {
    Http::fake(['rc.test/*' => Http::response(subscriber(now()->addYear()->toIso8601String()))]);

    actingAs($this->parent)->postJson('/api/billing/sync')->assertOk()->assertJsonPath('premium', true);

    expect($this->parent->fresh())
        ->subscription_plan->toBe('premium')
        ->subscription_source->toBe('app_store')
        ->subscription_expires_at->isFuture()->toBeTrue();
    Http::assertSent(fn ($r) => $r->url() === 'https://rc.test/v1/subscribers/'.$this->parent->id && $r->hasHeader('Authorization', 'Bearer sk_test'));
});

it('does not make a parent premium when RevenueCat has no entitlement or it has ended', function (string $body) {
    Http::fake(['rc.test/*' => Http::response($body === 'none' ? subscriber('none') : subscriber(now()->subDay()->toIso8601String()))]);

    actingAs($this->parent)->postJson('/api/billing/sync')->assertOk()->assertJsonPath('premium', false);

    expect($this->parent->fresh()->subscription_plan)->toBe('free');
})->with(['none', 'expired']);

it('takes premium away when a paid subscription lapses, but not a plan given by hand', function () {
    Http::fake(['rc.test/*' => Http::response(subscriber('none'))]);
    $paid = User::factory()->create(['subscription_source' => 'app_store', 'subscription_expires_at' => now()->subDay()]);
    $given = User::factory()->create(); // like the review account: premium, no store, no end date

    actingAs($paid)->postJson('/api/billing/sync')->assertOk();
    actingAs($given)->postJson('/api/billing/sync')->assertOk()->assertJsonPath('premium', true);

    expect($paid->fresh()->subscription_plan)->toBe('free')->and($given->fresh()->subscription_plan)->toBe('premium');
});

it('stops treating a paid plan as premium once its end date has passed', function () {
    $user = User::factory()->create(['subscription_expires_at' => now()->subMinute()]);

    actingAs($user)->getJson('/api/me')->assertOk()->assertJsonPath('user.premium', false);
});

it('leaves the stored plan alone and says so when RevenueCat cannot be reached', function () {
    Http::fake(['rc.test/*' => Http::response('down', 500)]);
    $paid = User::factory()->create(['subscription_source' => 'app_store', 'subscription_expires_at' => now()->addMonth()]);

    actingAs($paid)->postJson('/api/billing/sync')->assertStatus(502);

    expect($paid->fresh()->subscription_plan)->toBe('premium');
});

it('refuses to sync when billing is not set up, and when signed out', function () {
    config(['billing.revenuecat.secret_key' => null]);
    actingAs($this->parent)->postJson('/api/billing/sync')->assertStatus(503);

    app('auth')->forgetGuards();
    postJson('/api/billing/sync')->assertUnauthorized();
});

it('gives the app its RevenueCat setup, tied to this account', function () {
    config(['billing.revenuecat.public_key_ios' => 'appl_123']);

    actingAs($this->parent)->getJson('/api/billing')->assertOk()
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('app_user_id', (string) $this->parent->id)
        ->assertJsonPath('keys.ios', 'appl_123')
        ->assertJsonPath('products.yearly', 'beszed.premium.yearly')
        ->assertJsonMissingPath('secret_key');
});

it('re-reads the subscription when the webhook fires, and ignores the body for the verdict', function () {
    Http::fake(['rc.test/*' => Http::response(subscriber(now()->addMonth()->toIso8601String(), 'beszed.premium.monthly', 'play_store'))]);

    postJson('/api/webhooks/revenuecat', ['event' => ['type' => 'INITIAL_PURCHASE', 'app_user_id' => (string) $this->parent->id]], ['Authorization' => 'Bearer hook-secret'])
        ->assertOk();

    expect($this->parent->fresh())->subscription_plan->toBe('premium')->subscription_source->toBe('play_store');
});

it('rejects a webhook with the wrong or no secret', function (?string $header) {
    Http::fake();

    postJson('/api/webhooks/revenuecat', ['event' => ['app_user_id' => (string) $this->parent->id]], $header ? ['Authorization' => $header] : [])
        ->assertUnauthorized();

    Http::assertNothingSent();
    expect($this->parent->fresh()->subscription_plan)->toBe('free');
})->with([null, 'Bearer nope', 'hook-secret-but-longer']);

it('rejects every webhook while no webhook secret is set', function () {
    config(['billing.revenuecat.webhook_secret' => null]);

    postJson('/api/webhooks/revenuecat', ['event' => ['app_user_id' => (string) $this->parent->id]], ['Authorization' => 'Bearer '])->assertUnauthorized();
});

it('asks RevenueCat again on a webhook, so a lapse is picked up', function () {
    Http::fake(['rc.test/*' => Http::response(subscriber('none'))]);
    $paid = User::factory()->create(['subscription_source' => 'app_store', 'subscription_expires_at' => now()->addDay()]);

    postJson('/api/webhooks/revenuecat', ['event' => ['type' => 'EXPIRATION', 'app_user_id' => (string) $paid->id]], ['Authorization' => 'hook-secret'])->assertOk();

    expect($paid->fresh()->subscription_plan)->toBe('free');
});

it('tells RevenueCat to retry when it cannot reach RevenueCat itself', function () {
    Http::fake(['rc.test/*' => Http::response('down', 500)]);

    postJson('/api/webhooks/revenuecat', ['event' => ['app_user_id' => (string) $this->parent->id]], ['Authorization' => 'Bearer hook-secret'])->assertStatus(503);
});

it('ignores webhook ids that are not accounts here', function () {
    Http::fake();

    postJson('/api/webhooks/revenuecat', ['event' => ['app_user_id' => '$RCAnonymousID:abc', 'aliases' => ['999999']]], ['Authorization' => 'Bearer hook-secret'])->assertOk();

    Http::assertNothingSent();
});
