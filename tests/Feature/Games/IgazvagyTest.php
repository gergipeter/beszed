<?php

use App\Beszed\Content\ContentRules;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Igaz vagy butaság?: two buttons (👍 Igaz / 🤪 Butaság), the answer is the sentence's own truth, a silly sentence
// is set right afterwards, and a session is about half and half with no long run of one kind.

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function igazvagySession(int $level): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=igazvagy&level=$level")
        ->assertOk()
        ->json();
}

it('has at least 90 sentences, balanced true and silly at every level', function () {
    $items = BeszedContentItem::forGame('igazvagy')->get();

    expect($items->count())->toBeGreaterThanOrEqual(90);
    foreach ([1, 2, 3] as $level) {
        $at = $items->where('level', $level);
        $true = $at->filter(fn ($i) => $i->payload['truth'] === 'igaz')->count();
        expect($at->count())->toBeGreaterThanOrEqual(24)
            ->and(abs($true - ($at->count() - $true)))->toBeLessThanOrEqual(2);
    }
    // level 1 sentences come with a picture; every silly one with its right version
    $items->where('level', 1)->each(fn ($i) => expect($i->payload['emoji'] ?? null)->not->toBeEmpty($i->payload['text']));
    $items->filter(fn ($i) => $i->payload['truth'] === 'butasag')->each(fn ($i) => expect($i->payload['fix'] ?? '')->not->toBe(''));
});

it('asks igaz or butaság, judges by the sentence, and sets a silly one right', function (int $level) {
    $items = BeszedContentItem::forGame('igazvagy')->get()->keyBy('id');

    foreach (range(1, 8) as $i) {
        $rounds = igazvagySession($level)['rounds'];
        expect($rounds)->toHaveCount(config('beszed.games.igazvagy.rounds'));
        $answers = array_map(fn ($r) => $r['data']['answer'], $rounds);
        $true = count(array_filter($answers, fn ($a) => $a === 'igaz'));

        // about half and half, never four of one kind in a row
        expect($true)->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(5)
            ->and(implode(',', $answers))->not->toContain('igaz,igaz,igaz,igaz')
            ->and(implode(',', $answers))->not->toContain('butasag,butasag,butasag,butasag');

        foreach ($rounds as $round) {
            $item = $items[$round['content_item_id']];
            $p = $item->payload;
            expect($item->level)->toBeLessThanOrEqual($level)
                ->and(array_column($round['data']['options'], 'id'))->toBe(['igaz', 'butasag'])
                ->and(array_column($round['data']['options'], 'label'))->toBe(['Igaz', 'Butaság'])
                ->and($round['data']['layout'])->toBe('two')
                ->and($round['data']['answer'])->toBe($p['truth'])
                ->and($round['prompt']['parts'])->toContain($p['text'])
                ->and($round['data']['stimulus']['say'])->toBe($p['text']);
            if ($p['truth'] === 'butasag') {
                expect($round['data']['onCorrect'])->toContain('butaság')->toContain($p['fix'])
                    ->and($round['data']['onWrong'])->toContain($p['fix']);
            } else {
                expect($round['data']['onCorrect'])->toContain('igaz');
            }
        }
    }
})->with([1, 2, 3]);

it('needs a right version for a silly sentence, and none for a true one', function () {
    expect(ContentRules::check('igazvagy', ['text' => 'A hal a fán fészkel.', 'truth' => 'butasag']))->toHaveKey('fix')
        ->and(ContentRules::check('igazvagy', ['text' => 'A hal úszik.', 'truth' => 'igaz', 'fix' => 'A hal úszik.']))->toHaveKey('fix')
        ->and(ContentRules::check('igazvagy', ['text' => 'A hal a fán fészkel.', 'truth' => 'butasag', 'fix' => 'A hal a vízben úszik.']))->toBe([]);
});
