<?php

use App\Beszed\Content\ContentRules;
use App\Beszed\Rounds\IgekRounds;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Mozgó szavak: the asked verb's picture is among the options and no other picture also does it (animal voices
// only with animals, close verbs never together); level 2 asks "Mit csinál?" with spoken verbs; level 3 picks the
// right én/te/mi/ők form, exactly as the content wrote it (ikes verbs: eszem, úszom).

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
    $this->verbs = BeszedContentItem::forGame('igek')->get()->keyBy('id');
});

function igekSession(int $level): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=igek&level=$level")
        ->assertOk()
        ->json('rounds');
}

/** The emoji a round shows ("arasaac:12~🏃" keeps the emoji after "~"). */
function igekEmoji(string $e): string
{
    return str_contains($e, '~') ? explode('~', $e, 2)[1] : $e;
}

/** No other picture of a picture round can also be the answer. */
function igekCheckPictures(array $round, $verbs): void
{
    $answer = $verbs[(int) $round['data']['answer']]->payload;
    expect($round['prompt']['text'])->toEndWith($answer['question']);
    foreach ($round['data']['options'] as $o) {
        $other = $verbs[(int) $o['id']]->payload;
        expect(igekEmoji($o['emoji']))->toBe($other['emoji']);
        if ($o['id'] === $round['data']['answer']) {
            continue;
        }
        expect($other['emoji'])->not->toBe($answer['emoji'])
            ->and($other['group'] === 'allat')->toBe($answer['group'] === 'allat')
            ->and($answer['close'] ?? [])->not->toContain($other['verb'])
            ->and($other['close'] ?? [])->not->toContain($answer['verb']);
    }
}

it('has deep content: 50+ verbs, each with its own picture, most with checked forms', function () {
    $verbs = $this->verbs->map(fn ($i) => $i->payload);
    $names = $verbs->pluck('verb');

    expect($verbs->count())->toBeGreaterThanOrEqual(50)
        ->and($verbs->filter(fn ($p) => count($p['forms'] ?? []) === 4)->count())->toBeGreaterThanOrEqual(40)
        ->and($verbs->pluck('emoji')->duplicates()->all())->toBe([])
        ->and($this->verbs->where('level', 1)->count())->toBeGreaterThanOrEqual(12);
    foreach ($verbs as $p) {
        foreach ($p['close'] ?? [] as $close) {
            expect($names)->toContain($close);
        }
    }
});

it('conjugates exactly, ikes verbs too', function () {
    $forms = $this->verbs->mapWithKeys(fn ($i) => [$i->payload['verb'] => $i->payload['forms'] ?? null]);

    expect($forms['eszik'])->toBe(['eszem', 'eszel', 'eszünk', 'esznek'])
        ->and($forms['alszik'])->toBe(['alszom', 'alszol', 'alszunk', 'alszanak'])
        ->and($forms['úszik'])->toBe(['úszom', 'úszol', 'úszunk', 'úsznak'])
        ->and($forms['fürdik'])->toBe(['fürdöm', 'fürdesz', 'fürdünk', 'fürdenek'])
        ->and($forms['fut'])->toBe(['futok', 'futsz', 'futunk', 'futnak'])
        ->and($forms['főz'])->toBe(['főzök', 'főzöl', 'főzünk', 'főznek'])
        ->and($forms['ásít'])->toBe(['ásítok', 'ásítasz', 'ásítunk', 'ásítanak']);

    // the content rules catch the colloquial "eszek" and a mixed-up order
    expect(ContentRules::check('igek', ['verb' => 'eszik', 'emoji' => '😋', 'group' => 'arc', 'question' => 'Ki eszik?', 'forms' => ['eszek', 'eszel', 'eszünk', 'esznek']]))->toHaveKey('forms')
        ->and(ContentRules::check('igek', ['verb' => 'fut', 'emoji' => '🏃', 'group' => 'mozgas', 'question' => 'Ki fut?', 'forms' => ['futsz', 'futok', 'futunk', 'futnak']]))->toHaveKey('forms');
});

it('level 1: three pictures, the answer among them, no other picture also fits', function () {
    foreach (range(1, 6) as $_) {
        foreach (igekSession(1) as $round) {
            expect($round['engine'])->toBe('choice')
                ->and($round['data']['options'])->toHaveCount(3)
                ->and($round['data']['variant'] ?? null)->toBeNull();
            igekCheckPictures($round, $this->verbs);
        }
    }
});

it('level 2: four pictures, and "Mit csinál?" with three spoken verbs', function () {
    $spoken = 0;
    foreach (range(1, 4) as $_) {
        // level 66: the last level of tier 2 (34-66, "Mit csinál?" unlocked), where whoOptions = scaleInt(level,3,4) also reaches 4
        foreach (igekSession(66) as $round) {
            if (($round['data']['variant'] ?? null) !== 'speakers') {
                expect($round['data']['options'])->toHaveCount(4);
                igekCheckPictures($round, $this->verbs);

                continue;
            }
            $spoken++;
            $answer = $this->verbs[(int) $round['data']['answer']]->payload;
            $labels = collect($round['data']['options'])->pluck('label');
            expect($round['prompt']['text'])->toContain('Mit csinál')
                ->and(igekEmoji($round['data']['stimulus']['emoji']))->toBe($answer['emoji'])
                ->and($labels)->toHaveCount(3)->and($labels->unique())->toHaveCount(3)
                ->and(collect($round['data']['options'])->firstWhere('id', $round['data']['answer'])['label'])->toBe($answer['verb']);
            foreach ($round['data']['options'] as $o) {
                if ($o['id'] !== $round['data']['answer']) {
                    expect($answer['close'] ?? [])->not->toContain($o['label']);
                }
            }
        }
    }
    expect($spoken)->toBeGreaterThan(0);
});

it('level 3: the form that belongs to the person, among forms of the same verb', function () {
    foreach (range(1, 4) as $_) {
        foreach (igekSession(100) as $round) { // tier 3: levels 67-100
            if (($round['data']['variant'] ?? null) !== 'speakers') {
                igekCheckPictures($round, $this->verbs);

                continue;
            }
            $item = $this->verbs[$round['content_item_id']]->payload;
            $forms = array_combine(array_keys(IgekRounds::PERSONS), $item['forms']);
            $person = $round['data']['answer'];
            $right = collect($round['data']['options'])->firstWhere('id', $person);

            expect($round['prompt']['text'])->toEndWith(IgekRounds::PERSONS[$person].' is …')
                ->and($right['label'])->toBe($forms[$person])
                ->and($right['say'])->toBe(IgekRounds::PERSONS[$person]." is {$forms[$person]}.")
                ->and($round['data']['onCorrect'])->toBe('Igen! '.IgekRounds::PERSONS[$person]." is {$forms[$person]}.");
            foreach ($round['data']['options'] as $o) {
                expect($o['label'])->toBe($forms[$o['id']]);
            }
            expect(collect($round['data']['options'])->pluck('label')->unique())->toHaveCount(3);
        }
    }
});
