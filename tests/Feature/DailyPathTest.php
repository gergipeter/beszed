<?php

use App\Models\BeszedAttempt;
use App\Models\BeszedDailyPath;
use App\Models\Child;
use App\Models\User;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
    travelTo(CarbonImmutable::parse('2026-09-23 09:00', 'Europe/Budapest'));
});

function todaysPath(): array
{
    return actingAs(test()->user)->getJson('/api/beszed/children/'.test()->child->id.'/daily-path')->assertOk()->json();
}

function finishGame(string $game): array
{
    return actingAs(test()->user)->postJson('/api/beszed/children/'.test()->child->id.'/sessions',
        ['game' => $game, 'level' => 1, 'rounds' => 4, 'correct' => 4, 'first_try' => 3, 'duration_ms' => 60_000])
        ->assertCreated()->json();
}

it('suggests three games from different skill areas, the same all day', function () {
    $path = todaysPath();
    $areaOf = collect(config('beszed_skills.areas'))->flatMap(fn ($a, $k) => array_fill_keys($a['games'], $k));

    expect($path['day'])->toBe('2026-09-23')
        ->and($path['games'])->toHaveCount(3)
        ->and(collect($path['games'])->map(fn ($g) => $areaOf[$g])->unique())->toHaveCount(3)
        ->and($path['done'])->toBe([])
        ->and($path['completed'])->toBeFalse();

    travelTo(CarbonImmutable::parse('2026-09-23 21:00', 'Europe/Budapest'));
    expect(todaysPath()['games'])->toBe($path['games'])
        ->and(BeszedDailyPath::count())->toBe(1);
});

it('puts the game that needs practice first', function () {
    foreach (range(1, 10) as $i) {
        BeszedAttempt::create(['child_id' => $this->child->id, 'game' => 'rimelo', 'level' => 1, 'correct' => $i > 8, 'tries' => $i > 8 ? 1 : 3]);
    }
    foreach (config('beszed.games') as $id => $g) {
        if ($id !== 'rimelo') {
            foreach (range(1, 6) as $i) {
                BeszedAttempt::create(['child_id' => $this->child->id, 'game' => $id, 'level' => 1, 'correct' => true, 'tries' => 1]);
            }
        }
    }

    expect(todaysPath()['games'][0])->toBe('rimelo');
});

it('ticks the steps as games are finished, and celebrates the whole path', function () {
    $games = todaysPath()['games'];
    $off = collect(array_keys(config('beszed.games')))->first(fn ($g) => ! in_array($g, $games, true));

    expect(finishGame($off)['result']['daily_path'])->toBeNull();

    $first = finishGame($games[0])['result']['daily_path'];
    expect($first)->toMatchArray(['done' => [$games[0]], 'ticked' => true, 'just_completed' => false]);
    expect(finishGame($games[0])['result']['daily_path']['ticked'])->toBeFalse(); // played again: no new step

    finishGame($games[1]);
    $last = finishGame($games[2])['result'];

    expect($last['daily_path'])->toMatchArray(['completed' => true, 'just_completed' => true])
        ->and(collect($last['new_badges'])->pluck('id'))->toContain('path_1')
        ->and(todaysPath()['completed'])->toBeTrue();
});

it('gives a new path the next day, avoiding yesterday\'s games', function () {
    $monday = todaysPath()['games'];
    travelTo(CarbonImmutable::parse('2026-09-24 08:00', 'Europe/Budapest'));
    $tuesday = todaysPath();

    expect($tuesday['day'])->toBe('2026-09-24')
        ->and(array_intersect($monday, $tuesday['games']))->toHaveCount(0);
});
