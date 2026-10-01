<?php

use App\Beszed\Entitlements;
use App\Models\BeszedSkillLevel;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->free = User::factory()->free()->create();
    $this->freeChild = Child::create(['user_id' => $this->free->id, 'name' => 'Zoé']);
    $this->premium = User::factory()->create(); // the factory default is premium
    $this->premiumChild = Child::create(['user_id' => $this->premium->id, 'name' => 'Bence']);
    $this->cap = config('beszed_plans.free_max_level');
    // a game with levels beyond the free ones
    $this->game = collect(config('beszed.games'))->filter(fn ($g) => ($g['adaptive']['max'] ?? 0) > $this->cap + 1)->keys()->first();
});

function planSession(User $user, Child $child, string $game, string $query = '')
{
    return actingAs($user)->getJson("/api/beszed/children/{$child->id}/session?game=$game$query")->assertOk()->json();
}

function planSetLevel(Child $child, string $game, int $level, int $streak = 0): void
{
    BeszedSkillLevel::updateOrCreate(['child_id' => $child->id, 'game' => $game], ['level' => $level, 'streak' => $streak]);
}

function planAnswer(User $user, Child $child, string $game, int $level)
{
    return actingAs($user)->postJson("/api/beszed/children/{$child->id}/attempts",
        ['game' => $game, 'level' => $level, 'correct' => true, 'tries' => 1])->assertCreated()->json();
}

it('lets a free account play every game', function (string $game) {
    expect(planSession($this->free, $this->freeChild, $game)['rounds'])->not->toBeEmpty();
})->with(array_keys((require __DIR__.'/../../config/beszed.php')['games'])); // datasets load before the app boots

it('keeps a free account on the first levels, and says there is more behind them', function () {
    planSetLevel($this->freeChild, $this->game, $this->cap + 2);

    $session = planSession($this->free, $this->freeChild, $this->game);

    expect($session['level'])->toBe($this->cap)
        ->and($session['level_cap'])->toBe($this->cap)
        ->and(BeszedSkillLevel::first()->level)->toBe($this->cap + 2); // kept for when they upgrade
});

it('does not hold anything back from a premium account', function () {
    planSetLevel($this->premiumChild, $this->game, $this->cap + 2);

    $session = planSession($this->premium, $this->premiumChild, $this->game);

    expect($session['level'])->toBe($this->cap + 2)->and($session['level_cap'])->toBeNull();
});

it('does not say "more behind" for a game whose levels all fit the free plan', function () {
    $short = collect(config('beszed.games'))->filter(fn ($g) => isset($g['adaptive']) && $g['adaptive']['max'] <= $this->cap)->keys()->first();

    expect(planSession($this->free, $this->freeChild, $short)['level_cap'])->toBeNull();
});

it('stops a free account from levelling up past the cap, but a premium one climbs on', function () {
    $up = config("beszed.games.{$this->game}.adaptive.up_after");
    planSetLevel($this->freeChild, $this->game, $this->cap, $up - 1);
    planSetLevel($this->premiumChild, $this->game, $this->cap, $up - 1);

    expect(planAnswer($this->free, $this->freeChild, $this->game, $this->cap)['level'])->toBe($this->cap)
        ->and(planAnswer($this->premium, $this->premiumChild, $this->game, $this->cap)['level'])->toBe($this->cap + 1);
});

it('clamps a hand-picked level to the cap for a free account only', function () {
    $game = 'kirako';

    expect(planSession($this->free, $this->freeChild, $game, '&level=50')['level'])->toBe($this->cap)
        ->and(planSession($this->premium, $this->premiumChild, $game, '&level=50')['level'])->toBe(50);
});

it('tells the app whether the account is premium and what the free cap is', function () {
    actingAs($this->free)->getJson('/api/me')->assertOk()->assertJsonPath('user.premium', false);
    actingAs($this->premium)->getJson('/api/me')->assertOk()->assertJsonPath('user.premium', true);
    actingAs($this->free)->getJson('/api/beszed/meta')->assertOk()->assertJsonPath('freeMaxLevel', $this->cap);
});

it('treats the content editors as premium', function () {
    config(['beszed_content.admins' => [strtolower($this->free->email)]]);

    expect(app(Entitlements::class)->levelCap($this->free))->toBeNull();
});
