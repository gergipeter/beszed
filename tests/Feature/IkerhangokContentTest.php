<?php

use App\Beszed\Content\ContentRules;
use App\Beszed\Content\Hungarian;
use App\Models\BeszedContentItem;
use App\Models\BeszedSkillLevel;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

/** Ikerhangok's seed rows: [level, payload]. */
function ikerRows(): array
{
    return json_decode(file_get_contents(database_path('seeders/data/beszed/ikerhangok.json')), true, flags: JSON_THROW_ON_ERROR);
}

/** "s – sz": two sounds in alphabetical order, a long vowel next to its short one ("a – á"), as the sound report writes them. */
function ikerLabel(string $a, string $b): string
{
    $sides = [mb_strtolower($a), mb_strtolower($b)];
    usort($sides, fn ($x, $y) => [Hungarian::fold($x), $x] <=> [Hungarian::fold($y), $y]);

    return implode(' – ', $sides);
}

/** A session of a game for the child of the test (set up in beforeEach). */
function ikerSession(string $game = 'ikerhangok'): array
{
    return actingAs(test()->user)->getJson('/api/beszed/children/'.test()->child->id."/session?game=$game")->assertOk()->json();
}

it('ikerhangok: every pair is a graded, labelled pair of two different words with two different pictures', function () {
    $max = config('beszed.games.ikerhangok.adaptive.max');

    foreach (ikerRows() as $i => $row) {
        $p = $row['payload'];
        $name = "#$i {$p['wordA']} / {$p['wordB']}";

        expect($row['level'])->toBeInt()->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual($max, $name)
            ->and(array_keys($p))->toBe(['wordA', 'emojiA', 'wordB', 'emojiB', 'contrast'], $name)
            ->and($p['contrast'])->toBeString()->not->toBeEmpty($name)
            ->and(preg_match('/^\S+ – \S+$/u', $p['contrast']))->toBe(1, $name)
            ->and(mb_strtolower($p['wordA']))->not->toBe(mb_strtolower($p['wordB']), $name)
            ->and($p['emojiA'])->not->toBe($p['emojiB'], $name)
            ->and(ContentRules::check('ikerhangok', $p))->toBe([], $name);
    }
});

it('ikerhangok: the two words differ in one sound only, and the contrast names it', function () {
    foreach (ikerRows() as $i => $row) {
        $p = $row['payload'];
        $a = array_column(Hungarian::letters($p['wordA']), 'letter');
        $b = array_column(Hungarian::letters($p['wordB']), 'letter');
        $name = "#$i {$p['wordA']} / {$p['wordB']}";

        expect($a)->toHaveCount(count($b), $name);
        $labels = collect(array_keys(array_diff_assoc($a, $b)))->map(fn ($at) => ikerLabel($a[$at], $b[$at]))->unique()->values()->all();
        // one place (kés / kész), or the same two sounds twice (teve / tévé)
        expect($labels)->toBe([$p['contrast']], $name);
    }
});

it('ikerhangok: no word, no picture and no pair twice (a borrowed third option must never double an option)', function () {
    $words = collect(ikerRows())->flatMap(fn ($r) => [mb_strtolower($r['payload']['wordA']), mb_strtolower($r['payload']['wordB'])]);
    $pictures = collect(ikerRows())->flatMap(fn ($r) => [$r['payload']['emojiA'], $r['payload']['emojiB']]);
    $pairs = collect(ikerRows())->map(fn ($r) => collect([$r['payload']['wordA'], $r['payload']['wordB']])->map(fn ($w) => mb_strtolower($w))->sort()->implode('|'));

    expect($words->duplicates()->all())->toBe([])
        ->and($pictures->duplicates()->all())->toBe([])
        ->and($pairs->duplicates()->all())->toBe([]);
});

it('ikerhangok: enough pairs at every level for a session, and the Hungarian contrasts are all there', function () {
    $rows = collect(ikerRows());
    $rounds = config('beszed.games.ikerhangok.rounds');

    foreach ([1, 2, 3] as $level) {
        expect($rows->where('level', $level)->count())->toBeGreaterThanOrEqual($rounds + 2, "level $level");
    }
    $contrasts = $rows->countBy('payload.contrast');
    foreach (['s – sz' => 4, 's – zs' => 1, 'c – cs' => 1, 'a – á' => 3, 'e – é' => 2, 'u – ú' => 1] as $contrast => $min) {
        expect($contrasts[$contrast] ?? 0)->toBeGreaterThanOrEqual($min, $contrast);
    }
    // the fine contrasts are the top grade
    expect($rows->whereIn('payload.contrast', ['s – sz', 's – zs', 'c – cs', 'a – á', 'e – é', 'u – ú'])->pluck('level')->unique()->all())->toBe([3]);
});

it('catches a twin pair that is no pair', function (array $payload, string $field) {
    expect(ContentRules::check('ikerhangok', $payload))->toHaveKey($field);
})->with([
    'same word twice' => [['wordA' => 'só', 'emojiA' => '🧂', 'wordB' => 'Só', 'emojiB' => '💬'], 'wordB'],
    'same picture twice' => [['wordA' => 'só', 'emojiA' => '🧂', 'wordB' => 'szó', 'emojiB' => '🧂'], 'emojiB'],
]);

describe('in a session', function () {
    beforeEach(function () {
        seed(BeszedContentSeeder::class);
        $this->user = User::factory()->create();
        $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
    });

    it('shows two options below the top level and a third, from another pair, at the top', function (int $level, int $options) {
        BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => 'ikerhangok', 'level' => $level]);
        $pairOf = BeszedContentItem::forGame('ikerhangok')->get()->keyBy('id')->map(fn ($i) => [$i->payload['wordA'], $i->payload['wordB']]);

        foreach (ikerSession()['rounds'] as $round) {
            $labels = collect($round['data']['options'])->pluck('label');
            $answer = collect($round['data']['options'])->firstWhere('id', $round['data']['answer']);

            expect($round['data']['options'])->toHaveCount($options)
                ->and($labels->unique())->toHaveCount($options)
                ->and(collect($round['data']['options'])->pluck('emoji')->unique())->toHaveCount($options)
                ->and($labels->all())->toContain(...$pairOf[$round['content_item_id']])
                ->and($answer['label'])->toBe($round['prompt']['text']);
        }
    })->with([[1, 2], [2, 2], [3, 3]]);

    it('gives a beginner mostly the clear contrasts, rarely the fine ones', function () {
        $levels = collect(range(1, 20))->flatMap(fn () => ikerSession()['rounds'])
            ->map(fn ($r) => BeszedContentItem::find($r['content_item_id'])->level)
            ->countBy();

        expect($levels[1] ?? 0)->toBeGreaterThan($levels[2] ?? 0)
            ->and($levels[1] ?? 0)->toBeGreaterThan(($levels[3] ?? 0) * 3)
            ->and($levels[3] ?? 0)->toBeLessThan(160 / 4);
    });

    it('plays no ARASAAC picture with pictograms off, and still builds a full session at every level', function (int $level) {
        config(['beszed_content.pictograms' => false]);
        BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => 'ikerhangok', 'level' => $level]);

        $session = ikerSession();

        expect($session['rounds'])->toHaveCount(config('beszed.games.ikerhangok.rounds'))
            ->and(json_encode($session['rounds']))->not->toContain('arasaac:');
    })->with([1, 2, 3]);

    it('zs or s: half of every session is zs, half s, though there are far fewer zs words', function () {
        $zs = BeszedContentItem::forGame('zs')->get()->filter(fn ($i) => $i->payload['sound'] === 'zs');
        expect($zs->count())->toBeGreaterThanOrEqual(25)
            ->and($zs->countBy('level')->min())->toBeGreaterThanOrEqual(8);

        foreach (range(1, 10) as $_) {
            $answers = collect(ikerSession('zs')['rounds'])->countBy(fn ($r) => $r['data']['answer'])->all();
            expect($answers['zs'] ?? 0)->toBe(4)->and($answers['s'] ?? 0)->toBe(4);
        }
    });

    it('zs or s with pictograms off: still half and half', function () {
        config(['beszed_content.pictograms' => false]);

        $answers = collect(ikerSession('zs')['rounds'])->countBy(fn ($r) => $r['data']['answer'])->all();

        expect($answers['zs'] ?? 0)->toBe(4)->and($answers['s'] ?? 0)->toBe(4);
    });
});
