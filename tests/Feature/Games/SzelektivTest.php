<?php

use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Szelektív gyűjtés (sort engine, ValogatoRounds): the Hungarian household bins exist with their colours, every
// picture belongs to exactly one bin, and every round sorts pictures into the bin they really belong to.

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function szelektivBins(): array
{
    return collect(json_decode(file_get_contents(database_path('seeders/data/beszed/szelektiv.json')), true, flags: JSON_THROW_ON_ERROR))
        ->mapWithKeys(fn ($row) => [$row['payload']['key'] => $row['payload']])->all();
}

it('has the household bins with their colours', function () {
    $bins = szelektivBins();

    expect(array_keys($bins))->toEqualCanonicalizing(['papir', 'sarga', 'uveg', 'bio'])
        ->and($bins['papir']['label'])->toContain('kék')
        ->and($bins['sarga']['label'])->toContain('sárga')->toContain('Műanyag és fém')
        ->and($bins['uveg']['label'])->toContain('zöld')
        ->and($bins['bio']['label'])->toContain('barna')
        ->and(collect($bins)->pluck('icon')->all())->toEqualCanonicalizing(['🟦', '🟨', '🟩', '🟫']);
});

it('keeps every picture and every name to one bin', function () {
    $items = collect(szelektivBins())->flatMap(fn ($bin) => $bin['items']);

    expect($items->pluck(0)->duplicates()->all())->toBe([])
        ->and($items->pluck(1)->duplicates()->all())->toBe([])
        // a bin colour is never also a picture to sort
        ->and($items->pluck(0)->intersect(['🟦', '🟨', '🟩', '🟫'])->all())->toBe([]);
});

it('has depth where Twemoji has clear pictures', function () {
    $bins = szelektivBins();

    expect(count($bins['papir']['items']))->toBeGreaterThanOrEqual(8)
        ->and(count($bins['bio']['items']))->toBeGreaterThanOrEqual(8)
        // only a few unmistakable plastic, metal and glass packaging pictures exist
        ->and(count($bins['sarga']['items']))->toBeGreaterThanOrEqual(4)
        ->and(count($bins['uveg']['items']))->toBeGreaterThanOrEqual(2);
});

it('sorts every picture into the bin it belongs to, with a correct wrong-bin sentence', function (int $level) {
    $bins = szelektivBins();
    // by name: with pictograms on, the session swaps an emoji for "arasaac:<id>~<emoji>"
    $binOf = collect($bins)->flatMap(fn ($bin, $key) => collect($bin['items'])->mapWithKeys(fn ($i) => [$i[1] => $key]));

    $session = actingAs($this->user)
        ->getJson("/api/beszed/children/{$this->child->id}/session?game=szelektiv&level=$level")
        ->assertOk()->json();

    expect($session['rounds'])->toHaveCount(3);
    foreach ($session['rounds'] as $round) {
        expect($round['engine'])->toBe('sort')
            ->and($round['prompt']['text'])->toContain('kuk');
        $binIds = collect($round['data']['bins'])->pluck('id');
        expect($binIds->unique())->toHaveCount(2);

        foreach ($round['data']['items'] as $item) {
            expect($item['bin'])->toBe($binOf[$item['label']])
                ->and($binIds)->toContain($item['bin']);
            $other = $binIds->first(fn ($id) => $id !== $item['bin']);
            expect($item['wrong'])->toContain("{$item['label']} nem {$bins[$other]['singular']}.");
        }
    }
})->with([1, 2, 3]);
