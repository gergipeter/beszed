<?php

use App\Models\BeszedAttempt;
use App\Models\Child;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function spotlight(): ?string
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id.'/spotlight')
        ->assertOk()->json('game');
}

/** `$answers` attempts on `$game`, `$firstTry` of them right on the first try. */
function attemptsFor(string $game, int $answers, int $firstTry): void
{
    for ($i = 0; $i < $answers; $i++) {
        BeszedAttempt::create([
            'child_id' => test()->child->id, 'game' => $game, 'level' => 1,
            'correct' => true, 'tries' => $i < $firstTry ? 1 : 2,
        ]);
    }
}

it('is null until there is enough recent data for any game', function () {
    expect(spotlight())->toBeNull();

    attemptsFor('zs', answers: 4, firstTry: 1); // one short of the minimum
    expect(spotlight())->toBeNull();
});

it('suggests the game with the lowest first-try share', function () {
    attemptsFor('zs', answers: 8, firstTry: 6); // 75%
    attemptsFor('kezdo', answers: 8, firstTry: 2); // 25%, the weakest
    attemptsFor('hol', answers: 8, firstTry: 8); // 100%

    expect(spotlight())->toBe('kezdo');
});

it('ignores attempts older than two weeks', function () {
    attemptsFor('zs', answers: 8, firstTry: 8);

    BeszedAttempt::where('game', 'zs')->update(['created_at' => now()->subDays(20)]);

    expect(spotlight())->toBeNull();
});

it('keeps other families out', function () {
    $stranger = User::factory()->create();

    actingAs($stranger)
        ->getJson('/api/beszed/children/'.$this->child->id.'/spotlight')
        ->assertForbidden();
});
