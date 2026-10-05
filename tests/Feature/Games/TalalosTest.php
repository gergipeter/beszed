<?php

use App\Beszed\Content\ContentRules;
use App\Beszed\Content\Hungarian;
use App\Beszed\Rounds\TalalosRounds;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Találós kérdések: the clues are said one by one, the right picture is among the options, the wrong ones come
// from the riddle's own group and never from its near fits, and level 3 offers four pictures.

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function talalosSession(int $level): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=talalos&level=$level")
        ->assertOk()
        ->json();
}

function talalosItems(): array
{
    return BeszedContentItem::forGame('talalos')->get()->keyBy('id')->map(fn ($i) => $i->payload + ['level' => $i->level])->all();
}

it('has deep content: at least 70 riddles, every group big enough for four pictures', function () {
    $items = collect(talalosItems());

    expect($items)->toHaveCount(count(json_decode(file_get_contents(database_path('seeders/data/beszed/talalos.json')), true)))
        ->and($items->count())->toBeGreaterThanOrEqual(70);
    foreach ($items->groupBy('group') as $group => $riddles) {
        expect($riddles->count())->toBeGreaterThanOrEqual(5, "group $group");
    }
    foreach ([1, 2, 3] as $level) {
        expect($items->where('level', $level)->count())->toBeGreaterThanOrEqual(15);
    }
    // level 1 gives three clues
    $items->where('level', 1)->each(fn ($r) => expect(count($r['clues']))->toBeGreaterThanOrEqual(3, $r['answer']));
});

it('keeps near fits real answers of the same group', function () {
    $items = collect(talalosItems());
    $byAnswer = $items->keyBy(fn ($r) => Hungarian::fold($r['answer']));

    foreach ($items as $r) {
        foreach ($r['close'] ?? [] as $close) {
            expect($byAnswer->has(Hungarian::fold($close)))->toBeTrue("{$r['answer']}: $close")
                ->and($byAnswer[Hungarian::fold($close)]['group'])->toBe($r['group']);
        }
    }
});

it('says the clues one by one and offers the answer among pictures of its own group', function (int $level, int $tier) {
    $items = talalosItems();

    foreach (range(1, 6) as $i) {
        $session = talalosSession($level);
        expect($session['rounds'])->toHaveCount(config('beszed.games.talalos.rounds'));

        foreach ($session['rounds'] as $r => $round) {
            $data = $round['data'];
            $riddle = $items[$round['content_item_id']];
            $ids = array_column($data['options'], 'id');

            expect($round['engine'])->toBe('choice')
                ->and($data['options'])->toHaveCount($tier >= 3 ? 4 : 3)
                ->and($data['layout'])->toBe($tier >= 3 ? 'four' : 'three')
                ->and($ids)->toContain($data['answer'])
                ->and($data['answer'])->toBe((string) $round['content_item_id'])
                ->and(array_unique(array_column($data['options'], 'emoji')))->toHaveCount(count($ids))
                // the clues, then "Kire/Mire gondolok?"
                ->and($round['prompt']['parts'])->toContain(...$riddle['clues'])
                ->and(end($round['prompt']['parts']))->toBe(TalalosRounds::question($riddle))
                ->and(implode(' ', $round['prompt']['parts']))->not->toContain('…');
            if ($r === 0) {
                expect($round['prompt']['parts'][0])->toContain('Találós');
            }

            foreach ($data['options'] as $option) {
                if ($option['id'] === $data['answer']) {
                    continue;
                }
                $other = $items[(int) $option['id']];
                // same group, never a near fit either way round, and a sentence saying what it is
                expect($other['group'])->toBe($riddle['group'])
                    ->and(TalalosRounds::close($riddle, $other))->toBeFalse("{$riddle['answer']} / {$other['answer']}")
                    ->and($data['onWrong'][$option['id']])->toContain($other['answer']);
            }
            expect($data['onCorrect'])->toContain($riddle['answer']);
        }
    }
})->with([[1, 1], [50, 2], [100, 3]]); // level → tier(level, 3)

it('plays the easier riddles at the lower levels', function () {
    $items = talalosItems();

    foreach (range(1, 5) as $i) {
        foreach (talalosSession(1)['rounds'] as $round) {
            expect($items[$round['content_item_id']]['level'])->toBe(1);
        }
    }
});

it('rejects a clue that says the answer', function () {
    expect(ContentRules::check('talalos', ['answer' => 'nyuszi', 'emoji' => '🐰', 'group' => 'allat', 'clues' => ['A nyuszi ugrál.', 'Répát eszik.']]))
        ->toHaveKey('clues')
        ->and(ContentRules::check('talalos', ['answer' => 'nyuszi', 'emoji' => '🐰', 'group' => 'allat', 'clues' => ['Hosszú a füle.', 'Répát eszik.']]))
        ->toBe([]);
});
