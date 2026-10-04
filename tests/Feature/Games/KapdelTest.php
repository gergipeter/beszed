<?php

use App\Beszed\Content\ContentRules;
use App\Beszed\Rounds\KapdelRounds as K;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Kapd el! (catch engine): the stream starts with a target, keeps its rhythm (slow enough to say every word at
// level 3), judges every bubble by the row's own rule, turns round correctly in the switch round, and the
// listening rows really have (or lack) the sound, digraph-aware.

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function kapdelSession(int $level): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=kapdel&level=$level")
        ->assertOk()
        ->json();
}

function kapdelRows(): Illuminate\Support\Collection
{
    return collect(json_decode(file_get_contents(database_path('seeders/data/beszed/kapdel.json')), true, flags: JSON_THROW_ON_ERROR))->pluck('payload');
}

it('has at least 30 rule rows of every kind', function () {
    $rows = kapdelRows();
    $kinds = $rows->countBy('kind');

    expect($rows->count())->toBeGreaterThanOrEqual(30)
        ->and($kinds['visual'])->toBeGreaterThanOrEqual(12)
        ->and($kinds['category'])->toBeGreaterThanOrEqual(8)
        ->and($kinds['sound'])->toBeGreaterThanOrEqual(8)
        ->and($rows->where('kind', 'category')->filter(fn ($r) => ! empty($r['reverse']))->count())->toBeGreaterThanOrEqual(2)
        // the sound is named by its letter: TTS spells "rrr" out, and reads "…" aloud
        ->and($rows->pluck('rule')->filter(fn ($r) => preg_match('/rrr|lll|mmm|sss|zzz|fff|…/u', $r))->all())->toBe([]);
});

it('plays a stream that keeps the rule', function (int $level) {
    $session = kapdelSession($level);
    expect($session['level'])->toBe($level)->and($session['rounds'])->toHaveCount(4);

    foreach ($session['rounds'] as $r => $round) {
        $data = $round['data'];
        $row = BeszedContentItem::find($round['content_item_id'])->payload;
        $stream = collect($data['stream']);
        $switch = $data['switch'] ?? null;

        expect($round['engine'])->toBe('catch')
            ->and($data['grade'])->toBe(K::GRADE)
            ->and($stream->first()['target'])->toBeTrue()
            ->and($stream->pluck('id')->unique()->count())->toBe($stream->count())
            ->and($stream->where('target', true)->count())->toBeGreaterThanOrEqual($data['need'] + 2)
            ->and($stream->pluck('why')->filter(fn ($w) => str_contains($w, '…'))->all())->toBe([]);

        // in order, never the same lane twice in a row, never two bubbles on top of each other
        $stream->sliding(2)->each(function ($pair) use ($data) {
            [$a, $b] = $pair->values()->all();
            expect($b['at'] - $a['at'])->toBeGreaterThanOrEqual($data['mode'] === 'hear' ? K::SAY_GAP_MS : 1000)
                ->and($b['x'])->not->toBe($a['x']);
        });

        if ($switch) {
            // level 3, the third round: the group first, then the rest
            expect($level)->toBe(3)->and($r)->toBe(2)->and($row['kind'])->toBe('category')->and($switch['say'])->toBe($row['reverse']);
            $targets = array_column($row['targets'], 1);
            foreach ($stream as $b) {
                $after = $b['at'] > $switch['at'];
                expect($b['target'])->toBe($after ? ! in_array($b['name'], $targets, true) : in_array($b['name'], $targets, true));
            }
            expect($stream->filter(fn ($b) => $b['at'] > $switch['at'])->where('target', true)->count())->toBeGreaterThan(0);

            continue;
        }

        expect($row['kind'])->toBe([1 => 'visual', 2 => 'category', 3 => 'sound'][$level])
            ->and($data['mode'])->toBe($level === 3 ? 'hear' : 'see');
        foreach ($stream as $b) {
            expect($b['target'])->toBe(in_array($b['name'], array_column($row['targets'], 1), true))
                ->and($b['target'] || in_array($b['name'], array_column($row['others'], 1), true))->toBeTrue();
            if ($level === 3) {
                expect(K::hasSound($b['name'], $row['sound']))->toBe($b['target'])
                    ->and($b['why'])->toContain($b['target'] ? "van {$row['sound']} hang" : "nincs {$row['sound']} hang");
            }
        }
        if ($level === 3) {
            // every word of a listening round is said only once
            expect($stream->pluck('name')->duplicates()->all())->toBe([]);
        }
    }
})->with([1, 2, 3]);

it('hears sounds digraph-aware', function () {
    expect(K::hasSound('szék', 's'))->toBeFalse()
        ->and(K::hasSound('szék', 'sz'))->toBeTrue()
        ->and(K::hasSound('asszony', 'sz'))->toBeTrue()
        ->and(K::hasSound('kés', 's'))->toBeTrue()
        ->and(K::hasSound('rózsa', 'z'))->toBeFalse()
        ->and(K::hasSound('gólya', 'l'))->toBeFalse()
        ->and(K::hasSound('pillangó', 'l'))->toBeTrue();
});

it('rejects rule rows that would teach something wrong', function (array $payload, string $field) {
    expect(ContentRules::check('kapdel', $payload))->toHaveKey($field);
})->with([
    'a target without the sound' => [['rule' => 'Kapd el, amiben r hangot hallasz!', 'kind' => 'sound', 'sound' => 'r',
        'targets' => [['🦊', 'róka'], ['🦀', 'rák'], ['🥕', 'répa'], ['🍐', 'körte'], ['🚀', 'rakéta'], ['🐯', 'tigris'], ['🐟', 'hal']],
        'others' => [['🐱', 'cica'], ['🐶', 'kutya'], ['🍌', 'banán'], ['⚽', 'labda'], ['🍎', 'alma']]], 'targets'],
    'an other with the sound' => [['rule' => 'Kapd el, amiben r hangot hallasz!', 'kind' => 'sound', 'sound' => 'r',
        'targets' => [['🦊', 'róka'], ['🦀', 'rák'], ['🥕', 'répa'], ['🍐', 'körte'], ['🚀', 'rakéta'], ['🐯', 'tigris'], ['🦓', 'zebra']],
        'others' => [['🐱', 'cica'], ['🐶', 'kutya'], ['🍌', 'banán'], ['⚽', 'labda'], ['🐭', 'egér']]], 'others'],
    'sz is not s' => [['rule' => 'Kapd el, amiben s hangot hallasz!', 'kind' => 'sound', 'sound' => 's',
        'targets' => [['🧀', 'sajt'], ['🧢', 'sapka'], ['🦅', 'sas'], ['🥚', 'tojás'], ['🔪', 'kés'], ['🍲', 'leves'], ['🪑', 'szék']],
        'others' => [['🍌', 'banán'], ['🐟', 'hal'], ['🦊', 'róka'], ['🚗', 'autó'], ['🍎', 'alma']]], 'targets'],
    'a look-alike sound in the row' => [['rule' => 'Kapd el, amiben s hangot hallasz!', 'kind' => 'sound', 'sound' => 's',
        'targets' => [['🧀', 'sajt'], ['🧢', 'sapka'], ['🦅', 'sas'], ['🥚', 'tojás'], ['🔪', 'kés'], ['🍲', 'leves'], ['🧂', 'só']],
        'others' => [['🍌', 'banán'], ['🐟', 'hal'], ['🦊', 'róka'], ['🚗', 'autó'], ['🦓', 'zebra']]], 'targets'],
    'the sound not named by its letter' => [['rule' => 'Kapd el, amiben rrr hangot hallasz!', 'kind' => 'sound', 'sound' => 'r',
        'targets' => [['🦊', 'róka'], ['🦀', 'rák'], ['🥕', 'répa'], ['🍐', 'körte'], ['🚀', 'rakéta'], ['🐯', 'tigris'], ['🦓', 'zebra']],
        'others' => [['🐱', 'cica'], ['🐶', 'kutya'], ['🍌', 'banán'], ['⚽', 'labda'], ['🍎', 'alma']]], 'rule'],
    'a picture on both sides' => [['rule' => 'Csak a gyümölcsöket kapd el!', 'kind' => 'category', 'singular' => 'gyümölcs',
        'targets' => [['🍎', 'alma'], ['🍌', 'banán'], ['🍐', 'körte'], ['🍇', 'szőlő'], ['🍓', 'eper']],
        'others' => [['🥕', 'répa'], ['🥦', 'brokkoli'], ['🌽', 'kukorica'], ['🍎', 'alma']]], 'others'],
    'two kinds to catch at level 1' => [['rule' => 'Kapd el a katicákat!', 'kind' => 'visual',
        'targets' => [['🐞', 'katica'], ['🦋', 'pillangó']], 'others' => [['🐝', 'méhecske']]], 'targets'],
    'a category without its name' => [['rule' => 'Csak a gyümölcsöket kapd el!', 'kind' => 'category',
        'targets' => [['🍎', 'alma'], ['🍌', 'banán'], ['🍐', 'körte'], ['🍇', 'szőlő'], ['🍓', 'eper']],
        'others' => [['🥕', 'répa'], ['🥦', 'brokkoli'], ['🌽', 'kukorica'], ['🥔', 'krumpli']]], 'singular'],
]);
