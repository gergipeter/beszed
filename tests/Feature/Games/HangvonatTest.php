<?php

use App\Beszed\Content\ContentRules;
use App\Beszed\Rounds\HangvonatRounds;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Hangvonat (choice): where is the sound — start, middle or end. Every claim about a word must be true
// (digraph-aware: sz is not s, zs is not z), and the round's answer is where the sound asked really is.

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function hangvonatRows(): array
{
    return json_decode(file_get_contents(database_path('seeders/data/beszed/hangvonat.json')), true, flags: JSON_THROW_ON_ERROR);
}

function hangvonatSession(int $level): array
{
    return actingAs(test()->user)->getJson('/api/beszed/children/'.test()->child->id."/session?game=hangvonat&level=$level")->assertOk()->json();
}

it('knows where a sound is, telling digraphs apart', function () {
    expect(HangvonatRounds::position('busz', 'sz'))->toBe('end')
        ->and(HangvonatRounds::position('busz', 's'))->toBeNull()
        ->and(HangvonatRounds::position('szusi', 's'))->toBe('middle')
        ->and(HangvonatRounds::position('szusi', 'sz'))->toBe('start')
        ->and(HangvonatRounds::position('sószóró', 's'))->toBe('start')
        ->and(HangvonatRounds::position('zsiráf', 'z'))->toBeNull()
        ->and(HangvonatRounds::position('rózsa', 'zs'))->toBe('middle')
        ->and(HangvonatRounds::position('kacsa', 's'))->toBeNull()
        ->and(HangvonatRounds::position('olló', 'l'))->toBeNull() // twice
        ->and(HangvonatRounds::position('asszony', 'sz'))->toBe('middle'); // long sz is one sz
});

it('rejects a word whose sound is not where it says or does not sound as written', function (array $payload, string $field) {
    expect(ContentRules::check('hangvonat', $payload + ['emoji' => '🐶']))->toHaveKey($field);
})->with([
    'sz is not s' => [['word' => 'szék', 'sound' => 's', 'pos' => 'start'], 'word'],
    'zs is not z' => [['word' => 'zsiráf', 'sound' => 'z', 'pos' => 'start'], 'word'],
    'cs is not s' => [['word' => 'kacsa', 'sound' => 's', 'pos' => 'middle'], 'word'],
    'twice' => [['word' => 'sas', 'sound' => 's', 'pos' => 'start'], 'word'],
    'wrong place' => [['word' => 'busz', 'sound' => 'sz', 'pos' => 'start'], 'pos'],
    'z said as sz' => [['word' => 'vízpart', 'sound' => 'z', 'pos' => 'middle'], 'word'],
    'n said as m' => [['word' => 'színpad', 'sound' => 'n', 'pos' => 'middle'], 'word'],
    'ly says j' => [['word' => 'hajólyuk', 'sound' => 'j', 'pos' => 'middle'], 'word'],
]);

it('has deep, valid content for every sound', function () {
    $rows = collect(hangvonatRows());

    expect($rows->count())->toBeGreaterThanOrEqual(100);
    foreach ($rows as $row) {
        $p = $row['payload'];
        expect(ContentRules::check('hangvonat', $p))->toBe([], json_encode($p, JSON_UNESCAPED_UNICODE))
            ->and(HangvonatRounds::position($p['word'], $p['sound']))->toBe($p['pos'], $p['word']);
        // a look-alike in the word (szusi for s) only at level 3
        $look = array_intersect(HangvonatRounds::sounds($p['word']), HangvonatRounds::LOOKALIKES[$p['sound']] ?? []);
        if ($look) {
            expect($row['level'])->toBe(3, $p['word']);
        }
    }
    foreach (HangvonatRounds::SOUNDS as $sound) {
        $mine = $rows->where('payload.sound', $sound);
        expect($mine->where('payload.pos', 'start')->count())->toBeGreaterThanOrEqual(2, $sound)
            ->and($mine->where('payload.pos', 'end')->count())->toBeGreaterThanOrEqual(2, $sound)
            ->and($mine->where('payload.pos', 'middle')->count())->toBeGreaterThanOrEqual(2, $sound);
    }
    // level 1 has enough start/end words for a whole session of every kind
    expect($rows->where('level', 1)->where('payload.pos', '!=', 'middle')->count())->toBeGreaterThanOrEqual(30);
});

it('level 1: start or end only, two trains', function () {
    foreach (range(1, 5) as $i) {
        foreach (hangvonatSession(1)['rounds'] as $round) {
            $d = $round['data'];
            $item = BeszedContentItem::find($round['content_item_id']);
            expect($d['layout'])->toBe('two')
                ->and(collect($d['options'])->pluck('id')->all())->toBe(['start', 'end'])
                ->and($item->payload['pos'])->not->toBe('middle')
                ->and($d['answer'])->toBe(HangvonatRounds::position($item->payload['word'], $item->payload['sound']))
                ->and($d['stimulus']['say'])->toBe($item->payload['word']);
        }
    }
});

it('level 2: start, middle or end; the trains show the place', function () {
    $answers = collect();
    foreach (range(1, 5) as $i) {
        foreach (hangvonatSession(2)['rounds'] as $round) {
            $d = $round['data'];
            $item = BeszedContentItem::find($round['content_item_id']);
            expect($d['layout'])->toBe('two')
                ->and(collect($d['options'])->pluck('id')->all())->toBe(['start', 'middle', 'end'])
                ->and($d['answer'])->toBe($item->payload['pos'])
                ->and($round['prompt']['text'])->toContain(" {$item->payload['sound']} hang");
            foreach ($d['options'] as $o) {
                expect($o['emojis'])->toBe(HangvonatRounds::TRAINS[$o['id']]);
            }
            $answers->push($d['answer']);
        }
    }
    expect($answers->unique()->sort()->values()->all())->toBe(['end', 'middle', 'start']);
});

it('level 3: two look-alike sounds take turns, and the answer is where the one asked is', function () {
    foreach (range(1, 5) as $i) {
        $rounds = hangvonatSession(3)['rounds'];
        $sounds = collect($rounds)->map(fn ($r) => BeszedContentItem::find($r['content_item_id'])->payload['sound']);

        expect($sounds->unique()->sort()->values()->all())->toBeIn([['s', 'sz'], ['z', 'zs']]);
        foreach ($rounds as $k => $round) {
            $p = BeszedContentItem::find($round['content_item_id'])->payload;
            expect($round['data']['answer'])->toBe(HangvonatRounds::position($p['word'], $p['sound']))
                ->and($round['prompt']['text'])->toContain(" {$p['sound']} hangot");
            if ($k > 0) {
                expect($sounds[$k])->not->toBe($sounds[$k - 1]);
            }
        }
    }
});

it('names sounds the way the voice can say them', function () {
    foreach ([1, 2, 3] as $level) {
        foreach (hangvonatSession($level)['rounds'] as $round) {
            foreach ([...$round['prompt']['parts'], $round['data']['onCorrect'], $round['data']['onWrong']] as $said) {
                // a sound written on its own ("sss", "mmm") is spelled out letter by letter; "…" is read as "pont pont pont"
                expect($said)->not->toMatch('/\b(s{2,}z?|z{2,}|m{2,}|r{2,}|l{2,}|f{2,}|n{2,})\b/u')->not->toContain('…');
            }
        }
    }
});
