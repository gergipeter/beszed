<?php

use App\Beszed\Rewards\BadgeRules;
use App\Beszed\Rewards\PlayerLevel;
use App\Models\BeszedAttempt;
use App\Models\Child;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
    travelTo(now('Europe/Budapest')->setTime(10, 0)->utc());
});

/** Plays a game: `correct` right answers (as attempts) then finishes the session. */
function finish(string $game = 'zs', int $rounds = 8, int $correct = 8, ?int $firstTry = null): array
{
    $child = test()->child;
    for ($i = 0; $i < $correct; $i++) {
        BeszedAttempt::create(['child_id' => $child->id, 'game' => $game, 'level' => 1, 'correct' => true, 'tries' => 1]);
    }

    return actingAs(test()->user)
        ->postJson("/api/beszed/children/{$child->id}/sessions", [
            'game' => $game, 'level' => 1, 'rounds' => $rounds, 'correct' => $correct, 'first_try' => $firstTry ?? $correct,
        ])
        ->assertCreated()
        ->json();
}

it('computes player levels from stars', function () {
    expect(collect([0, 9, 10, 29, 30, 60, 100])->map(fn ($s) => PlayerLevel::forStars($s, 5)['number'])->all())
        ->toBe([1, 1, 2, 2, 3, 4, 5])
        ->and(PlayerLevel::forStars(20, 5))->toMatchArray(['from' => 10, 'to' => 30, 'progress' => 0.5]);
});

it('awards the first sticker, a perfect sticker and a level-up on the first flawless game', function () {
    $res = finish(correct: 8);

    expect(collect($res['result']['new_badges'])->pluck('id')->all())->toBe(['first_game', 'perfect'])
        ->and($res['result'])->toMatchArray(['stars' => 8, 'medal' => 3, 'level_up' => false])
        ->and($res['level']['number'])->toBe(1)
        ->and($res['medals']['zs'])->toBe(3)
        ->and($res['daily'])->toBe(['done' => 1, 'goal' => 3])
        ->and($res['streak'])->toBe(['days' => 1, 'today' => true]);

    // Stickers are only new once.
    $again = finish(rounds: 4, correct: 4, firstTry: 2);
    expect($again['result']['new_badges'])->toBe([])
        ->and($again['result'])->toMatchArray(['medal' => 1, 'level_up' => true])
        ->and($again['result']['level_before']['number'])->toBe(1)
        ->and($again['level']['number'])->toBe(2)
        ->and(collect($again['result']['unlocked'])->pluck('id')->all())->toBe(['bow'])
        ->and($again['medals']['zs'])->toBe(3); // the best session counts
});

it('reaches the daily goal on the third game of the day', function () {
    finish();
    finish();
    $third = finish();

    expect(collect($third['result']['new_badges'])->pluck('id'))->toContain('daily_goal')
        ->and($third['daily']['done'])->toBe(3);
});

it('counts consecutive local days as a streak, and a missed day breaks it', function () {
    $day = now();
    foreach ([0, 1, 2] as $offset) {
        travelTo($day->copy()->addDays($offset));
        $res = finish();
    }
    expect($res['streak']['days'])->toBe(3)
        ->and(collect($res['result']['new_badges'])->pluck('id'))->toContain('streak_3');

    // Next day, not played yet: the streak is still alive.
    travelTo($day->copy()->addDays(3));
    $summary = actingAs($this->user)->getJson("/api/beszed/children/{$this->child->id}/rewards")->json();
    expect($summary['streak'])->toBe(['days' => 3, 'today' => false]);

    // A whole day missed: gone.
    travelTo($day->copy()->addDays(5));
    $summary = actingAs($this->user)->getJson("/api/beszed/children/{$this->child->id}/rewards")->json();
    expect($summary['streak']['days'])->toBe(0);
});

it('uses the configured timezone for day boundaries', function () {
    // 23:30 UTC on the 1st is already the 2nd in Budapest.
    travelTo(now()->setDate(2026, 10, 1)->setTime(23, 30));
    $res = finish();
    travelTo(now()->setDate(2026, 10, 2)->setTime(12, 0));
    $summary = actingAs($this->user)->getJson("/api/beszed/children/{$this->child->id}/rewards")->json();

    expect($res['daily']['done'])->toBe(1)
        ->and($summary['daily']['done'])->toBe(1)
        ->and($summary['streak'])->toBe(['days' => 1, 'today' => true]);
});

it('only lets Csillám wear unlocked accessories', function () {
    $wear = fn (string $slot, ?string $a) => actingAs($this->user)
        ->putJson("/api/beszed/children/{$this->child->id}/profile", ['slot' => $slot, 'accessory' => $a]);

    $wear('extra', 'bow')->assertStatus(422);
    finish(correct: 8);
    finish(correct: 8); // 16 stars → level 2
    $wear('extra', 'bow')->assertOk()->assertJsonPath('worn.extra', 'bow');
    $wear('head', 'bow')->assertStatus(422); // wrong slot
    $wear('head', 'crown')->assertStatus(422); // level 7
    $wear('extra', null)->assertOk()->assertJsonMissingPath('worn.extra');
    $wear('extra', 'nope')->assertStatus(422);
});

it('rejects impossible results and other families', function () {
    actingAs($this->user)->postJson("/api/beszed/children/{$this->child->id}/sessions",
        ['game' => 'zs', 'level' => 1, 'rounds' => 4, 'correct' => 5, 'first_try' => 1])->assertStatus(422);
    actingAs($this->user)->postJson("/api/beszed/children/{$this->child->id}/sessions",
        ['game' => 'zs', 'level' => 1, 'rounds' => 4, 'correct' => 2, 'first_try' => 3])->assertStatus(422);

    actingAs(User::factory()->create())->getJson("/api/beszed/children/{$this->child->id}/rewards")->assertForbidden();
});

it('has a valid rule for every sticker and a level for every accessory', function () {
    foreach (config('beszed.rewards.badges') as $id => $badge) {
        expect($badge['rule'][0])->toBeIn(BadgeRules::TYPES, $id);
        if ($badge['rule'][0] === 'game') {
            expect(config('beszed.games'))->toHaveKey($badge['rule'][1]);
        }
    }
    foreach (config('beszed.rewards.accessories') as $a) {
        expect($a['level'])->toBeGreaterThan(1);
    }
});

it('earns the explorer sticker after every game was finished once', function () {
    $games = array_keys(config('beszed.games'));
    foreach ($games as $i => $game) {
        $res = finish($game, 2, 1, 0);
        $ids = collect($res['result']['new_badges'])->pluck('id');
        expect($ids->contains('explorer'))->toBe($i === count($games) - 1);
    }
});
