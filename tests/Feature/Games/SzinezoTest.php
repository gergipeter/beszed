<?php

use App\Beszed\Content\ContentRules;
use App\Beszed\Content\Hungarian;
use App\Beszed\Rounds\SzinezoRounds as S;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Színező: the pictures the server names are the ones the client draws, region by region; every step is a
// region of its picture and a known paint; the level sets the sentences (one part, one part, two parts with
// left/right) and the paint pots (3, 6, 8, always including every colour asked); the Hungarian is built from
// stored accusative and sublative forms ("a tetőt pirosra", "a füvet zöldre", "a jobb oldalit pedig kékre").

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function szinezoSession(int $level): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=szinezo&level=$level")
        ->assertOk()
        ->json();
}

/** A content item that is never saved, for building rounds directly. */
function szinezoItem(int $id, string $picture, string $name, array $steps, int $level = 1): BeszedContentItem
{
    $item = new BeszedContentItem(['game' => 'szinezo', 'level' => $level, 'payload' => compact('picture', 'name', 'steps')]);
    $item->id = $id;

    return $item;
}

it('knows exactly the pictures and regions the client draws (engines/color/pictures.js)', function () {
    $file = resource_path('js/modules/beszed/engines/color/pictures.js');
    if (! is_file($file)) {
        $this->markTestSkipped('resources/js is not available here');
    }
    $js = file_get_contents($file);
    // each picture starts with "  <id>: {" and lists its regions as R('<id>', …)
    preg_match_all('/^  (\w+): \{\n(.*?)^  \},/ms', $js, $blocks, PREG_SET_ORDER);
    $library = collect($blocks)->mapWithKeys(function ($b) {
        preg_match_all("/\\bR\\(\\s*'(\\w+)'/", $b[2], $ids);

        return [$b[1] => collect($ids[1])->sort()->values()->all()];
    });

    expect($library->keys()->sort()->values()->all())->toBe(collect(array_keys(S::PICTURES))->sort()->values()->all());
    foreach (S::PICTURES as $picture => $regions) {
        expect($library[$picture])->toBe(collect(array_keys($regions))->sort()->values()->all(), "regions of $picture");
    }
    // the editor's picture list is the library too
    expect(array_keys(config('beszed_content.schemas.szinezo.fields.picture.options')))->toEqualCanonicalizing(array_keys(S::PICTURES));
});

it('stores real accusative and sublative forms', function () {
    foreach (S::PICTURES as $picture => $regions) {
        foreach ($regions as $id => [$name, $acc]) {
            expect($acc)->toEndWith('t', "$picture.$id")
                ->and(mb_substr(Hungarian::fold($acc), 0, 1))->toBe(mb_substr(Hungarian::fold($name), 0, 1), "$picture.$id");
            if (S::isSide($id)) {
                expect($name)->toStartWith(str_ends_with($id, '_bal') ? 'bal oldali ' : 'jobb oldali ');
            }
        }
    }
    foreach (S::COLORS as $id => [$name, $onto]) {
        expect($onto)->toMatch('/^'.preg_quote(mb_substr($name, 0, -1), '/').'.+(ra|re)$/u', $id);
    }
    // the irregular ones, spelled out
    expect(S::PICTURES['haz']['fu'][1])->toBe('füvet')
        ->and(S::PICTURES['hoember']['ho'][1])->toBe('havat')
        ->and(S::PICTURES['hajo']['viz'][1])->toBe('vizet')
        ->and(S::PICTURES['auto']['kerek_bal'][1])->toBe('bal oldali kereket')
        ->and(S::PICTURES['fa']['kosar'][1])->toBe('kosarat');
});

it('has at least twelve pictures, each with enough to ask at every level', function () {
    $items = BeszedContentItem::forGame('szinezo')->get();
    expect($items->pluck('payload.picture')->unique()->count())->toBeGreaterThanOrEqual(12);

    foreach ($items as $item) {
        $steps = collect($item->payload['steps'])->map(fn ($s) => S::parseStep($s));
        expect($steps->reject(fn ($s) => S::isSide($s[0]))->count())->toBeGreaterThanOrEqual(S::MIN_PLAIN_STEPS);
    }
    // level 3 is about left and right: most pictures have such a pair
    $withPair = $items->filter(fn ($i) => collect($i->payload['steps'])->contains(fn ($s) => str_contains($s, '_bal=')));
    expect($withPair->count())->toBeGreaterThanOrEqual(8);
});

it('rejects unknown pictures, regions and colours', function (array $payload, string $field) {
    expect(ContentRules::check('szinezo', $payload))->toHaveKey($field);
})->with([
    'unknown picture' => [['picture' => 'kastely', 'name' => 'Kastély', 'steps' => ['teto=piros', 'fal=sarga', 'ajto=barna', 'fu=zold']], 'picture'],
    'region of another picture' => [['picture' => 'haz', 'name' => 'Ház', 'steps' => ['teto=piros', 'fal=sarga', 'ajto=barna', 'kalap=zold']], 'steps'],
    'unknown colour' => [['picture' => 'haz', 'name' => 'Ház', 'steps' => ['teto=piros', 'fal=sarga', 'ajto=barna', 'fu=turkiz']], 'steps'],
    'not region=colour' => [['picture' => 'haz', 'name' => 'Ház', 'steps' => ['teto piros', 'fal=sarga', 'ajto=barna', 'fu=zold']], 'steps'],
    'a region twice' => [['picture' => 'haz', 'name' => 'Ház', 'steps' => ['teto=piros', 'teto=sarga', 'ajto=barna', 'fu=zold']], 'steps'],
    'too few plain steps' => [['picture' => 'haz', 'name' => 'Ház', 'steps' => ['teto=piros', 'fal=sarga', 'ablak_bal=kek', 'ablak_jobb=zold']], 'steps'],
]);

it('follows the level: sentences, parts and paint pots', function (int $level, int $tier, int $pots) {
    foreach (range(1, 4) as $repeat) {
        foreach (szinezoSession($level)['rounds'] as $round) {
            $d = $round['data'];
            $item = BeszedContentItem::find($round['content_item_id']);
            $regions = S::PICTURES[$d['picture']];
            $asked = collect($item->payload['steps'])->map(fn ($s) => S::parseStep($s))->mapWithKeys(fn ($s) => [$s[0] => $s[1]]);
            $fills = collect($d['steps'])->flatMap(fn ($s) => $s['fills']);

            expect($round['engine'])->toBe('color')
                ->and($d['picture'])->toBe($item->payload['picture'])
                ->and($round['prompt']['text'])->toEndWith($d['steps'][0]['say'])
                ->and(array_keys($d['regions']))->toBe(array_keys($regions))
                ->and($d['freePots'])->toBe(array_keys(S::COLORS))
                // every pot needed is there, in paint-box order
                ->and($d['pots'])->toHaveCount($pots)
                ->and(array_diff($fills->pluck('color')->unique()->all(), $d['pots']))->toBe([])
                ->and($d['pots'])->toBe(array_values(array_intersect(array_keys(S::COLORS), $d['pots'])))
                // each part once, with the colour the content gives it
                ->and($fills->pluck('region')->duplicates()->all())->toBe([]);
            foreach ($fills as $f) {
                expect($asked[$f['region']] ?? null)->toBe($f['color']);
            }

            if ($tier < 3) {
                expect($d['steps'])->toHaveCount($tier === 1 ? 3 : 4)
                    ->and($fills->contains(fn ($f) => S::isSide($f['region'])))->toBeFalse();
                foreach ($d['steps'] as $s) {
                    expect($s['fills'])->toHaveCount(1)->and($s['say'])->toStartWith('Színezd ');
                }
            } else {
                expect(count($d['steps']))->toBeGreaterThanOrEqual(2);
                foreach ($d['steps'] as $s) {
                    expect($s['fills'])->toHaveCount(2)->and($s['say'])->toMatch('/^Színezd .+, .+ (pedig|is) .+(ra|re)!$/u');
                }
                // the left/right pair, said the short way the second time
                $last = end($d['steps']);
                expect(collect($last['fills'])->every(fn ($f) => S::isSide($f['region'])))->toBeTrue()
                    ->and($last['say'])->toContain('bal oldali')
                    ->and($last['say'])->toMatch('/a jobb oldalit (pedig|is) /u');
            }
        }
    }
})->with([[1, 1, 3], [50, 2, 5], [100, 3, 8]]); // level → [tier(level,3), scaleInt(level,3,8)]

it('says the steps in correct Hungarian', function () {
    $house = szinezoItem(1, 'haz', 'Ház', ['teto=piros', 'ajto=barna', 'fu=zold']);
    $round = (new S)->build(collect([$house]), 1, 1)[0];
    [$roof, $door, $grass] = $round['data']['steps'];

    expect($round['prompt']['text'])->toBe('Nézd, egy ház! Először koppints a festékre, aztán a kép részére. Színezd a tetőt pirosra!')
        ->and($roof['say'])->toBe('Színezd a tetőt pirosra!')
        ->and($roof['fills'][0]['wrongColor'])->toBe('Hoppá, ez nem piros! Válaszd a piros festéket!')
        ->and($roof['fills'][0]['wrongPart'])->toBe('Hoppá, nem ott! Keresd meg a tetőt, és színezd pirosra!')
        ->and($door['say'])->toBe('Színezd az ajtót barnára!')
        ->and($door['lead'])->toMatch('/^(Szép|Ügyes|Így van|Nagyon jó)! Most (színezd az ajtót barnára|az ajtót színezd barnára)!$/u')
        ->and($grass['say'])->toBe('Színezd a füvet zöldre!')
        ->and($round['data']['onCorrect'])->toContain('a ház')
        // three pots: the colours asked and no look-alike of them at level 1
        ->and($round['data']['pots'])->toHaveCount(3)->toContain('piros', 'barna', 'zold');

    $level3 = (new S)->build(collect([szinezoItem(2, 'haz', 'Ház', ['teto=piros', 'ajto=barna', 'fu=zold', 'nap=sarga', 'ablak_bal=kek', 'ablak_jobb=sarga'], 3)]), 100, 1)[0]; // tier 3: levels 67-100
    expect(collect($level3['data']['steps'])->pluck('say')->all())->toBe([
        'Színezd a tetőt pirosra, az ajtót pedig barnára!',
        'Színezd a füvet zöldre, a napot pedig sárgára!',
        'Színezd a bal oldali ablakot kékre, a jobb oldalit pedig sárgára!',
    ]);

    // the same colour twice: "is", not "pedig"
    $snowman = (new S)->build(collect([szinezoItem(3, 'hoember', 'Hóember', ['kalap=fekete', 'sal=piros', 'orr=narancs', 'gombok=kek', 'fenyo_bal=zold', 'fenyo_jobb=zold'], 3)]), 100, 1)[0]; // tier 3: levels 67-100
    expect(end($snowman['data']['steps'])['say'])->toBe('Színezd a bal oldali fenyőfát zöldre, a jobb oldalit is zöldre!')
        ->and($snowman['data']['steps'][0]['say'])->toBe('Színezd a kalapot feketére, a sálat pedig pirosra!')
        ->and($snowman['data']['steps'][1]['fills'][0]['wrongPart'])->toBe('Hoppá, nem ott! Keresd meg a hóember orrát, és színezd narancssárgára!');
});
