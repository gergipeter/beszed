<?php

use App\Beszed\CsillamGuess;
use App\Beszed\Rounds\KirakoRounds;
use App\Beszed\Rounds\KulonbsegRounds;
use App\Beszed\Rounds\NagysagRounds;
use App\Beszed\Rounds\TortenetRounds;
use App\Beszed\Rounds\UtasitasRounds;
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
        expect($round['engine'])->toBeIn(['choice', 'sequence', 'tapcount', 'trace', 'judged', 'puzzle', 'memory', 'sort', 'difference', 'vanish', 'order', 'simon', 'directions'])
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

it('rímpárok: cards pair by rhyme, never by identical word, pairs = level', function (int $level) {
    BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => 'rimparok', 'level' => $level]);
    $rhymeOf = BeszedContentItem::forGame('rimparok')->get()->mapWithKeys(fn ($i) => [$i->payload['word'] => $i->payload['rhyme']]);

    foreach (gameSession('rimparok')['rounds'] as $round) {
        $cards = collect($round['data']['cards']);
        $byPair = $cards->groupBy('pair');

        expect($cards)->toHaveCount($level * 2)
            ->and($cards->pluck('id')->unique())->toHaveCount($level * 2)
            ->and($byPair->map->count()->unique()->values()->all())->toBe([2]);

        foreach ($byPair as $pair) {
            [$a, $b] = $pair->pluck('label')->all();
            expect($a)->not->toBe($b)
                ->and($rhymeOf[$a])->toBe($rhymeOf[$b]);
        }
    }
})->with([2, 3, 4]);

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

it('hallgasd: the spoken word names the answer, options never repeat', function () {
    $wordOf = BeszedContentItem::forGame('hallgasd')->get()->keyBy('id')->map(fn ($i) => $i->payload['word']);

    foreach (gameSession('hallgasd')['rounds'] as $round) {
        $options = collect($round['data']['options']);
        $answer = $options->firstWhere('id', $round['data']['answer']);

        expect($round['prompt']['text'])->toBe($wordOf[(int) $round['data']['answer']])
            ->and($options)->toHaveCount(3)
            ->and($options->pluck('id')->unique())->toHaveCount(3)
            ->and($answer['label'])->toBe($round['prompt']['text']);
    }
});

it('ikerhangok: the two options are always the item\'s own minimal pair', function () {
    $pairOf = BeszedContentItem::forGame('ikerhangok')->get()->keyBy('id')
        ->map(fn ($i) => [$i->payload['wordA'], $i->payload['wordB']]);

    foreach (gameSession('ikerhangok')['rounds'] as $round) {
        $options = collect($round['data']['options']);
        $answer = $options->firstWhere('id', $round['data']['answer']);
        $pair = $pairOf[$round['content_item_id']];

        expect($options)->toHaveCount(2)
            ->and($options->pluck('label')->sort()->values()->all())->toBe(collect($pair)->sort()->values()->all())
            ->and($round['prompt']['text'])->toBeIn($pair)
            ->and($answer['label'])->toBe($round['prompt']['text']);
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

it('mi a különbség: the panels differ in exactly one cell, grid follows the level', function (int $level) {
    BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => 'kulonbseg', 'level' => $level]);
    $groupOf = BeszedContentItem::forGame('kulonbseg')->get()->mapWithKeys(fn ($i) => [$i->payload['emoji'] => $i->payload['group']]);
    $bare = fn (string $picture) => str_contains($picture, '~') ? explode('~', $picture)[1] : $picture; // "arasaac:1~🐶" → "🐶"
    [$cols, $rows] = KulonbsegRounds::GRIDS[$level];

    foreach (gameSession('kulonbseg')['rounds'] as $round) {
        ['left' => $left, 'right' => $right, 'diff' => $diff] = $round['data'];
        $differs = collect($left)->keys()->filter(fn ($k) => $left[$k] !== $right[$k])->values()->all();

        expect($round['data'])->toMatchArray(['cols' => $cols, 'rows' => $rows])
            ->and($left)->toHaveCount($cols * $rows)
            ->and($differs)->toBe([$diff])
            ->and(collect($left)->unique())->toHaveCount($cols * $rows)
            ->and(collect($right)->unique())->toHaveCount($cols * $rows);
        // top level: a look-alike from the same group; below it, something clearly different
        $same = $groupOf[$bare($left[$diff])] === $groupOf[$bare($right[$diff])];
        expect($same)->toBe($level === 3);
    }
})->with([1, 2, 3]);

it('kicsitől a nagyig: n sizes of one picture, shuffled, ordered by size', function (int $level) {
    BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => 'nagysag', 'level' => $level]);

    foreach (gameSession('nagysag')['rounds'] as $r => $round) {
        $items = collect($round['data']['items'])->keyBy('id');
        $scales = collect($round['data']['order'])->map(fn ($id) => $items[$id]['scale'])->all();
        $down = $level === 3 && $r % 2 === 1;

        expect($items)->toHaveCount(NagysagRounds::SIZES[$level])
            ->and($items->pluck('emoji')->unique())->toHaveCount(1)
            ->and($scales)->toBe(collect($scales)->sort()->when($down, fn ($s) => $s->reverse())->values()->all())
            ->and($items->keys()->all())->not->toBe($round['data']['order']);
    }
})->with([1, 2, 3]);

it('mi történt előbb: the story\'s steps in order, first and last kept, shuffled', function (int $level) {
    BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => 'tortenet', 'level' => $level]);
    $stories = BeszedContentItem::forGame('tortenet')->get()->keyBy('id');

    foreach (gameSession('tortenet')['rounds'] as $round) {
        $steps = collect($stories[$round['content_item_id']]->payload['steps'])->pluck(1)->all();
        $items = collect($round['data']['items'])->keyBy('id');
        $told = collect($round['data']['order'])->map(fn ($id) => $items[$id]['label'])->all();
        $positions = collect($told)->map(fn ($label) => array_search($label, $steps, true))->all();

        expect($told)->toHaveCount(TortenetRounds::STEPS[$level])
            ->and($positions)->toBe(collect($positions)->sort()->values()->all())
            ->and($told[0])->toBe($steps[0])
            ->and(end($told))->toBe(end($steps))
            ->and($items->keys()->all())->not->toBe($round['data']['order']);
    }
})->with([1, 2]);

it('mi tűnt el: n pictures, the missing one among them, the other choices never shown', function (int $level) {
    BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => 'mitunt', 'level' => $level]);

    foreach (gameSession('mitunt')['rounds'] as $round) {
        $shown = collect($round['data']['items'])->pluck('id');
        $options = collect($round['data']['options'])->pluck('id');

        expect($shown)->toHaveCount($level)
            ->and($shown->unique())->toHaveCount($level)
            ->and($shown)->toContain($round['data']['missing'])
            ->and($options)->toContain($round['data']['missing'])
            ->and($options->intersect($shown)->values()->all())->toBe([$round['data']['missing']])
            ->and((string) $round['content_item_id'])->toBe($round['data']['missing']);
    }
})->with([3, 6]);

it('hogy érzi magát: the right face belongs to the feeling, situations never offer a look-alike feeling', function () {
    $feelings = BeszedContentItem::forGame('erzelmek')->get()->keyBy('id');
    $faces = fn ($f) => [$f->payload['emoji'], ...($f->payload['faces'] ?? [])];

    foreach (gameSession('erzelmek')['rounds'] as $round) {
        $feel = $feelings[(int) $round['data']['answer']];
        $options = collect($round['data']['options']);
        $answer = $options->firstWhere('id', $round['data']['answer']);

        expect($options)->toHaveCount(3)
            ->and($faces($feel))->toContain($answer['emoji'])
            ->and($round['data']['onWrong'])->toHaveKeys($options->pluck('id')->all());
        if (isset($round['data']['stimulus'])) {
            $offered = $options->pluck('id')->map(fn ($id) => $feelings[(int) $id]->payload['name']);
            expect($offered->intersect($feel->payload['close'] ?? [])->all())->toBe([]);
        }
    }
    // a face means one feeling only
    expect($feelings->flatMap($faces)->duplicates()->all())->toBe([]);
});

it('állatkórus: the same four animals all session, n notes, never one animal three times in a row', function (int $level) {
    BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => 'korus', 'level' => $level]);
    $rounds = gameSession('korus')['rounds'];
    $pads = collect($rounds[0]['data']['pads'])->pluck('id')->all();

    foreach ($rounds as $round) {
        $order = $round['data']['order'];
        expect(collect($round['data']['pads'])->pluck('id')->all())->toBe($pads)
            ->and($pads)->toHaveCount(4)
            ->and($order)->toHaveCount($level)
            ->and(array_diff($order, $pads))->toBe([]);
        for ($k = 2; $k < count($order); $k++) {
            expect($order[$k] === $order[$k - 1] && $order[$k] === $order[$k - 2])->toBeFalse();
        }
    }
})->with([2, 7]);

it('csináld, amit mondok: every direction has exactly one right way to follow it', function (int $level) {
    BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => 'utasitas', 'level' => $level]);
    $items = BeszedContentItem::forGame('utasitas')->get()->keyBy('id');
    [$size, $kinds] = UtasitasRounds::LEVELS[$level];

    foreach (gameSession('utasitas')['rounds'] as $r => $round) {
        $grid = collect($round['data']['grid'])->pluck('id');
        $steps = $round['data']['steps'];
        $tapped = collect($steps)->flatten();
        $text = $round['prompt']['text'];
        $kind = $kinds[$r % count($kinds)];

        expect($grid)->toHaveCount($size)
            ->and($grid->unique())->toHaveCount($size)
            ->and($tapped->diff($grid)->all())->toBe([])
            ->and($tapped->unique())->toHaveCount($tapped->count());

        $of = fn ($id) => $items[(int) $id]->payload;
        if (in_array($kind, ['one', 'two', 'three', 'before'], true)) {
            // one picture per step, each named in the direction ("before" names them the other way round)
            $said = collect($steps)->map(fn ($s) => $of($s[0])['onto']);
            expect(collect($steps)->every(fn ($s) => count($s) === 1))->toBeTrue()
                ->and($said->every(fn ($w) => str_contains($text, $w)))->toBeTrue();
            $at = $said->map(fn ($w) => mb_strpos($text, $w))->all();
            expect($at)->toBe(collect($at)->sort()->when($kind === 'before', fn ($p) => $p->reverse())->values()->all());
        } else {
            // the step is exactly the grid's pictures of the group (or, for "not", all the others)
            $group = $of($round['content_item_id'])['group'];
            $members = $grid->filter(fn ($id) => $of($id)['group'] === $group)->values();
            $expected = $kind === 'not' ? $grid->diff($members) : $members;
            expect(count($steps))->toBe(1)
                ->and(collect($steps[0])->sort()->values()->all())->toBe($expected->sort()->values()->all());
            if ($kind === 'group') {
                expect($members)->toHaveCount(1);
            }
        }
    }
})->with([1, 2, 3]);

it('lets Csillám have a go in a few choice rounds: never the first, never Brumi and Nyuszi, at most max', function () {
    $choice = fn (array $extra = []) => ['engine' => 'choice', 'data' => $extra + [
        'options' => [['id' => 'a'], ['id' => 'b'], ['id' => 'c']], 'answer' => 'b',
    ]];
    $rounds = [$choice(), $choice(), ['engine' => 'sort', 'data' => []], $choice(['variant' => 'speakers']), $choice(), $choice()];

    $guessed = collect(CsillamGuess::apply($rounds, ['chance' => 1.0, 'max' => 2]))
        ->filter(fn ($r) => isset($r['data']['guess']));

    expect($guessed->keys()->all())->toBe([1, 4]) // round 0, the sort and the speakers round are left alone
        ->and($guessed->every(fn ($r) => in_array($r['data']['guess']['id'], ['a', 'b', 'c'], true)))->toBeTrue()
        ->and($guessed->first()['data']['guess'])->toHaveKeys(['ask', 'confirmed', 'caught', 'agreedWrong', 'deniedRight']);
    expect(collect(CsillamGuess::apply($rounds, ['chance' => 0.0]))->filter(fn ($r) => isset($r['data']['guess'])))->toBeEmpty();
});

it('makes Csillám wrong about half the time', function () {
    $round = ['engine' => 'choice', 'data' => ['options' => [['id' => 'a'], ['id' => 'b']], 'answer' => 'b']];
    $wrong = collect(range(1, 400))
        ->map(fn () => CsillamGuess::apply([$round, $round], ['chance' => 1.0])[1]['data']['guess']['id'])
        ->filter(fn ($id) => $id !== 'b')->count();

    expect($wrong)->toBeGreaterThan(140)->toBeLessThan(260);
});

it('puts every game in the simple or the advanced group of the hub', function () {
    $games = collect(actingAs($this->user)->getJson('/api/beszed/meta')->assertOk()->json('games'));

    expect($games->pluck('tier')->unique()->sort()->values()->all())->toBe(['advanced', 'simple'])
        ->and($games->every(fn ($g) => in_array($g['tier'], ['simple', 'advanced'], true)))->toBeTrue()
        // every game is played in one of the scenes GameStage.vue draws
        ->and($games->pluck('stage')->diff(['meadow', 'hive', 'theatre', 'magic', 'workshop', 'pond', 'forest', 'market', 'storybook'])->all())->toBe([]);
});

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
