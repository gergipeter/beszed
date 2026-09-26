<?php

use App\Beszed\Content\ContentRules;
use App\Beszed\Content\Pictures;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

const PNG = "\x89PNG\r\n\x1a\nfake-pictogram";

it('swaps an emoji for its pictogram, keeping the emoji as the fallback', function () {
    $alma = collect(json_decode(file_get_contents(database_path('lexicon/hu.json')), true))->firstWhere('w', 'alma');

    $rounds = Pictures::apply([['data' => ['stimulus' => ['emoji' => '🍎'], 'label' => 'alma', 'emojis' => ['🍎', '🔴']]]]);

    expect($rounds[0]['data']['stimulus']['emoji'])->toBe("arasaac:{$alma['p']}~🍎")
        ->and($rounds[0]['data']['emojis'][1])->toBe('🔴') // no pictogram for it: stays
        ->and($rounds[0]['data']['label'])->toBe('alma');

    config(['beszed_content.pictograms' => false]);
    expect(Pictures::apply([['e' => '🍎']])[0]['e'])->toBe('🍎');
});

it('serves pictograms in a game session', function () {
    seed(BeszedContentSeeder::class);
    $user = User::factory()->create();
    $child = Child::create(['user_id' => $user->id, 'name' => 'Zoé']);

    // Papagáj shows 30+ pictures per session (Kirakó only 3, which could all be emoji-only words).
    $json = json_encode(actingAs($user)->getJson("/api/beszed/children/{$child->id}/session?game=papagaj")->assertOk()->json('rounds'));

    expect(substr_count($json, 'arasaac:'))->toBeGreaterThan(10);
});

it('accepts a pictogram wherever content takes an emoji', function () {
    expect(ContentRules::check('papagaj', ['word' => 'hinta', 'emoji' => 'arasaac:2466']))->toBe([])
        ->and(ContentRules::check('papagaj', ['word' => 'hinta', 'emoji' => 'arasaac:hinta']))->toHaveKey('emoji');
});

it('fetches a pictogram from ARASAAC once, then serves its own copy', function () {
    Storage::fake('local');
    Http::fake(['static.arasaac.org/*' => Http::response(PNG, 200, ['Content-Type' => 'image/png'])]);

    get('/pictograms/2462.png')->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public')
        ->assertCookieMissing('laravel_session');
    get('/pictograms/2462.png')->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'https://static.arasaac.org/pictograms/2462/2462_300.png');
    expect(Storage::disk('local')->get('pictograms/2462.png'))->toBe(PNG);
});

it('refuses what is not a pictogram', function () {
    Storage::fake('local');
    Http::fake(['static.arasaac.org/*' => Http::response('<html>not found</html>', 200)]);

    get('/pictograms/999999.png')->assertNotFound();
    get('/pictograms/abc.png')->assertNotFound();
    expect(Storage::disk('local')->exists('pictograms/999999.png'))->toBeFalse();
});
