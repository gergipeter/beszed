<?php

use App\Beszed\Content\ContentRules;
use App\Beszed\Content\Hungarian;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Csigabeszéd (choice): the snail says a word syllable by syllable; the child taps its picture.
// The pieces must be the word's real syllables, said one by one, and no wrong picture may start the same way.

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function csigaRows(): array
{
    return json_decode(file_get_contents(database_path('seeders/data/beszed/csigabeszed.json')), true, flags: JSON_THROW_ON_ERROR);
}

function csigaSession(int $level): array
{
    return actingAs(test()->user)->getJson('/api/beszed/children/'.test()->child->id."/session?game=csigabeszed&level=$level")->assertOk()->json();
}

it('has at least 80 words cut into their real syllables, graded by length', function () {
    $rows = collect(csigaRows());

    expect($rows->count())->toBeGreaterThanOrEqual(80);
    foreach ($rows as $row) {
        $p = $row['payload'];
        $n = count($p['pieces']);
        expect(ContentRules::check('csigabeszed', $p))->toBe([], $p['word'])
            ->and($p['pieces'])->toBe(Hungarian::syllables($p['word']))
            ->and($n)->toBe(ContentRules::vowelCount($p['word']))
            ->and($row['level'])->toBe($n <= 2 ? 1 : ($n === 3 ? 2 : 3), $p['word']);
    }
    foreach ([1, 2, 3] as $level) {
        expect($rows->where('level', $level)->count())->toBeGreaterThanOrEqual(15, "level $level");
    }
    // the snail is the stimulus: the snail's own word would give itself away
    expect($rows->pluck('payload.emoji'))->not->toContain('🐌');
});

it('rejects pieces that are not the syllables', function (array $pieces) {
    expect(ContentRules::check('csigabeszed', ['word' => 'cica', 'emoji' => '🐱', 'pieces' => $pieces]))->toHaveKey('pieces');
})->with([[['ci', 'ka']], [['c', 'ica']], [['cic', 'a']]]);

it('says the pieces one by one, then asks; the level sets the length and the number of pictures', function (int $level) {
    foreach (range(1, 5) as $i) {
        foreach (csigaSession($level)['rounds'] as $k => $round) {
            $d = $round['data'];
            $item = BeszedContentItem::find($round['content_item_id']);
            $pieces = $item->payload['pieces'];
            $parts = $round['prompt']['parts'];
            $options = collect($d['options']);

            expect(match ($level) { 1 => count($pieces) === 2, 2 => count($pieces) === 3, 3 => count($pieces) >= 4 })->toBeTrue($item->payload['word'])
                // each piece is its own utterance, in order, with nothing glued to it
                ->and(array_slice($parts, $k === 0 ? 1 : 0, count($pieces)))->toBe($pieces)
                ->and($options)->toHaveCount($level === 3 ? 4 : 3)
                ->and($d['layout'])->toBe($level === 3 ? 'four' : 'three')
                ->and($d['answer'])->toBe((string) $item->id)
                ->and($options->pluck('id'))->toContain($d['answer'])
                ->and($options->pluck('emoji')->unique())->toHaveCount($options->count());
            foreach ($parts as $part) {
                expect($part)->not->toContain('…')->and(preg_match('/[aáeéiíoóöőuúüű]/iu', $part))->toBe(1, $part);
            }
            // no wrong picture starts with the same piece (ka… is kacsa or kakas?)
            $first = mb_strtolower($pieces[0]);
            foreach ($options->where('id', '!=', $d['answer']) as $o) {
                $other = BeszedContentItem::find((int) $o['id']);
                expect(mb_strtolower($other->payload['pieces'][0]))->not->toBe($first, "{$item->payload['word']} / {$other->payload['word']}")
                    ->and($other->payload['word'])->not->toBe($item->payload['word']);
            }
        }
    }
})->with([1, 2, 3]);
