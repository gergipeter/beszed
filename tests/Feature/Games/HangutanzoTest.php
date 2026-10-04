<?php

use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Ki mondja?: the one who makes the sound is among the pictures, and no other option makes the same sound or one
// the content marks as close (ló / szamár, nevetés / Mikulás); level 2 also asks the other way round with spoken
// sounds; level 3 plays things and people. The sounds are the standard Hungarian ones.

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
    $this->items = BeszedContentItem::forGame('hangutanzo')->get()->keyBy('id');
});

function hangutanzoSession(int $level): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=hangutanzo&level=$level")
        ->assertOk()
        ->json('rounds');
}

function hangutanzoEmoji(string $e): string
{
    return str_contains($e, '~') ? explode('~', $e, 2)[1] : $e;
}

/** Two options that must never stand side by side. */
function hangutanzoClash(array $a, array $b): bool
{
    return $a['sound'] === $b['sound'] || in_array($a['name'], $b['close'] ?? [], true) || in_array($b['name'], $a['close'] ?? [], true);
}

it('has 40+ items with standard sounds, every sound once, every close name real', function () {
    $items = $this->items->map(fn ($i) => $i->payload);
    $sound = $items->mapWithKeys(fn ($p) => [$p['name'] => $p['sound']]);

    expect($items->count())->toBeGreaterThanOrEqual(40)
        ->and($items->pluck('sound')->duplicates()->all())->toBe([])
        ->and($items->pluck('emoji')->duplicates()->all())->toBe([])
        ->and($sound->only(['kutya', 'cica', 'tehén', 'ló', 'malac', 'kacsa', 'liba', 'tyúk', 'kakas', 'kecske', 'bárány', 'béka', 'bagoly', 'egér'])->all())
        ->toEqual(['kutya' => 'vau-vau', 'cica' => 'miaú', 'tehén' => 'mú', 'ló' => 'nyihaha', 'malac' => 'röf-röf', 'kacsa' => 'háp-háp', 'liba' => 'gá-gá',
            'tyúk' => 'kotkodács', 'kakas' => 'kukurikú', 'kecske' => 'mek-mek', 'bárány' => 'bee', 'béka' => 'brekeke', 'bagoly' => 'huhu', 'egér' => 'cin-cin']);
    foreach ($items as $p) {
        foreach ($p['close'] ?? [] as $close) {
            expect($sound)->toHaveKey($close);
        }
    }
    // level 3 is things and people
    expect($this->items->where('level', 3)->every(fn ($i) => $i->payload['kind'] !== 'animal'))->toBeTrue();
});

it('asks who says it, and invites the child to say it too', function (int $level) {
    $spoken = 0;
    foreach (range(1, 5) as $_) {
        foreach (hangutanzoSession($level) as $round) {
            $answer = $this->items[$round['content_item_id']]->payload;
            $options = collect($round['data']['options']);
            expect($options->pluck('id'))->toContain($round['data']['answer'])
                ->and((string) $round['content_item_id'])->toBe($round['data']['answer']);
            foreach ($options as $o) {
                if ($o['id'] !== $round['data']['answer']) {
                    expect(hangutanzoClash($answer, $this->items[(int) $o['id']]->payload))->toBeFalse();
                }
            }

            if (($round['data']['variant'] ?? null) === 'speakers') {
                $spoken++;
                expect($level)->toBeGreaterThanOrEqual(2)
                    ->and(hangutanzoEmoji($round['data']['stimulus']['emoji']))->toBe($answer['emoji'])
                    ->and($options->firstWhere('id', $round['data']['answer'])['label'])->toBe($answer['sound'])
                    ->and($options)->toHaveCount(3)
                    ->and($round['data']['onCorrect'])->toContain($answer['sound']);
            } else {
                expect($round['prompt']['text'])->toEndWith("hogy {$answer['sound']}?")
                    ->and($options)->toHaveCount($level >= 3 ? 4 : 3)
                    ->and($round['data']['onCorrect'])->toEndWith("Mondd te is: {$answer['sound']}!");
                foreach ($options as $o) {
                    expect(hangutanzoEmoji($o['emoji']))->toBe($this->items[(int) $o['id']]->payload['emoji']);
                }
            }
            if ($level === 1) {
                expect($answer['kind'])->toBe('animal');
            }
            if ($level === 3) {
                expect($answer['kind'])->not->toBe('animal');
            }
        }
    }
    expect($spoken > 0)->toBe($level >= 2);
})->with([1, 2, 3]);

it('says the questions in good Hungarian', function () {
    $prompts = collect(range(1, 4))->flatMap(fn () => hangutanzoSession(3))->pluck('prompt.text')->implode(' | ');

    expect($prompts)->not->toContain('az aki')->not->toContain('a aki')->not->toMatch('/\ba [aáeéiíoóöőuúüű]/u');
});
