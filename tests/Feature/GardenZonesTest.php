<?php

it('puts every game in exactly one garden zone', function () {
    $placed = collect(config('beszed_folders.zones'))->flatten();

    expect($placed->duplicates()->all())->toBe([])
        ->and($placed->diff(array_keys(config('beszed.games')))->values()->all())->toBe([], 'a zone lists a game that does not exist')
        ->and(collect(array_keys(config('beszed.games')))->diff($placed)->values()->all())->toBe([], 'a game stands in no zone');
});

it('keeps every zone a playable size', function (string $zone, array $games) {
    expect(count($games))->toBeGreaterThanOrEqual(5)->toBeLessThanOrEqual(14);
})->with(collect((require __DIR__.'/../../config/beszed_folders.php')['zones'])->map(fn ($g, $z) => [$z, $g])->all());

it('tells the app which zone each game is in', function () {
    $user = App\Models\User::factory()->create();
    $games = collect(Pest\Laravel\actingAs($user)->getJson('/api/beszed/meta')->assertOk()->json('games'))->keyBy('id');

    expect($games['betuk']['zone'])->toBe('letters')
        ->and($games['szamol']['zone'])->toBe('meadow')
        ->and($games['elohely']['zone'])->toBe('world');
});
