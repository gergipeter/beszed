<?php

use App\Beszed\Content\ContentRules;
use App\Models\BeszedAttempt;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

$games = array_keys((require __DIR__.'/../../config/beszed.php')['games']); // datasets load before the app boots

it('every seed item passes its game\'s rules', function (string $game) {
    $rows = json_decode(file_get_contents(database_path("seeders/data/beszed/$game.json")), true, flags: JSON_THROW_ON_ERROR);

    foreach ($rows as $i => $row) {
        expect(ContentRules::check($game, $row['payload']))->toBe([], "$game #$i ".json_encode($row['payload'], JSON_UNESCAPED_UNICODE))
            ->and($row['level'] ?? 1)->toBeIn([1, 2, 3]);
    }
    // no word twice in a game (a duplicate would make two identical cards or options)
    $field = config("beszed_content.schemas.$game.title");
    $titles = collect($rows)->map(fn ($r) => json_encode($r['payload'][$field] ?? $r['payload'], JSON_UNESCAPED_UNICODE));
    expect($titles->duplicates()->all())->toBe([]);
})->with($games);

it('catches content that would teach something wrong', function (string $game, array $payload, string $field) {
    expect(ContentRules::check($game, $payload))->toHaveKey($field);
})->with([
    'zs word without zs' => ['zs', ['word' => 'sapka', 'emoji' => '🧢', 'sound' => 'zs'], 'word'],
    's word with only cs' => ['zs', ['word' => 'kacsa', 'emoji' => '🦆', 'sound' => 's'], 'word'],
    's word with only sz' => ['zs', ['word' => 'szék', 'emoji' => '🪑', 'sound' => 's'], 'word'],
    'syllables do not add up' => ['szotag', ['word' => 'cica', 'emoji' => '🐱', 'syllables' => ['ci', 'ka']], 'syllables'],
    'one syllable per vowel' => ['szotag', ['word' => 'autó', 'emoji' => '🚗', 'syllables' => ['au', 'tó']], 'syllables'],
    'digraph first sound' => ['kezdo', ['word' => 'szív', 'emoji' => '❤️', 'sound' => 's'], 'sound'],
    'accusative needs -t' => ['szamol', ['name' => 'alma', 'accusative' => 'alma', 'emoji' => '🍎'], 'accusative'],
    'rhyme is the ending' => ['rimelo', ['word' => 'hal', 'emoji' => '🐟', 'rhyme' => 'ál'], 'rhyme'],
    'chunks rebuild the sentence' => ['mondd', ['text' => 'A cica alszik.', 'chunks' => ['A kutya', 'alszik.'], 'emoji' => '🐱'], 'chunks'],
    'not an emoji' => ['papagaj', ['word' => 'alma', 'emoji' => 'alma'], 'emoji'],
    'unknown option' => ['ceruza', ['path' => 'spiral'], 'path'],
    'too few pictures' => ['valogato', ['key' => 'x', 'label' => 'X', 'singular' => 'x', 'icon' => '🎁', 'items' => [['🐶', 'kutya']]], 'items'],
]);

it('knows Hungarian first sounds and plain s', function () {
    expect(ContentRules::firstSound('gyöngy'))->toBe('gy')
        ->and(ContentRules::firstSound('dzsem'))->toBe('dzs')
        ->and(ContentRules::firstSound('Szív'))->toBe('sz')
        ->and(ContentRules::firstSound('alma'))->toBe('a')
        ->and(ContentRules::hasPlainS('asszony'))->toBeFalse()
        ->and(ContentRules::hasPlainS('kalács'))->toBeFalse()
        ->and(ContentRules::hasPlainS('hátsó'))->toBeTrue();
});

it('seeds again without duplicates, keeps edited items, switches off removed ones', function () {
    seed(BeszedContentSeeder::class);
    $count = BeszedContentItem::count();

    seed(BeszedContentSeeder::class);
    expect(BeszedContentItem::count())->toBe($count);

    // edited in the editor: the seeder must not touch it, even to deactivate
    $edited = BeszedContentItem::where('game', 'zs')->first();
    $edited->update(['level' => 3, 'active' => false, 'edited_at' => now()]);
    // a seed item that is no longer in the JSON
    $gone = BeszedContentItem::create(['game' => 'zs', 'level' => 1, 'payload' => ['word' => 'sósav', 'emoji' => '🧪', 'sound' => 's'], 'source' => 'seed', 'seed_key' => 'old']);
    // made in the editor
    $own = BeszedContentItem::create(['game' => 'zs', 'level' => 1, 'payload' => ['word' => 'mese', 'emoji' => '📖', 'sound' => 's'], 'source' => 'admin', 'edited_at' => now()]);

    seed(BeszedContentSeeder::class);

    expect($edited->fresh())->level->toBe(3)->active->toBeFalse()
        ->and($gone->fresh()->active)->toBeFalse()
        ->and($own->fresh()->active)->toBeTrue();
});

it('brings recently missed items back more often', function () {
    seed(BeszedContentSeeder::class);
    $user = User::factory()->create();
    $child = Child::create(['user_id' => $user->id, 'name' => 'Zoé']);
    $missed = BeszedContentItem::forGame('szotag')->where('level', 2)->first();
    foreach (range(1, 3) as $i) {
        BeszedAttempt::create(['child_id' => $child->id, 'game' => 'szotag', 'content_item_id' => $missed->id, 'level' => 1, 'correct' => false, 'tries' => 3]);
    }

    $hits = collect(range(1, 20))->filter(fn () => collect(actingAs($user)
        ->getJson("/api/beszed/children/{$child->id}/session?game=szotag")->assertOk()->json('rounds'))
        ->contains('content_item_id', $missed->id))->count();

    // 8 of 360+ items per session: about 1 in 45 by chance; missed three times, nearly always.
    expect($hits)->toBeGreaterThanOrEqual(15);
});

it('leans towards items that fit the child\'s age', function () {
    seed(BeszedContentSeeder::class);
    $user = User::factory()->create();
    $small = Child::create(['user_id' => $user->id, 'name' => 'Kicsi', 'birth_date' => CarbonImmutable::now()->subYears(3)->subMonths(6)]);

    $levels = collect(range(1, 10))->flatMap(fn () => actingAs($user)
        ->getJson("/api/beszed/children/{$small->id}/session?game=szotag")->assertOk()->json('rounds'))
        ->map(fn ($r) => BeszedContentItem::find($r['content_item_id'])->level)
        ->countBy();

    // 3–4 years: mostly one-syllable (level 1) words, rarely the long ones.
    expect($levels[1] ?? 0)->toBeGreaterThan($levels[3] ?? 0);
});
