<?php

use App\Models\BeszedAttempt;
use App\Models\BeszedShare;
use App\Models\Child;
use App\Models\User;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\travel;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé', 'birth_date' => CarbonImmutable::now()->subYears(5)->subMonth()]);
});

function shareLink(int $days = 30, ?string $label = 'Kovács Anna logopédus'): string
{
    $url = actingAs(test()->user)->postJson('/api/beszed/children/'.test()->child->id.'/shares', ['days' => $days, 'label' => $label])
        ->assertCreated()->json('url');

    return substr($url, strrpos($url, '/') + 1);
}

it('gives the therapist a read-only report without sign-in, and nothing personal beyond it', function () {
    foreach ([true, true, false, true, true, true] as $ok) {
        BeszedAttempt::create(['child_id' => $this->child->id, 'game' => 'kezdo', 'level' => 1, 'correct' => $ok, 'tries' => $ok ? 1 : 3]);
    }
    $token = shareLink();
    auth()->forgetGuards();

    $report = getJson("/api/share/$token")->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();

    expect($report['child'])->toBe(['name' => 'Zoé', 'age' => '5–6 év'])
        ->and(collect($report['areas'])->firstWhere('key', 'beszedhanghallas'))
        ->toMatchArray(['rounds' => 6, 'firstTryRate' => 0.83, 'band' => 'strong'])
        ->and($report)->toHaveKeys(['narrative', 'recommendations', 'games', 'expiresAt'])
        ->and(json_encode($report))->not->toContain($this->user->email)->not->toContain('birth');

    expect(BeszedShare::sole())->views->toBe(1)->last_viewed_at->not->toBeNull()
        ->and(BeszedShare::sole()->token_hash)->not->toBe($token);
});

it('stops working when revoked or expired', function () {
    $token = shareLink(7);
    getJson("/api/share/$token")->assertOk();

    travel(8)->days();
    getJson("/api/share/$token")->assertNotFound();
    travel(-8)->days();

    $share = BeszedShare::sole();
    actingAs($this->user)->deleteJson("/api/beszed/children/{$this->child->id}/shares/{$share->id}")
        ->assertOk()->assertJsonPath('share.active', false);
    getJson("/api/share/$token")->assertNotFound();
    getJson('/api/share/'.str_repeat('a', 40))->assertNotFound();
});

it('lists the parent\'s links without their tokens, and keeps other families out', function () {
    shareLink();
    $list = actingAs($this->user)->getJson("/api/beszed/children/{$this->child->id}/shares")->assertOk()->json('shares');
    expect($list)->toHaveCount(1)->and($list[0])->not->toHaveKey('token_hash')->and($list[0]['label'])->toBe('Kovács Anna logopédus');

    $stranger = User::factory()->create();
    actingAs($stranger)->postJson("/api/beszed/children/{$this->child->id}/shares", ['days' => 30])->assertForbidden();
    actingAs($stranger)->getJson("/api/beszed/children/{$this->child->id}/shares")->assertForbidden();
    actingAs($stranger)->deleteJson("/api/beszed/children/{$this->child->id}/shares/".BeszedShare::sole()->id)->assertForbidden();
});

it('limits the validity and the number of live links', function () {
    actingAs($this->user)->postJson("/api/beszed/children/{$this->child->id}/shares", ['days' => 365])->assertUnprocessable();
    foreach (range(1, 5) as $i) {
        shareLink(7, null);
    }
    actingAs($this->user)->postJson("/api/beszed/children/{$this->child->id}/shares", ['days' => 7])->assertUnprocessable();
});

it('serves the share page without passing the token on', function () {
    $this->get('/megosztas/'.str_repeat('a', 40))->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});
