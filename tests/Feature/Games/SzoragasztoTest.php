<?php

use App\Beszed\Content\ContentRules;
use App\Beszed\Rounds\SzoragasztoRounds;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Szóragasztó (choice): compound words. Every compound is exactly its two parts glued together;
// level 1 glues, level 2 splits, level 3 takes a part away — and no wrong answer is also right.

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function ragasztoRows(): array
{
    return json_decode(file_get_contents(database_path('seeders/data/beszed/szoragaszto.json')), true, flags: JSON_THROW_ON_ERROR);
}

/** A session, with each picture back to its emoji (a session shows the ARASAAC pictogram where there is one: "arasaac:2462~🍎"). */
function ragasztoSession(int $level): array
{
    $session = actingAs(test()->user)->getJson('/api/beszed/children/'.test()->child->id."/session?game=szoragaszto&level=$level")->assertOk()->json();
    array_walk_recursive($session, function (&$value) {
        $value = is_string($value) ? preg_replace('/^arasaac:\d+~/u', '', $value) : $value;
    });

    return $session;
}

it('has at least 40 clean compounds: the two parts glued, three different pictures', function () {
    $rows = collect(ragasztoRows());

    expect($rows->count())->toBeGreaterThanOrEqual(40);
    foreach ($rows as $row) {
        $p = $row['payload'];
        expect(ContentRules::check('szoragaszto', $p))->toBe([], $p['word'])
            ->and($p['a'].$p['b'])->toBe($p['word'])
            ->and(array_unique(SzoragasztoRounds::pictures($p)))->toHaveCount(3);
    }
    // a compound's picture is never a part's picture of another compound shown as a part of this one
    expect($rows->pluck('payload.word')->duplicates()->all())->toBe([]);
    // the forms a rule would get wrong
    $byWord = $rows->keyBy('payload.word');
    expect($byWord['hóember']['payload']['accA'])->toBe('havat')
        ->and($byWord['lóverseny']['payload']['accA'])->toBe('lovat')
        ->and($byWord['szélcsengő']['payload']['accA'])->toBe('szelet')
        ->and($byWord['falevél']['payload']['accB'])->toBe('levelet')
        ->and($byWord['villanykörte']['payload']['from'])->toBe('villanykörtéből');
});

it('rejects parts that do not glue into the word', function () {
    $p = ['word' => 'esernyő', 'emoji' => '☂️', 'a' => 'eső', 'emojiA' => '🌧️', 'b' => 'ernyő', 'emojiB' => '⛱️', 'from' => 'esernyőből', 'accA' => 'esőt', 'accB' => 'ernyőt'];
    expect(ContentRules::check('szoragaszto', $p))->toHaveKey('b');
});

it('level 1: two words glued — the glued word is one of three pictures, no wrong one shows a part', function () {
    foreach (range(1, 5) as $i) {
        foreach (ragasztoSession(10)['rounds'] as $round) {
            $d = $round['data'];
            $p = BeszedContentItem::find($round['content_item_id'])->payload;
            $options = collect($d['options']);

            expect($d['sequence'])->toBe([$p['emojiA'], $p['emojiB']])
                ->and(mb_strtolower($round['prompt']['text']))->toContain($p['a'])->toContain($p['b'])
                ->and($options)->toHaveCount(3)
                ->and($options->firstWhere('id', $d['answer'])['label'])->toBe($p['word'])
                ->and($options->pluck('emoji')->unique())->toHaveCount(3);
            foreach ($options->where('id', '!=', $d['answer']) as $o) {
                expect($o['label'])->not->toBe($p['word'])
                    ->and(SzoragasztoRounds::pictures($p))->not->toContain($o['emoji']);
            }
        }
    }
});

it('level 2: the compound\'s picture — only the right pair of parts glues into it', function () {
    foreach (range(1, 5) as $i) {
        foreach (ragasztoSession(50)['rounds'] as $round) {
            $d = $round['data'];
            $p = BeszedContentItem::find($round['content_item_id'])->payload;
            $options = collect($d['options']);

            expect($d['stimulus']['emoji'])->toBe($p['emoji'])
                ->and($options)->toHaveCount(3)
                ->and($options->firstWhere('id', $d['answer'])['emoji'])->toBe($p['emojiA'].$p['emojiB'])
                ->and($options->pluck('label')->unique())->toHaveCount(3);
            foreach ($options->where('id', '!=', $d['answer']) as $o) {
                [$x, $y] = explode(' + ', $o['label']);
                expect($x.$y)->not->toBe($p['word'])
                    ->and(preg_match_all('/\X/u', $o['emoji'], $m) ? $m[0] : [])->not->toContain($p['emoji'])
                    ->and($o['emoji'])->not->toBe($p['emojiA'].$p['emojiB']);
            }
        }
    }
});

it('level 3: take a part away — what is left is the other part, and the question uses the right forms', function () {
    $taken = collect();
    foreach (range(1, 5) as $i) {
        foreach (ragasztoSession(100)['rounds'] as $round) {
            $d = $round['data'];
            $p = BeszedContentItem::find($round['content_item_id'])->payload;
            $options = collect($d['options'])->keyBy('id');
            $left = $options['left']['label'];
            $gone = $options['gone']['label'];

            expect([$left, $gone])->toBeIn([[$p['a'], $p['b']], [$p['b'], $p['a']]])
                ->and($d['answer'])->toBe('left')
                ->and($round['prompt']['text'])->toContain($p['from'])
                ->and($round['prompt']['text'])->toContain($gone === $p['a'] ? $p['accA'] : $p['accB']);
            if ($options->has('other')) {
                expect([$p['a'], $p['b'], $p['word']])->not->toContain($options['other']['label'])
                    ->and(SzoragasztoRounds::pictures($p))->not->toContain($options['other']['emoji']);
            }
            $taken->push($gone === $p['a'] ? 'a' : 'b');
        }
    }
    // sometimes the first part goes, sometimes the second
    expect($taken->unique()->count())->toBe(2);
});
