<?php

use App\Beszed\Content\ContentRules;
use App\Beszed\Rounds\HangrepuloRounds;
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

function hangrepuloSession(int $level): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=hangrepulo&level=$level")
        ->assertOk()
        ->json();
}

it('Hangrepülő levels 1–2: hold the modelled sound, in all or without stopping', function (int $level, int $ms, bool $continuous) {
    $session = hangrepuloSession($level);

    expect($session['rounds'])->toHaveCount(4);
    foreach ($session['rounds'] as $r => $round) {
        $d = $round['data'];
        $item = BeszedContentItem::find($round['content_item_id']);
        expect($round['engine'])->toBe('voice')
            ->and($d['mode'])->toBe('sustain')
            ->and($item->payload['kind'])->toBe('sustain')
            ->and($d['target'])->toBe(['ms' => $ms, 'continuous' => $continuous])
            ->and($d['shown'])->toBe($item->payload['shown'])
            // Csillám asks for the sound to hold, with what it is like
            ->and($round['prompt']['text'])->toContain($item->payload['model'].', '.$d['helper'])
            ->and($d['flyer'])->toBeIn(array_keys(HangrepuloRounds::FLYERS))
            ->and($d['hints']['quiet'])->toContain($item->payload['model'])
            ->and($d['onCorrect'])->not->toBeEmpty();
        expect(isset($d['hints']['fell']))->toBe($continuous);
        // the first round explains what the voice does to the flyer
        expect(count($round['prompt']['parts']))->toBe(($r === 0 ? 2 : 1) + ($continuous ? 1 : 0));
    }
})->with([[1, 2000, false], [66, 4000, true]]); // level → scaleInt(level,2000,4000,66), continuous once tier >= 2

it('Hangrepülő level 3: high and low stars in turn, with a voiced sound', function () {
    foreach (hangrepuloSession(100)['rounds'] as $r => $round) { // tier 3: levels 67-100
        $d = $round['data'];
        $stars = collect($d['stars']);
        expect($d['mode'])->toBe('pitch')
            ->and($d['sound'])->toBeIn(HangrepuloRounds::VOICED)
            ->and($stars->count())->toBe($r === 0 ? 2 : 3)
            ->and($d['hints'])->toHaveKeys(['high', 'low', 'quiet'])
            ->and($d['onCorrect'])->toContain($r === 0 ? 'két' : 'három');
        // left to right, alternating high and low
        expect($stars->pluck('x')->all())->toBe($stars->pluck('x')->sort()->values()->all());
        foreach ($stars->values() as $k => $star) {
            expect($star['x'])->toBeGreaterThan(0)->toBeLessThan(1);
            if ($k > 0) {
                expect($star['high'])->not->toBe($stars[$k - 1]['high']);
            }
        }
    }
});

it('Hangrepülő has deep content: every shown form is the sound held long, every model can be said', function () {
    $rows = collect(json_decode(file_get_contents(database_path('seeders/data/beszed/hangrepulo.json')), true));

    expect($rows->count())->toBeGreaterThanOrEqual(16)
        ->and($rows->where('payload.kind', 'sustain')->count())->toBeGreaterThanOrEqual(12)
        ->and($rows->where('payload.kind', 'pitch')->count())->toBeGreaterThanOrEqual(5)
        ->and($rows->pluck('payload.sound')->unique()->count())->toBeGreaterThanOrEqual(10);
    foreach ($rows as $row) {
        $p = $row['payload'];
        expect(HangrepuloRounds::modelFits($p['sound'], $p['shown']))->toBeTrue($p['shown'])
            ->and(HangrepuloRounds::speakable($p['sound'], $p['shown'], $p['model']))->toBeTrue($p['model'])
            ->and($row['level'])->toBeIn($p['kind'] === 'pitch' ? [3] : [1, 2]);
    }
});

it('Hangrepülő rules: the model must be the sound, and pitch needs a voice', function () {
    expect(HangrepuloRounds::modelFits('sz', 'sssz'))->toBeTrue()
        ->and(HangrepuloRounds::modelFits('sz', 'zzz'))->toBeFalse()
        ->and(HangrepuloRounds::modelFits('á', 'ááá'))->toBeTrue()
        ->and(HangrepuloRounds::modelFits('á', 'aaa'))->toBeFalse()
        ->and(HangrepuloRounds::modelFits('m', 'mm'))->toBeFalse()
        // the voice would spell these out: "mmm" → "em em em", "…" → "pont pont pont"
        ->and(HangrepuloRounds::speakable('m', 'mmm', 'Mondd: mmm'))->toBeFalse()
        ->and(HangrepuloRounds::speakable('á', 'ááá', 'Mondd hosszan… ááá'))->toBeFalse()
        ->and(HangrepuloRounds::speakable('á', 'ááá', 'Mondd hosszan'))->toBeFalse()
        ->and(HangrepuloRounds::speakable('sz', 'sssz', 'Sziszegj hosszan'))->toBeTrue()
        ->and(ContentRules::check('hangrepulo', ['sound' => 'm', 'shown' => 'mmmo', 'model' => 'Dúdolj', 'helper' => 'x', 'emoji' => '🍲', 'kind' => 'sustain', 'flyer' => 'rocket']))->toHaveKey('shown')
        ->and(ContentRules::check('hangrepulo', ['sound' => 'z', 'shown' => 'zzz', 'model' => 'Mondd: zzz', 'helper' => 'x', 'emoji' => '🪰', 'kind' => 'sustain', 'flyer' => 'bee']))->toHaveKey('model')
        ->and(ContentRules::check('hangrepulo', ['sound' => 'sz', 'shown' => 'sssz', 'model' => 'Sziszegj', 'helper' => 'x', 'emoji' => '🐍', 'kind' => 'pitch', 'flyer' => 'rocket']))->toHaveKey('sound')
        ->and(ContentRules::check('hangrepulo', ['sound' => 'z', 'shown' => 'zzz', 'model' => 'Zümmögj', 'helper' => 'x', 'emoji' => '🪰', 'kind' => 'pitch', 'flyer' => 'bee']))->toBe([]);
});
