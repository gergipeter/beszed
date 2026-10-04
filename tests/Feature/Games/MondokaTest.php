<?php

use App\Beszed\Content\ContentRules;
use App\Beszed\Content\Hungarian;
use App\Beszed\Rounds\MondokaRounds;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Mondókázz!: Csillám says the whole rhyme, then again up to the missing word; its picture is among the options,
// no wrong picture is a word of the same rhyme, the full line is said after a right answer, and at level 3 a
// rhyme with a second gap comes twice in a row.

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function mondokaSession(int $level): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=mondoka&level=$level")
        ->assertOk()
        ->json();
}

it('has at least 24 rhymes at every level, with each gap word in its rhyme', function () {
    $items = BeszedContentItem::forGame('mondoka')->get();

    expect($items->count())->toBeGreaterThanOrEqual(24)
        ->and($items->where('level', 1)->count())->toBeGreaterThanOrEqual(10)
        ->and($items->where('level', 3)->filter(fn ($i) => ! empty($i->payload['gap2']))->count())->toBeGreaterThanOrEqual(3);
    foreach ($items as $item) {
        $p = $item->payload;
        expect(MondokaRounds::locate($p['lines'], $p['gap']))->not->toBeNull($p['title']);
        // the short rhymes are the first level
        if ($item->level === 1) {
            expect(count($p['lines']))->toBeLessThanOrEqual(5, $p['title']);
        }
    }
});

it('says the rhyme, stops before the missing word and offers its picture', function (int $level) {
    $items = BeszedContentItem::forGame('mondoka')->get()->keyBy('id');

    foreach (range(1, 8) as $i) {
        $rounds = mondokaSession($level)['rounds'];
        expect($rounds)->toHaveCount(config('beszed.games.mondoka.rounds'));

        foreach ($rounds as $r => $round) {
            $p = $items[$round['content_item_id']]->payload;
            $data = $round['data'];
            $parts = $round['prompt']['parts'];
            $again = $r > 0 && $rounds[$r - 1]['content_item_id'] === $round['content_item_id'];
            [$gap, $emoji] = $again ? [$p['gap2'], $p['emoji2']] : [$p['gap'], $p['emoji']];
            [$line, $before] = MondokaRounds::locate($p['lines'], $gap);
            $answer = collect($data['options'])->firstWhere('id', $data['answer']);

            expect($data['options'])->toHaveCount($level >= 3 ? 4 : 3)
                ->and(preg_replace('/^arasaac:\d+~/', '', $answer['emoji']))->toBe($emoji)
                ->and(array_unique(array_column($data['options'], 'emoji')))->toHaveCount(count($data['options']))
                // the full line after a right answer
                ->and(mb_strtolower($data['onCorrect']))->toContain(mb_strtolower(rtrim($p['lines'][$line], ' ,;:!?.')))
                // TTS would read "…" out loud: never in what Csillám says
                ->and(implode(' ', $parts).$data['onCorrect'].implode(' ', $data['onWrong']))->not->toContain('…');
            // the whole rhyme first (not when the same rhyme comes again), then up to the gap, never the gap itself
            if (! $again) {
                expect($parts)->toContain(...$p['lines']);
            }
            $lead = array_slice($parts, -2)[0];
            expect($lead)->toBe(trim($before) !== '' ? trim($before) : $p['lines'][$line - 1]);
            // no wrong picture is a word of this rhyme
            $text = Hungarian::fold(implode(' ', $p['lines']));
            foreach ($data['options'] as $option) {
                if ($option['id'] !== $data['answer']) {
                    expect(str_contains($text, Hungarian::fold($option['label'])))->toBeFalse("{$p['title']}: {$option['label']}");
                }
            }
            if ($level < 3) {
                expect($again)->toBeFalse();
            }
        }
    }
})->with([1, 2, 3]);

it('asks a level-3 rhyme twice, the second time with its other gap', function () {
    $seen = 0;
    foreach (range(1, 12) as $i) {
        $rounds = mondokaSession(3)['rounds'];
        foreach ($rounds as $r => $round) {
            if ($r > 0 && $rounds[$r - 1]['content_item_id'] === $round['content_item_id']) {
                $seen++;
                expect($round['prompt']['parts'][0])->toContain('másik szó');
            }
        }
    }
    expect($seen)->toBeGreaterThan(0);
});

it('rejects a gap word that is not in the rhyme', function () {
    $rhyme = ['title' => 'Csiga-biga, gyere ki', 'lines' => ['Csiga-biga, gyere ki,', 'ég a házad ideki.'], 'name' => 'ház', 'emoji' => '🏠'];

    expect(ContentRules::check('mondoka', $rhyme + ['gap' => 'kertje']))->toHaveKey('gap')
        ->and(ContentRules::check('mondoka', $rhyme + ['gap' => 'Csiga-biga']))->toHaveKey('gap')
        ->and(ContentRules::check('mondoka', $rhyme + ['gap' => 'házad']))->toBe([]);
});
