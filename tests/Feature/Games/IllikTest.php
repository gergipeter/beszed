<?php

use App\Beszed\Rounds\IllikRounds;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Mi illik hozzá?: the go-together or the tool is offered with wrong pictures from its own hand-picked list only,
// and never next to something that does the same job ("Mivel vágjuk a papírt?" never offers the knife beside the
// scissors); level 3 names the group, never offering a name that would also be right.

/** Pictures that can do each other's job: two of one set must never be in one round. */
const ILLIK_SAME_JOB = [
    ['✂️', '🔪', '🪚'], ['✏️', '🖍️', '🖊️', '🖌️'], ['🥄', '🍴', '🥢'], ['⏰', '📱', '⌚', '🕰️', '☎️'], ['📷', '📱', '📸'],
    ['☂️', '🌂', '🧥'], ['🧼', '🧽', '🧴'], ['⛵', '🚢', '🛶', '🚤'], ['🍳', '🥘'], ['🔨', '🪛', '🔧'], ['🔦', '🕯️', '💡'],
    ['🔭', '🔍', '🔬', '👓'], ['🐄', '🐐'], ['🦴', '🥩'], ['🐭', '🥛', '🐟', '🧶'], ['☀️', '⭐'],
];

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
    $this->rows = BeszedContentItem::forGame('illik')->get()->keyBy('id');
});

function illikSession(int $level): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=illik&level=$level")
        ->assertOk()
        ->json('rounds');
}

function illikEmoji(string $e): string
{
    return str_contains($e, '~') ? explode('~', $e, 2)[1] : $e;
}

/** @param list<string> $emojis */
function illikTwoOfOneJob(array $emojis): ?array
{
    foreach (ILLIK_SAME_JOB as $set) {
        $hit = array_values(array_intersect($set, $emojis));
        if (count($hit) > 1) {
            return $hit;
        }
    }

    return null;
}

it('has 70+ rows: pairs, tools and groups', function () {
    $rows = $this->rows->map(fn ($i) => $i->payload);

    expect($rows->count())->toBeGreaterThanOrEqual(70)
        ->and($rows->where('kind', 'pair')->count())->toBeGreaterThanOrEqual(20)
        ->and($rows->where('kind', 'function')->count())->toBeGreaterThanOrEqual(20)
        ->and($rows->where('kind', 'category')->count())->toBeGreaterThanOrEqual(20)
        ->and($rows->where('kind', 'category')->pluck('answer')->unique()->count())->toBeGreaterThanOrEqual(15);
});

it('never lets a wrong picture do the same job as the right one', function () {
    foreach ($this->rows as $row) {
        $p = $row->payload;
        if ($p['kind'] === 'category') {
            continue;
        }
        $all = array_merge([$p['answerEmoji']], $p['wrong'], array_filter([$p['emoji'] ?? null]));
        expect(illikTwoOfOneJob([$p['answerEmoji'], ...$p['wrong']]))->toBeNull($p['question'])
            ->and(count(array_unique($all)))->toBe(count($all), $p['question']);
    }

    // "Mivel vágjuk a papírt?": the scissors, never the knife beside them
    $paper = $this->rows->first(fn ($r) => $r->payload['question'] === 'Mivel vágjuk a papírt?');
    foreach (range(1, 20) as $_) {
        $round = (new IllikRounds)->build(collect([$paper]), 2, 1)[0];
        $shown = collect($round['data']['options'])->pluck('emoji');
        expect($shown)->toContain('✂️')->not->toContain('🔪')->toHaveCount(4);
    }
});

it('pairs and tools: the right picture among the row\'s own wrong ones', function (int $level) {
    foreach (range(1, 5) as $_) {
        foreach (illikSession($level) as $round) {
            $p = $this->rows[$round['content_item_id']]->payload;
            $options = collect($round['data']['options']);
            $right = $options->firstWhere('id', $round['data']['answer']);

            expect($p['kind'])->toBe($level === 1 ? 'pair' : 'function')
                ->and($round['prompt']['text'])->toBe($p['question'])
                ->and(illikEmoji($right['emoji']))->toBe($p['answerEmoji'])
                ->and($options)->toHaveCount($level === 1 ? 3 : 4)
                ->and($round['data']['onCorrect'])->toBe($p['say']);
            foreach ($options as $o) {
                if ($o['id'] !== $round['data']['answer']) {
                    expect($p['wrong'])->toContain(illikEmoji($o['emoji']));
                }
            }
            expect(illikTwoOfOneJob($options->map(fn ($o) => illikEmoji($o['emoji']))->all()))->toBeNull();
        }
    }
})->with([1, 2]);

it('groups: the pictures shown together, three names said, only one of them right', function () {
    foreach (range(1, 5) as $_) {
        foreach (illikSession(3) as $round) {
            $p = $this->rows[$round['content_item_id']]->payload;
            $labels = collect($round['data']['options'])->pluck('label');

            expect($p['kind'])->toBe('category')
                ->and($round['data']['variant'])->toBe('speakers')
                ->and(collect($round['data']['sequence'])->map(fn ($e) => illikEmoji($e))->all())->toBe(IllikRounds::emojis($p['emoji']))
                ->and($labels)->toHaveCount(3)->and($labels->unique())->toHaveCount(3)
                ->and(collect($round['data']['options'])->firstWhere('id', $round['data']['answer'])['label'])->toBe($p['answer'])
                ->and($round['prompt']['text'])->toEndWith($p['question']);
            foreach ($labels as $label) {
                expect($p['close'] ?? [])->not->toContain($label);
            }
        }
    }
});
