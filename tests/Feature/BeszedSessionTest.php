<?php

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

it('builds a playable session for every game', function (string $game) {
    $res = actingAs($this->user)
        ->getJson("/api/beszed/children/{$this->child->id}/session?game=$game")
        ->assertOk();

    expect($res->json('rounds'))->toHaveCount(config("beszed.games.$game.rounds"));

    foreach ($res->json('rounds') as $round) {
        expect($round['engine'])->toBeIn(['choice', 'sequence', 'tapcount', 'trace', 'judged'])
            ->and($round['prompt']['text'])->not->toBeEmpty();

        if ($round['engine'] === 'choice') {
            expect(collect($round['data']['options'])->pluck('id'))->toContain($round['data']['answer']);
        }
    }
})->with(['zs', 'szotag', 'kezdo', 'hol', 'szamol', 'okoska', 'papagaj', 'mondd', 'melyik', 'ceruza']);

it('levels papagáj up after two clean wins and down after a skip', function () {
    $post = fn (bool $ok, int $tries) => actingAs($this->user)
        ->postJson("/api/beszed/children/{$this->child->id}/attempts",
            ['game' => 'papagaj', 'level' => 3, 'correct' => $ok, 'tries' => $tries])
        ->assertCreated()->json('level');

    expect($post(true, 1))->toBe(3)
        ->and($post(true, 1))->toBe(4)
        ->and($post(false, 3))->toBe(3);
});

it('keeps other families out', function () {
    $stranger = User::factory()->create();

    actingAs($stranger)
        ->getJson("/api/beszed/children/{$this->child->id}/session?game=zs")
        ->assertForbidden();
});
