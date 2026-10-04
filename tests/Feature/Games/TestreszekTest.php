<?php

use App\Beszed\Rounds\TestreszekRounds;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Testrészek: the right picture is offered, nothing that would also be right ("Mivel harapjuk meg az almát?" never
// offers the mouth next to the teeth, "Melyik a kéz?" never the pointing finger); level 1 names, level 2 what
// the parts do, level 3 caring for the body with the body part shown above.

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
    $this->rows = BeszedContentItem::forGame('testreszek')->get()->keyBy('id');
});

function testreszekSession(int $level): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=testreszek&level=$level")
        ->assertOk()
        ->json('rounds');
}

function testreszekEmoji(string $e): string
{
    return str_contains($e, '~') ? explode('~', $e, 2)[1] : $e;
}

it('has 40+ rows of every kind, one name per picture', function () {
    $rows = $this->rows->map(fn ($i) => $i->payload);

    expect($rows->count())->toBeGreaterThanOrEqual(40)
        ->and($rows->where('kind', 'name')->count())->toBeGreaterThanOrEqual(10)
        ->and($rows->where('kind', 'function')->count())->toBeGreaterThanOrEqual(15)
        ->and($rows->where('kind', 'care')->count())->toBeGreaterThanOrEqual(15);
    // a picture means one thing everywhere (👀 is always "szem")
    foreach ($rows->groupBy('emoji') as $emoji => $same) {
        expect($same->pluck('name')->unique()->values()->all())->toHaveCount(1, "$emoji has two names");
    }
    // the hand pictures never stand in for each other
    $kez = $rows->firstWhere('question', 'Melyik a kéz?');
    expect($kez['close'])->toContain('👆')->toContain('💪');
    // the brain's pictogram is a whole head with a face: never offered when the answer is a part of the face
    foreach ($rows->whereIn('emoji', ['👂', '👃', '👀', '👄', '👅', '🦷']) as $p) {
        expect($p['close'] ?? [])->toContain('🧠');
    }
});

it('offers the right picture and nothing that would also be right', function (int $level) {
    [$body, $things] = TestreszekRounds::pictures($this->rows->values());
    foreach (range(1, 5) as $_) {
        foreach (testreszekSession($level) as $round) {
            $row = $this->rows[$round['content_item_id']];
            $p = $row->payload;
            $shown = collect($round['data']['options'])->map(fn ($o) => testreszekEmoji($o['emoji']));
            $right = collect($round['data']['options'])->firstWhere('id', $round['data']['answer']);
            $names = isset($body[$p['emoji']]) ? $body : $things;

            expect($row->level)->toBe($level)
                ->and($round['prompt']['text'])->toBe($p['question'])
                ->and(testreszekEmoji($right['emoji']))->toBe($p['emoji'])
                ->and($shown)->toHaveCount($level === 2 ? 4 : 3)
                ->and($shown->unique())->toHaveCount($shown->count())
                ->and($round['data']['onCorrect'])->toBe($p['say']);
            foreach ($shown as $e) {
                // every option is a body part for a body-part answer, a thing for a thing answer
                expect($names)->toHaveKey($e);
                if ($e !== $p['emoji']) {
                    expect($p['close'] ?? [])->not->toContain($e)
                        ->and($names[$e])->not->toBe($p['name']);
                }
            }
            if (isset($p['part'])) {
                expect(testreszekEmoji($round['data']['stimulus']['emoji']))->toBe($p['part']);
            }
            match ($level) {
                1 => expect($p['kind'])->toBe('name'),
                3 => expect($p['kind'])->not->toBe('name'),
                default => null,
            };
        }
    }
})->with([1, 2, 3]);
