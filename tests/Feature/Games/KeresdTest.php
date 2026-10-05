<?php

use App\Beszed\Content\ContentRules;
use App\Beszed\Rounds\KeresdRounds as K;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Keresd meg!: the scene is laid out on the server inside the board, targets are spread out and never hidden,
// the counts follow the level, the same seed gives the same picture, and a clue (colour or first sound) is true
// for every target and for no other picture.

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

/** The emoji behind a picture: no pictogram prefix ("arasaac:2462~🍎" → "🍎"), no presentation selector. */
function keresdPlainEmoji(string $picture): string
{
    return str_replace("\u{FE0F}", '', preg_replace('/^[a-z]+:[^~]*~/u', '', $picture));
}

function keresdSession(int $level): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=keresd&level=$level")
        ->assertOk()
        ->json();
}

it('lays out a scene inside the board, with the level\'s targets and pictures', function (int $level) {
    $spec = K::spec($level);
    foreach (range(1, 3) as $repeat) {
        $session = keresdSession($level);
        expect($session['rounds'])->toHaveCount(4);

        foreach ($session['rounds'] as $round) {
            $d = $round['data'];
            $items = collect($d['items']);
            $targets = $items->where('target', true);

            expect($round['engine'])->toBe('hidden')
                ->and($d['count'])->toBe($targets->count())
                ->and($targets->count())->toBe($spec['targets'])
                ->and($targets->count())->toBeGreaterThanOrEqual(3)
                ->and($items->count())->toBe($spec['total'])
                ->and($items->pluck('id')->unique())->toHaveCount($items->count())
                ->and($d['backdrop'])->toBeIn(array_keys(config('beszed_content.schemas.keresd.fields.backdrop.options')))
                ->and($d['onCorrect'])->not->toBeEmpty();

            foreach ($items as $i => $it) {
                $r = $it['size'] / 2;
                // inside the board, big enough to tap, turned only above level 1
                expect($it['x'])->toBeGreaterThanOrEqual($r - 0.06)->toBeLessThanOrEqual(100 - $r + 0.06)
                    ->and($it['y'])->toBeGreaterThanOrEqual($r - 0.06)->toBeLessThanOrEqual(100 - $r + 0.06)
                    ->and($it['size'])->toBeGreaterThanOrEqual(K::MIN_SIZE)
                    ->and(abs($it['rotate']))->toBeLessThanOrEqual($spec['turn']);
            }
            // never mostly hidden; targets never on top of each other (so counting is clear)
            foreach ($d['items'] as $i => $it) {
                if ($it['target']) {
                    expect(K::covered($d['items'], $i))->toBeLessThanOrEqual(K::COVER_MAX);
                }
            }
            foreach ($targets->values() as $a => $ta) {
                foreach ($targets->values()->slice($a + 1) as $tb) {
                    expect(hypot($ta['x'] - $tb['x'], $ta['y'] - $tb['y']))->toBeGreaterThan(($ta['size'] + $tb['size']) / 2 * 0.9);
                }
            }
            // not degenerate: the pictures cover the board, not one corner of it
            expect($items->max('x') - $items->min('x'))->toBeGreaterThan(45)
                ->and($items->max('y') - $items->min('y'))->toBeGreaterThan(45);
        }
    }
})->with([1, 33, 66]);

it('finds one kind of picture below the clue tier, said in the accusative', function (int $level) {
    foreach (keresdSession($level)['rounds'] as $round) {
        $d = $round['data'];
        $targets = collect($d['items'])->where('target', true);
        $emoji = keresdPlainEmoji($targets->first()['emoji']);
        $item = BeszedContentItem::find($round['content_item_id']);
        $acc = collect($item->payload['targets'])->first(fn ($t) => keresdPlainEmoji($t[0]) === $emoji)[1];

        expect($item->payload['kind'])->toBe('find')
            ->and($targets->pluck('emoji')->unique()->all())->toBe([$targets->first()['emoji']])
            ->and($d['tray']['emoji'])->toBe($targets->first()['emoji'])
            ->and(collect($d['items'])->where('target', false)->pluck('emoji')->map(fn ($e) => keresdPlainEmoji($e)))->not->toContain($emoji)
            ->and($round['prompt']['text'])->toContain(' '.$acc)
            ->and($round['prompt']['text'])->not->toContain('…')
            ->and($d['counts'])->toHaveCount($d['count']);
    }
})->with([1, 40]);

it('covers some targets partly at a higher level, none at level 1', function () {
    $overlaps = function (array $items) {
        $n = 0;
        foreach ($items as $i => $it) {
            if ($it['target'] && K::covered($items, $i) > 0.02) {
                $n++;
            }
        }

        return $n;
    };
    $level1 = collect(range(1, 3))->flatMap(fn () => keresdSession(1)['rounds'])->sum(fn ($r) => $overlaps($r['data']['items']));
    $level50 = collect(range(1, 3))->flatMap(fn () => keresdSession(50)['rounds'])->sum(fn ($r) => $overlaps($r['data']['items']));

    expect($level1)->toBe(0)->and($level50)->toBeGreaterThan(0);
});

it('lays out the same picture for the same seed', function () {
    $pieces = [...array_fill(0, 5, ['emoji' => '🐞', 'target' => true]), ...array_fill(0, 20, ['emoji' => '🌷', 'target' => false])];

    expect(K::layout($pieces, 50, 12345))->toBe(K::layout($pieces, 50, 12345))
        ->and(K::layout($pieces, 50, 12345))->not->toBe(K::layout($pieces, 50, 54321));
});

it('gives a clue-tier clue that fits every target and no other picture', function () {
    foreach (range(1, 4) as $repeat) {
        foreach (keresdSession(85)['rounds'] as $round) {
            $d = $round['data'];
            $item = BeszedContentItem::find($round['content_item_id']);
            $p = $item->payload;
            $good = collect($p['targets'])->mapWithKeys(fn ($t) => [keresdPlainEmoji($t[0]) => $t[1]]);
            $bad = collect($p['others'])->mapWithKeys(fn ($t) => [keresdPlainEmoji($t[0]) => $t[1]]);

            expect($p['kind'])->toBe('clue')
                ->and($round['prompt']['text'])->toContain($p['clue'])
                ->and($round['prompt']['text'])->not->toContain('…');
            foreach ($d['items'] as $it) {
                // a colour clue keeps the emoji (its colours), never a pictogram drawn in other colours
                if (! isset($p['sound'])) {
                    expect($it['emoji'])->not->toStartWith('arasaac:')->not->toStartWith('mulberry:');
                }
                $it['emoji'] = keresdPlainEmoji($it['emoji']);
                if ($it['target']) {
                    expect($good)->toHaveKey($it['emoji'])
                        ->and($it['say'])->toBe(mb_strtoupper(mb_substr($good[$it['emoji']], 0, 1)).mb_substr($good[$it['emoji']], 1).'!');
                } else {
                    expect($bad)->toHaveKey($it['emoji'])->and($it['say'])->toContain($p['nope']);
                }
                if (isset($p['sound'])) {
                    $name = $it['target'] ? $good[$it['emoji']] : $bad[$it['emoji']];
                    expect(K::firstSound($name) === $p['sound'])->toBe($it['target']);
                }
            }
            // the distinct targets, each once
            $targets = collect($d['items'])->where('target', true)->pluck('emoji');
            expect($targets->unique()->count())->toBe($targets->count());
        }
    }
});

it('has deep, varied content: themes, backdrops, colours and sounds', function () {
    $rows = BeszedContentItem::forGame('keresd')->get()->pluck('payload');
    $find = $rows->where('kind', 'find');
    $clue = $rows->where('kind', 'clue');

    expect($find->count())->toBeGreaterThanOrEqual(12)
        ->and($find->pluck('backdrop')->unique()->count())->toBeGreaterThanOrEqual(6)
        ->and($clue->whereNull('sound')->count())->toBeGreaterThanOrEqual(6)
        ->and($clue->whereNotNull('sound')->count())->toBeGreaterThanOrEqual(6);
    // every theme can fill a level-2 round with decoys and scenery
    foreach ($find as $p) {
        expect(count($p['targets']))->toBeGreaterThanOrEqual(4)->and(count($p['fillers']))->toBeGreaterThanOrEqual(5);
    }
});

it('checks the first sound digraph-aware', function () {
    expect(K::firstSound('szék'))->toBe('sz')
        ->and(K::firstSound('sapka'))->toBe('s')
        ->and(K::firstSound('zsiráf'))->toBe('zs')
        ->and(K::firstSound('nyuszi'))->toBe('ny')
        ->and(K::firstSound('csiga'))->toBe('cs');

    $row = ['name' => 'X', 'backdrop' => 'room', 'kind' => 'clue', 'clue' => 'olyan dolgot, aminek a neve s hanggal kezdődik', 'nope' => 'neve nem s hanggal kezdődik', 'sound' => 's',
        'targets' => [['🧢', 'sapka'], ['🧀', 'sajt'], ['🦅', 'sas'], ['⛺', 'sátor'], ['🧦', 'sál'], ['🥣', 'sótartó']],
        'others' => [['🐶', 'kutya'], ['🍎', 'alma'], ['🐟', 'hal'], ['🚗', 'autó'], ['🍌', 'banán'], ['🐸', 'béka'], ['🎈', 'lufi'], ['⚽', 'labda']]];
    expect(ContentRules::check('keresd', $row))->toBe([])
        // "szék" starts with sz, not s
        ->and(ContentRules::check('keresd', ['targets' => [['🪑', 'szék'], ...array_slice($row['targets'], 1)]] + $row))->toHaveKey('targets')
        // a look-alike sound among the pictures that don't fit
        ->and(ContentRules::check('keresd', ['others' => [['🐌', 'csiga'], ...array_slice($row['others'], 1)]] + $row))->toHaveKey('others')
        // the same picture can't be right and wrong
        ->and(ContentRules::check('keresd', ['others' => [['🧢', 'kalap'], ...array_slice($row['others'], 1)]] + $row))->toHaveKey('others')
        // levels 1–2 say the target in the accusative
        ->and(ContentRules::check('keresd', ['name' => 'Y', 'backdrop' => 'garden', 'kind' => 'find', 'targets' => [['🐞', 'katica'], ['🐌', 'csigát'], ['🦋', 'pillangót']], 'fillers' => ['🌷', '🌼', '🌿', '🍃', '🪨']]))->toHaveKey('targets');
});
