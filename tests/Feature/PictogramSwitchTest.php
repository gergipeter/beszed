<?php

use App\Beszed\Content\ContentRules;
use App\Beszed\Content\Pictures;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

// BESZED_PICTOGRAMS=false: the app plays and shows nothing from ARASAAC (CC BY-NC-SA, non-commercial), so
// it can be sold without ARASAAC's agreement. Pictograms with a Mulberry symbol (CC BY-SA 4.0) are shown as
// that symbol; items with a pictogram that has none are left out. See docs/pictogram-licensing.md.

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function sessionJson(string $game): string
{
    return actingAs(test()->user)->get('/api/beszed/children/'.test()->child->id."/session?game=$game")->assertOk()->getContent();
}

it('has content that shows pictograms, so the switch matters', function () {
    expect(BeszedContentItem::forGame('kezdo')->get()->filter(fn ($i) => $i->arasaacIds() !== []))->not->toBeEmpty();
});

it('plays nothing from ARASAAC with pictograms off, and still builds full sessions', function (string $game) {
    config(['beszed_content.pictograms' => false]);

    $session = json_decode(sessionJson($game), true);

    expect(json_encode($session['rounds']))->not->toContain('arasaac:')
        ->and($session['rounds'])->toHaveCount(config("beszed.games.$game.rounds"));
})->with(['arnyek', 'kezdo', 'kirako', 'papagaj', 'parkereso', 'rimelo', 'szotag', 'zs']);

it('swaps a pictogram for its Mulberry symbol, keeping the emoji fallback', function () {
    config(['beszed_content.pictograms' => false]);
    $symbols = Pictures::symbols();
    $id = array_key_first($symbols);
    $name = $symbols[$id];

    expect(Pictures::apply([['a' => "arasaac:$id", 'b' => "arasaac:$id~🍎", 'c' => '🍎', 'd' => 'arasaac:999999']]))
        ->toBe([['a' => "mulberry:$name", 'b' => "mulberry:$name~🍎", 'c' => '🍎', 'd' => 'arasaac:999999']]);
});

it('keeps items whose pictograms all have a symbol, and drops the others', function () {
    config(['beszed_content.pictograms' => false]);
    $mapped = array_key_first(Pictures::symbols());

    expect(Pictures::hasSymbols([]))->toBeTrue()
        ->and(Pictures::hasSymbols([$mapped]))->toBeTrue()
        ->and(Pictures::hasSymbols([$mapped, 999999]))->toBeFalse();

    $kept = BeszedContentItem::forGame('kezdo')->get()->filter(fn ($i) => Pictures::hasSymbols($i->arasaacIds()));
    expect($kept->filter(fn ($i) => $i->arasaacIds() !== []))->not->toBeEmpty(); // symbol-backed items still play
});

it('has an SVG for every symbol it maps to', function () {
    foreach (array_unique(Pictures::symbols()) as $name) {
        expect(public_path("symbols/$name.svg"))->toBeFile();
    }
    expect(public_path('symbols/LICENSE.txt'))->toBeFile(); // Mulberry's licence travels with the files
});

it('serves no pictogram and fetches nothing from ARASAAC with pictograms off', function () {
    config(['beszed_content.pictograms' => false]);
    Http::fake();

    get('/pictograms/2595.png')->assertNotFound();

    Http::assertNothingSent();
});

it('does not accept new pictograms in the content editor with pictograms off', function () {
    expect(ContentRules::isPicture('arasaac:2595'))->toBeTrue(); // on by default
    expect(ContentRules::isPicture('🍎'))->toBeTrue();

    config(['beszed_content.pictograms' => false]);

    expect(ContentRules::isPicture('arasaac:2595'))->toBeFalse()
        ->and(ContentRules::isPicture('🍎'))->toBeTrue();
});
