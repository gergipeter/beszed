<?php

use App\Beszed\Rounds\KirakoRounds;
use App\Beszed\Rounds\ValogatoRounds;
use App\Models\BeszedContentItem;
use App\Models\BeszedSkillLevel;
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

function gameSession(string $game): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=$game")
        ->assertOk()
        ->json();
}

it('builds a playable session for every game', function (string $game) {
    $session = gameSession($game);

    expect($session['rounds'])->toHaveCount(config("beszed.games.$game.rounds"));

    foreach ($session['rounds'] as $round) {
        expect($round['engine'])->toBeIn(['choice', 'sequence', 'tapcount', 'trace', 'judged', 'puzzle', 'memory', 'sort'])
            ->and($round['prompt']['text'])->not->toBeEmpty();

        if ($round['engine'] === 'choice') {
            expect(collect($round['data']['options'])->pluck('id'))->toContain($round['data']['answer']);
        }
    }
})->with(array_keys((require __DIR__.'/../../config/beszed.php')['games'])); // datasets load before the app boots

it('seeds content for every game', function () {
    foreach (array_keys(config('beszed.games')) as $game) {
        expect(BeszedContentItem::forGame($game)->count())->toBeGreaterThan(0, "no content for $game");
    }
});

it('kirakó: shuffled, never solved, grid follows the level', function (int $level) {
    BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => 'kirako', 'level' => $level]);
    [$cols, $rows] = KirakoRounds::GRIDS[$level];

    foreach (gameSession('kirako')['rounds'] as $round) {
        $pieces = $round['data']['pieces'];
        expect($round['data'])->toMatchArray(['cols' => $cols, 'rows' => $rows])
            ->and(collect($pieces)->sort()->values()->all())->toBe(range(0, $cols * $rows - 1))
            ->and($pieces)->not->toBe(range(0, $cols * $rows - 1));
    }
})->with([1, 2, 3]);

it('párkereső: every word exactly twice, pairs = level', function () {
    BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => 'parkereso', 'level' => 5]);

    foreach (gameSession('parkereso')['rounds'] as $round) {
        $cards = collect($round['data']['cards']);
        expect($cards)->toHaveCount(10)
            ->and($cards->pluck('id')->unique())->toHaveCount(10)
            ->and($cards->countBy('pair')->unique()->values()->all())->toBe([2]);
    }
});

it('árnyékkereső: stimulus is a silhouette of the answer', function () {
    foreach (gameSession('arnyek')['rounds'] as $round) {
        $answer = collect($round['data']['options'])->firstWhere('id', $round['data']['answer']);
        expect($round['data']['stimulus'])->toMatchArray(['emoji' => $answer['emoji'], 'silhouette' => true]);
    }
});

it('rímelő: the answer rhymes with the word, the distractors do not', function () {
    $rhymeOf = BeszedContentItem::forGame('rimelo')->get()->mapWithKeys(fn ($i) => [$i->payload['word'] => $i->payload['rhyme']]);

    foreach (gameSession('rimelo')['rounds'] as $round) {
        $word = $round['data']['stimulus']['label'];
        foreach ($round['data']['options'] as $o) {
            $rhymes = $rhymeOf[$o['label']] === $rhymeOf[$word];
            expect($rhymes)->toBe($o['id'] === $round['data']['answer'], "$word / {$o['label']}");
            expect($o['label'])->not->toBe($word);
        }
    }
});

it('válogató: two baskets, each picture belongs to one of them, count follows the level', function (int $level) {
    BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => 'valogato', 'level' => $level]);

    foreach (gameSession('valogato')['rounds'] as $round) {
        $bins = collect($round['data']['bins'])->pluck('id');
        $items = collect($round['data']['items']);
        expect($bins)->toHaveCount(2)
            ->and($items)->toHaveCount(ValogatoRounds::PICTURES[$level])
            ->and($items->countBy('bin')->all())->toEqual($bins->mapWithKeys(fn ($b) => [$b => ValogatoRounds::PICTURES[$level] / 2])->all())
            ->and($items->every(fn ($i) => str_contains($i['wrong'], ' nem ')))->toBeTrue();
    }
})->with([1, 2, 3]);

it('levels papagáj up after two clean wins and down after a skip', function () {
    $post = fn (bool $ok, int $tries) => actingAs($this->user)
        ->postJson("/api/beszed/children/{$this->child->id}/attempts",
            ['game' => 'papagaj', 'level' => 3, 'correct' => $ok, 'tries' => $tries])
        ->assertCreated()->json('level');

    expect($post(true, 1))->toBe(3)
        ->and($post(true, 1))->toBe(4)
        ->and($post(false, 3))->toBe(3);
});

it('reports server TTS off for the null driver, whatever form the env value takes', function (?string $driver, bool $on) {
    config(['tts.driver' => $driver, 'tts.azure.key' => 'k']);
    app()->forgetInstance(\App\Beszed\Tts\TtsClient::class);

    actingAs($this->user)->getJson('/api/beszed/meta')->assertOk()->assertJsonPath('serverTts', $on);
})->with([[null, false], ['null', false], ['azure', true]]);

it('keeps other families out', function () {
    $stranger = User::factory()->create();

    actingAs($stranger)
        ->getJson("/api/beszed/children/{$this->child->id}/session?game=zs")
        ->assertForbidden();
});
