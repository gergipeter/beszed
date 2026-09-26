<?php

use App\Models\BeszedAttempt;
use App\Models\Child;
use App\Models\User;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
    // Wednesday noon in Budapest.
    travelTo(CarbonImmutable::parse('2026-09-23 12:00', 'Europe/Budapest'));
});

function answer(bool $correct, int $tries, ?string $at = null, string $game = 'zs'): void
{
    $payload = ['game' => $game, 'level' => 1, 'correct' => $correct, 'tries' => $tries];
    if ($at) {
        $payload['played_at'] = $at;
    }
    actingAs(test()->user)->postJson('/api/beszed/children/'.test()->child->id.'/attempts', $payload)->assertCreated();
}

it('buckets answers and finished games by local week, oldest first', function () {
    answer(true, 1);
    answer(false, 2);
    answer(true, 1, CarbonImmutable::parse('2026-09-15 10:00', 'Europe/Budapest')->toIso8601String()); // previous week
    actingAs($this->user)->postJson("/api/beszed/children/{$this->child->id}/sessions",
        ['game' => 'zs', 'level' => 1, 'rounds' => 2, 'correct' => 1, 'first_try' => 1, 'duration_ms' => 90_000])->assertCreated();

    $weeks = actingAs($this->user)->getJson("/api/beszed/children/{$this->child->id}/progress/history?weeks=4")
        ->assertOk()->json('weeks');

    expect(collect($weeks)->pluck('week')->all())->toBe(['2026-08-31', '2026-09-07', '2026-09-14', '2026-09-21'])
        ->and($weeks[3])->toMatchArray(['games' => 1, 'answers' => 2, 'firstTryRate' => 0.5, 'minutes' => 1.5])
        ->and($weeks[2])->toMatchArray(['games' => 0, 'answers' => 1, 'firstTryRate' => 1.0])
        ->and($weeks[0]['firstTryRate'])->toBeNull();
});

it('filters by game and validates the range', function () {
    answer(true, 1, null, 'zs');
    answer(true, 1, null, 'kirako');

    $weeks = actingAs($this->user)->getJson("/api/beszed/children/{$this->child->id}/progress/history?weeks=4&game=kirako")->json('weeks');
    expect($weeks[3]['answers'])->toBe(1);

    actingAs($this->user)->getJson("/api/beszed/children/{$this->child->id}/progress/history?weeks=99")->assertStatus(422);
    actingAs(User::factory()->create())->getJson("/api/beszed/children/{$this->child->id}/progress/history")->assertForbidden();
});

it('stores offline results at the time they were played, within 30 days', function () {
    $yesterday = CarbonImmutable::now()->subDay();
    answer(true, 1, $yesterday->toIso8601String());
    expect(BeszedAttempt::sole()->created_at->toDateString())->toBe($yesterday->utc()->toDateString());

    $post = fn (string $at) => actingAs($this->user)->postJson("/api/beszed/children/{$this->child->id}/attempts",
        ['game' => 'zs', 'level' => 1, 'correct' => true, 'tries' => 1, 'played_at' => $at]);
    $post(CarbonImmutable::now()->addDay()->toIso8601String())->assertStatus(422);
    $post(CarbonImmutable::now()->subDays(40)->toIso8601String())->assertStatus(422);

    // A game finished offline yesterday keeps yesterday's streak day.
    actingAs($this->user)->postJson("/api/beszed/children/{$this->child->id}/sessions",
        ['game' => 'zs', 'level' => 1, 'rounds' => 1, 'correct' => 1, 'first_try' => 1, 'played_at' => $yesterday->toIso8601String()])
        ->assertCreated()
        ->assertJsonPath('streak.days', 1)
        ->assertJsonPath('streak.today', false)
        ->assertJsonPath('daily.done', 0);
});
