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

    expect(collect($res['result']['new_badges'])->pluck('id')->all())->toBe(['first_game', 'perfect', 'collector_1']) // collector_N: the generated sticker for the Nth finished game
        ->and($res['result'])->toMatchArray(['stars' => 8, 'medal' => 3, 'level_up' => false])
        ->and($res['level']['number'])->toBe(1)
        ->and($res['medals']['zs'])->toBe(3)
        ->and($res['daily'])->toBe(['done' => 1, 'goal' => 3])
        ->and($res['streak'])->toMatchArray(['days' => 1, 'today' => true]);

    // Stickers are only new once: only the next collector sticker is new on the second game.
    $again = finish(rounds: 4, correct: 4, firstTry: 2);
    expect(collect($again['result']['new_badges'])->pluck('id')->all())->toBe(['collector_2'])
        ->and($again['result'])->toMatchArray(['medal' => 1, 'level_up' => true])
        ->and($again['result']['level_before']['number'])->toBe(1)
        ->and($again['level']['number'])->toBe(2)
        ->and(collect($again['result']['unlocked'])->pluck('id')->all())->toBe(['cap', 'bow']) // level 2 brings two new things for Csillám
        ->and($again['medals']['zs'])->toBe(3); // the best session counts
});

it('saves a sticker scene with its scale', function () {
    finish(correct: 8); // earns the "first_game" badge, so it can be placed

    $save = fn (array $stickers) => actingAs($this->user)
        ->putJson("/api/beszed/children/{$this->child->id}/scene", ['background' => 'meadow', 'stickers' => $stickers]);

    $saved = $save([['badge' => 'first_game', 'x' => 50, 'y' => 50, 'rotate' => 10, 'scale' => 1.8]])->assertOk()->json();
    expect($saved['stickers'][0])->toMatchArray(['badge' => 'first_game', 'rotate' => 10, 'scale' => 1.8]);

    // out of 0.5-2.5 range: rejected, same as rotate outside -180..180
    $save([['badge' => 'first_game', 'x' => 50, 'y' => 50, 'scale' => 99]])
        ->assertInvalid(['stickers.0.scale']);

    // missing scale defaults to 1 (older saved scenes, or a sticker just dropped)
    $defaulted = $save([['badge' => 'first_game', 'x' => 50, 'y' => 50]])->assertOk()->json();
    expect($defaulted['stickers'][0]['scale'])->toEqual(1);
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
    expect($summary['streak'])->toMatchArray(['days' => 3, 'today' => false])
        ->and($summary['streak']['recent'])->toHaveCount(14)
        ->and(collect($summary['streak']['recent'])->pluck('played')->all())->toBe([false, false, false, false, false, false, false, false, false, false, true, true, true, false]);

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
        ->and($summary['streak'])->toMatchArray(['days' => 1, 'today' => true])
        ->and(end($summary['streak']['recent']))->toMatchArray(['date' => '2026-10-02', 'played' => true]);
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
    $wear('tail', 'bow')->assertStatus(422); // no such slot
});

it('dresses Csillám in every slot at once, her mane in a new colour too', function () {
    foreach (range(1, 4) as $i) {
        finish(correct: 8); // 32 stars → level 3
    }
    $wear = fn (string $slot, string $a) => actingAs($this->user)
        ->putJson("/api/beszed/children/{$this->child->id}/profile", ['slot' => $slot, 'accessory' => $a])->assertOk();

    $wear('head', 'cap');
    $wear('face', 'glasses');
    $wear('extra', 'bow');
    $worn = $wear('mane', 'mane_candy')->json('worn');

    expect($worn)->toBe(['head' => 'cap', 'face' => 'glasses', 'extra' => 'bow', 'mane' => 'mane_candy']);
    actingAs($this->user)->putJson("/api/beszed/children/{$this->child->id}/profile", ['slot' => 'neck', 'accessory' => 'scarf'])
        ->assertStatus(422); // the scarf comes at level 4
});

it('rejects impossible results and other families', function () {
    actingAs($this->user)->postJson("/api/beszed/children/{$this->child->id}/sessions",
        ['game' => 'zs', 'level' => 1, 'rounds' => 4, 'correct' => 5, 'first_try' => 1])->assertStatus(422);
    actingAs($this->user)->postJson("/api/beszed/children/{$this->child->id}/sessions",
        ['game' => 'zs', 'level' => 1, 'rounds' => 4, 'correct' => 2, 'first_try' => 3])->assertStatus(422);

    actingAs(User::factory()->create())->getJson("/api/beszed/children/{$this->child->id}/rewards")->assertForbidden();
});

it('keeps every rate limit to its own route: a busy voice never blocks saving a game', function () {
    config(['tts.driver' => null]);
    app()->forgetInstance(\App\Beszed\Tts\TtsClient::class);
    foreach (range(1, 120) as $i) {
        actingAs($this->user)->getJson('/api/beszed/tts?t=Szia')->assertNotFound(); // no voice: the browser speaks
    }
    actingAs($this->user)->getJson('/api/beszed/tts?t=Szia')->assertStatus(429);

    finish(); // sessions has its own allowance (asserts 201)
});

it('has a valid rule for every sticker and a level for every accessory', function () {
    foreach (config('beszed.rewards.badges') as $id => $badge) {
        expect($badge['rule'][0])->toBeIn(BadgeRules::TYPES, $id);
        if ($badge['rule'][0] === 'game') {
            expect(config('beszed.games'))->toHaveKey($badge['rule'][1]);
        }
    }
    foreach (config('beszed.rewards.accessories') as $id => $a) {
        expect($a['level'])->toBeGreaterThan(1)
            // the slots Csillám's drawing knows (CsillamAvatar.vue, accessories.js)
            ->and($a['slot'])->toBeIn(['head', 'face', 'neck', 'extra', 'mane'], $id);
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
