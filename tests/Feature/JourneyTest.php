<?php

use App\Models\BeszedAttempt;
use App\Models\BeszedSkillLevel;
use App\Models\Child;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
    $this->cfg = config('beszed.games.szotag.adaptive'); // a 1–3 game in the sound-hearing area
});

function journey(?User $user = null): array
{
    return actingAs($user ?? test()->user)->getJson('/api/beszed/children/'.test()->child->id.'/journey')->assertOk()->json();
}

function journeyStep(array $journey, string $game): array
{
    return collect($journey['areas'])->flatMap(fn ($a) => $a['steps'])->firstWhere('id', $game);
}

function playedAnswers(string $game, int $answers, int $firstTry): void
{
    for ($i = 0; $i < $answers; $i++) {
        BeszedAttempt::create([
            'child_id' => test()->child->id, 'game' => $game, 'level' => 1,
            'correct' => true, 'tries' => $i < $firstTry ? 1 : 2,
        ]);
    }
}

it('lists every skill area with its games as steps, all new at the start', function () {
    $j = journey();

    expect($j['areas'])->toHaveCount(count(config('beszed_skills.areas')))
        ->and(collect($j['areas'])->flatMap(fn ($a) => $a['steps'])->pluck('state')->unique()->all())->toBe(['new'])
        ->and($j['recommended'])->not->toBeNull();
});

it('marks a played game as learning, and points next at the first step not yet mastered', function () {
    playedAnswers('szotag', 6, 3);

    $j = journey();
    $area = collect($j['areas'])->first(fn ($a) => in_array('szotag', array_column($a['steps'], 'id')));

    expect(journeyStep($j, 'szotag')['state'])->toBe('learning')
        ->and($area['next'])->toBe($area['steps'][0]['id']);
});

it('recommends the started game that needs practice most', function () {
    playedAnswers('szotag', 8, 7);
    playedAnswers('zs', 8, 2);

    expect(journey()['recommended']['game'])->toBe('zs');
});

it('calls a game mastered only at its top level with clean first-try answers', function () {
    playedAnswers('szotag', 8, 7);
    expect(journeyStep(journey(), 'szotag')['state'])->toBe('learning'); // still on the start level

    BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => 'szotag', 'level' => $this->cfg['max'], 'streak' => 0]);
    expect(journeyStep(journey(), 'szotag')['state'])->toBe('mastered');
});

it('marks a step held back when a free account has reached the free cap and the game goes further', function () {
    $free = User::factory()->free()->create();
    $this->child = Child::create(['user_id' => $free->id, 'name' => 'Bence']);
    $cap = config('beszed_plans.free_max_level');
    $long = collect(config('beszed.games'))->filter(fn ($g) => ($g['adaptive']['max'] ?? 0) > $cap)->keys()->first();
    playedAnswers($long, 6, 6);
    BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => $long, 'level' => $cap + 1, 'streak' => 0]);

    $step = journeyStep(journey($free), $long);

    expect($step['held'])->toBeTrue()->and($step['level'])->toBe($cap);
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Premium']);
    BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => $long, 'level' => $cap + 1, 'streak' => 0]);
    playedAnswers($long, 6, 6);
    expect(journeyStep(journey(), $long)['held'])->toBeFalse();
});

it("is private to the child's parent", function () {
    $other = User::factory()->create();

    actingAs($other)->getJson('/api/beszed/children/'.$this->child->id.'/journey')->assertForbidden();
});

it('finds the level quickly in a game\'s first answers: every clean win moves up', function () {
    $first = fn () => actingAs($this->user)->postJson('/api/beszed/children/'.$this->child->id.'/attempts',
        ['game' => 'szotag', 'level' => 1, 'correct' => true, 'tries' => 1])->assertCreated()->json('level');

    expect($first())->toBe(2)->and($first())->toBe(3);
});

it('is back to the normal pace after the placement answers', function () {
    playedAnswers('szotag', config('beszed_skills.placement_answers'), 0);
    BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => 'szotag', 'level' => 1, 'streak' => 0]);

    $level = actingAs($this->user)->postJson('/api/beszed/children/'.$this->child->id.'/attempts',
        ['game' => 'szotag', 'level' => 1, 'correct' => true, 'tries' => 1])->assertCreated()->json('level');

    expect($level)->toBe(1); // one win in a row is not enough any more
});
