<?php

use App\Models\BeszedAttempt;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function introSession(string $game): array
{
    return actingAs(test()->user)->getJson('/api/beszed/children/'.test()->child->id."/session?game=$game")->assertOk()->json();
}

it('introduces a game only the first time the child plays it', function () {
    expect(introSession('zs')['first_time'])->toBeTrue();

    BeszedAttempt::create(['child_id' => $this->child->id, 'game' => 'zs', 'level' => 1, 'correct' => true, 'tries' => 1]);

    expect(introSession('zs')['first_time'])->toBeFalse()
        ->and(introSession('kezdo')['first_time'])->toBeTrue();
});

it('explains the drum and the parrot once, then keeps the prompts short', function (string $game, string $longTail) {
    $prompts = collect(introSession($game)['rounds'])->pluck('prompt.text');

    expect($prompts->first())->toContain($longTail)
        ->and($prompts->slice(1)->filter(fn ($p) => str_contains($p, $longTail)))->toBeEmpty();
})->with([
    'szótag' => ['szotag', 'Minden szótagra üss egyet'],
    'papagáj' => ['papagaj', 'Figyelj, és mondd utánam!'],
]);
