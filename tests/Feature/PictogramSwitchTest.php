<?php

use App\Beszed\Content\ContentRules;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

// BESZED_PICTOGRAMS=false: the app plays and shows nothing from ARASAAC (CC BY-NC-SA, non-commercial),
// so it can be sold without ARASAAC's agreement. See docs/pictogram-licensing.md.

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function sessionJson(string $game): string
{
    return actingAs(test()->user)->get('/api/beszed/children/'.test()->child->id."/session?game=$game")->assertOk()->getContent();
}

it('has content that depends on pictograms, so the switch matters', function () {
    expect(BeszedContentItem::forGame('kezdo')->get()->filter->needsPictogram())->not->toBeEmpty();
});

it('plays no pictogram-only content with pictograms off, and still builds full sessions', function (string $game) {
    config(['beszed_content.pictograms' => false]);

    $session = json_decode(sessionJson($game), true);

    expect(json_encode($session['rounds']))->not->toContain('arasaac:')
        ->and($session['rounds'])->toHaveCount(config("beszed.games.$game.rounds"));
})->with(['arnyek', 'kezdo', 'kirako', 'papagaj', 'parkereso', 'rimelo', 'szotag', 'zs']);

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
