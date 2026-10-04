<?php

use App\Beszed\Content\ContentRules;
use App\Beszed\Rounds\FujokaRounds;
use App\Models\BeszedContentItem;
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

function fujokaSession(int $level): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=fujoka&level=$level")
        ->assertOk()
        ->json();
}

it('Fújóka plays blowing rounds of the level\'s kind, in a scene that can show it', function (int $level, array $kinds) {
    $session = fujokaSession($level);

    expect($session['rounds'])->toHaveCount(4);
    foreach ($session['rounds'] as $round) {
        $d = $round['data'];
        expect($round['engine'])->toBe('voice')
            ->and($d['mode'])->toBe('blow')
            ->and($d['kind'])->toBeIn($kinds)
            ->and($d['scene'])->toBeIn(FujokaRounds::SCENES[$d['kind']])
            ->and($d['onCorrect'])->not->toBeEmpty()
            ->and($d['hints']['voiced'])->not->toBeEmpty()
            ->and($round['prompt']['parts'])->toHaveCount(2)
            ->and($round['prompt']['text'])->toStartWith(BeszedContentItem::find($round['content_item_id'])->payload['story']);
    }
})->with([[1, ['puffs']], [2, ['long']], [3, ['gentle', 'alternate']]]);

it('Fújóka level 1: short puffs, as many as the content says', function () {
    foreach (fujokaSession(1)['rounds'] as $round) {
        $item = BeszedContentItem::find($round['content_item_id']);
        expect($round['data']['target'])->toBe(['puffs' => $item->payload['count']])
            ->and($round['data']['target']['puffs'])->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(5);
    }
});

it('Fújóka level 2: one long blow growing from 1.5 s to 2.5 s', function () {
    $ms = collect(fujokaSession(2)['rounds'])->pluck('data.target.holdMs')->all();

    expect($ms)->toBe([1500, 1800, 2200, 2500])
        ->and(collect(fujokaSession(2)['rounds'])->pluck('data.hints.fell')->filter()->count())->toBe(4);
});

it('Fújóka level 3: gentle and soft-and-strong rounds take turns', function () {
    $rounds = fujokaSession(3)['rounds'];

    expect(collect($rounds)->pluck('data.kind')->all())->toBe(['gentle', 'alternate', 'gentle', 'alternate']);
    foreach ($rounds as $round) {
        $d = $round['data'];
        if ($d['kind'] === 'gentle') {
            expect($d['target'])->toBe(['softMs' => FujokaRounds::SOFT_MS])
                ->and($d['hints']['tooStrong'])->not->toBeEmpty();
        } else {
            $steps = collect($d['steps']);
            expect($d['target'])->toBe(['stepMs' => FujokaRounds::STEP_MS])
                ->and($steps->count())->toBe(BeszedContentItem::find($round['content_item_id'])->payload['steps'])
                // soft first, then strong, then soft… each with what Csillám says
                ->and($steps->pluck('strength')->all())->toBe(array_map(fn ($i) => $i % 2 ? 'strong' : 'soft', range(0, $steps->count() - 1)))
                ->and($steps->every(fn ($s) => $s['say'] !== ''))->toBeTrue();
        }
    }
});

it('Fújóka has deep enough content for every level, each in a fitting scene', function () {
    $rows = collect(json_decode(file_get_contents(database_path('seeders/data/beszed/fujoka.json')), true));
    $byKind = $rows->groupBy('payload.kind');

    expect($rows->count())->toBeGreaterThanOrEqual(18)
        ->and($byKind['puffs']->count())->toBeGreaterThanOrEqual(8)
        ->and($byKind['long']->count())->toBeGreaterThanOrEqual(6)
        ->and($byKind['gentle']->count())->toBeGreaterThanOrEqual(4)
        ->and($byKind['alternate']->count())->toBeGreaterThanOrEqual(3)
        // every scene is used
        ->and($rows->pluck('payload.scene')->unique()->sort()->values()->all())->toBe(['boat', 'bubbles', 'candles', 'dandelion', 'feather', 'pinwheel']);
    // the item's level is the level that plays its kind
    foreach ($rows as $row) {
        $kind = $row['payload']['kind'];
        expect(in_array($kind, FujokaRounds::KINDS[$row['level']], true))->toBeTrue($row['payload']['name']);
    }
});

it('Fújóka rules: a scene that cannot show the blow is refused', function () {
    expect(ContentRules::check('fujoka', ['name' => 'X', 'story' => 'Y.', 'scene' => 'feather', 'kind' => 'puffs', 'count' => 3]))->toHaveKey('scene')
        ->and(ContentRules::check('fujoka', ['name' => 'X', 'story' => 'Y.', 'scene' => 'candles', 'kind' => 'puffs']))->toHaveKey('count')
        ->and(ContentRules::check('fujoka', ['name' => 'X', 'story' => 'Y.', 'scene' => 'boat', 'kind' => 'long']))->toBe([]);
});
