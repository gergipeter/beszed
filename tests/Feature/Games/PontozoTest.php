<?php

use App\Beszed\Content\ContentRules;
use App\Beszed\Content\Hungarian;
use App\Beszed\Rounds\PontozoRounds as P;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Pontról pontra: the level sets the number of dots (6, 10, 15), dots stay on the board and far enough apart
// for a finger (labels never touch), numbers go 1…N, and at the top level every other round is the Hungarian
// ABC in order, a two-letter letter being one dot.

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function pontozoSession(int $level): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=pontozo&level=$level")
        ->assertOk()
        ->json();
}

it('gives the level\'s number of dots, on the board and apart', function (int $level, int $n, int $tier) {
    foreach (range(1, 3) as $repeat) {
        foreach (pontozoSession($level)['rounds'] as $r => $round) {
            $d = $round['data'];
            $dots = collect($d['dots']);
            $item = BeszedContentItem::find($round['content_item_id']);

            expect($round['engine'])->toBe('dots')
                ->and($dots)->toHaveCount($n)
                ->and($item->level)->toBeLessThanOrEqual($tier)
                // the emoji itself (the outline was drawn after it), never a pictogram
                ->and(str_replace("\u{FE0F}", '', $d['emoji']))->toBe(str_replace("\u{FE0F}", '', $item->payload['emoji']))
                ->and($d['name'])->toBe($item->payload['name'])
                ->and($d['onCorrect'])->toContain($item->payload['name'])
                ->and($round['prompt']['text'])->not->toContain('…');
            foreach ($dots as $dot) {
                expect($dot['x'])->toBeGreaterThanOrEqual(0.04)->toBeLessThanOrEqual(0.96)
                    ->and($dot['y'])->toBeGreaterThanOrEqual(0.04)->toBeLessThanOrEqual(0.96)
                    ->and($dot['say'])->not->toBeEmpty()
                    ->and($dot['hint'])->not->toBeEmpty();
            }
            expect(P::minGap($dots->map(fn ($p) => [$p['x'], $p['y']])->all()))->toBeGreaterThanOrEqual(P::MIN_GAP - 0.001);

            $letters = $tier >= 3 && $r % 2 === 1;
            expect($dots->pluck('label')->all())->toBe($letters ? P::abc($n) : array_map('strval', range(1, $n)));
        }
    }
})->with([[1, 6, 1], [50, 10, 2], [100, 15, 3]]); // level → [scaleInt(level,6,15), tier(level,3)]

it('counts in words and hints at the dot that comes next', function () {
    $dots = pontozoSession(1)['rounds'][0]['data']['dots'];

    expect(array_column($dots, 'say'))->toBe(['Egy!', 'Kettő!', 'Három!', 'Négy!', 'Öt!', 'Hat!'])
        ->and($dots[1]['hint'])->toBe('Az egy után melyik jön?')
        ->and($dots[2]['hint'])->toBe('A kettő után melyik jön?')
        ->and($dots[5]['hint'])->toBe('Az öt után melyik jön?');
});

it('alternates numbers and the ABC at level 3, two-letter letters as one dot', function () {
    $rounds = pontozoSession(100)['rounds']; // tier 3: levels 67-100, scaleInt(100,6,15)=15 dots
    $abc = $rounds[1]['data']['dots'];

    expect(array_column($abc, 'label'))->toBe(['a', 'á', 'b', 'c', 'cs', 'd', 'dz', 'dzs', 'e', 'é', 'f', 'g', 'gy', 'h', 'i'])
        ->and(array_column($abc, 'say'))->toBe(['A!', 'Á!', 'Bé!', 'Cé!', 'Csé!', 'Dé!', 'Dzé!', 'Dzsé!', 'E!', 'É!', 'Ef!', 'Gé!', 'Gyé!', 'Há!', 'I!'])
        ->and($abc[5]['hint'])->toBe('A csé után melyik betű jön?')
        ->and($rounds[1]['prompt']['text'])->toContain('ábécé')
        ->and(array_column($rounds[0]['data']['dots'], 'label'))->toBe(array_map('strval', range(1, 15)))
        ->and(array_column($rounds[3]['data']['dots'], 'label'))->toBe(P::abc(15));

    // the ABC is the Hungarian one, in order, each letter as Hungarian::letters sees it
    expect(P::ABC)->toHaveCount(40)
        ->and(collect(P::ABC)->every(fn ($l) => count(Hungarian::letters($l)) === 1))->toBeTrue()
        ->and(P::abc(8))->toBe(['a', 'á', 'b', 'c', 'cs', 'd', 'dz', 'dzs']);
});

it('makes every shape playable at every level it is used on', function () {
    $rows = json_decode(file_get_contents(database_path('seeders/data/beszed/pontozo.json')), true);
    foreach ($rows as $row) {
        foreach (P::DOTS as $level => $n) {
            if ($level < $row['level']) {
                continue;
            }
            $dots = P::dots(P::parse($row['payload']['points']), $n);
            expect($dots)->toHaveCount($n, "{$row['payload']['name']} level $level")
                ->and(P::minGap($dots))->toBeGreaterThanOrEqual(P::MIN_GAP, "{$row['payload']['name']} level $level");
            // not degenerate: the dots fill the board one way and are not flat the other
            $spans = [max(array_column($dots, 0)) - min(array_column($dots, 0)), max(array_column($dots, 1)) - min(array_column($dots, 1))];
            expect(max($spans))->toBeGreaterThan(0.8)->and(min($spans))->toBeGreaterThan(0.3);
        }
    }
});

it('keeps the corners that make a shape (a star keeps its five tips)', function () {
    $star = BeszedContentItem::forGame('pontozo')->get()->first(fn ($i) => $i->payload['name'] === 'csillag');
    $dots = P::dots(P::parse($star->payload['points']), 10);
    // the outer tips lie far from the centre, the inner corners close to it
    $c = [array_sum(array_column($dots, 0)) / 10, array_sum(array_column($dots, 1)) / 10];
    $far = collect($dots)->filter(fn ($p) => hypot($p[0] - $c[0], $p[1] - $c[1]) > 0.35);

    expect($far)->toHaveCount(5);
});

it('has at least 16 shapes, enough for every level', function () {
    $rows = BeszedContentItem::forGame('pontozo')->get();

    expect($rows->count())->toBeGreaterThanOrEqual(16)
        ->and($rows->where('level', 1)->count())->toBeGreaterThanOrEqual(6)
        ->and($rows->where('level', '<=', 2)->count())->toBeGreaterThanOrEqual(12)
        ->and($rows->pluck('payload.emoji')->unique()->count())->toBe($rows->count());
});

it('rejects points outside the board', function () {
    expect(ContentRules::check('pontozo', ['name' => 'x', 'emoji' => '⭐', 'points' => ['0.5,0.1', '0.9,1.4', '0.1,0.9', '0.2,0.2']]))->toHaveKey('points')
        ->and(ContentRules::check('pontozo', ['name' => 'x', 'emoji' => '⭐', 'points' => ['0.5,0.1', '0.9,0.9', '0.1,0.9']]))->toHaveKey('points')
        ->and(ContentRules::check('pontozo', ['name' => 'x', 'emoji' => '⭐', 'points' => ['0.5,0.1', '0.9,0.9', '0.1,0.9', '0.3,0.5']]))->toBe([]);
});
