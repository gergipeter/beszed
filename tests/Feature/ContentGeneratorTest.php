<?php

use App\Beszed\Content\ContentGenerator;
use App\Beszed\Content\Hungarian;

it('splits Hungarian words into spoken syllables', function (string $word, ?array $syllables) {
    expect(Hungarian::syllables($word))->toBe($syllables);
})->with([
    ['alma', ['al', 'ma']],
    ['macska', ['macs', 'ka']],
    ['karácsonyfa', ['ka', 'rá', 'csony', 'fa']],
    ['autó', ['a', 'u', 'tó']],
    ['pingvin', ['ping', 'vin']],
    ['nyelv', ['nyelv']],
    ['Mikulás', ['Mi', 'ku', 'lás']],
    ['hattyú', null], // "tty" is hyphenated as "ty-ty", which wouldn't spell the word
]);

it('finds the rhyming ending', function (string $word, string $rhyme) {
    expect(Hungarian::rhyme($word))->toBe($rhyme);
})->with([
    ['hal', 'al'], ['kalap', 'ap'], ['ló', 'ó'], ['cica', 'ica'], ['róka', 'óka'], ['egér', 'ér'],
]);

it('has a correct accusative for every word in the bank', function () {
    $words = json_decode(file_get_contents(database_path('lexicon/hu.json')), true);

    foreach ($words as $w) {
        expect($w)->toHaveKeys(['w', 'c', 'f'])
            ->and(isset($w['e']) || isset($w['p']))->toBeTrue("{$w['w']} has no picture");
        if (isset($w['acc'])) {
            expect(str_ends_with($w['acc'], 't'))->toBeTrue("{$w['w']} → {$w['acc']}")
                ->and(mb_substr(Hungarian::fold($w['acc']), 0, 2))->toBe(mb_substr(Hungarian::fold($w['w']), 0, 2), $w['w']);
        }
    }
    expect(collect($words)->pluck('w')->duplicates()->all())->toBe([])
        ->and(collect($words)->pluck('e')->filter()->duplicates()->all())->toBe([])
        ->and(collect($words)->pluck('p')->filter()->duplicates()->all())->toBe([]);
});

it('matches the seed files: regenerating changes nothing', function () {
    $generator = ContentGenerator::fromFiles();

    foreach ($generator->build() as $game => $rows) {
        $file = json_decode(file_get_contents(database_path("seeders/data/beszed/$game.json")), true);
        expect($rows)->toEqual($file, "$game.json is out of date: run php artisan beszed:content-generate");
    }
    expect($generator->rejected)->toBe([]);
});

it('gives every game plenty of content', function (string $game, int $min) {
    $rows = json_decode(file_get_contents(database_path("seeders/data/beszed/$game.json")), true);
    expect(count($rows))->toBeGreaterThanOrEqual($min);
})->with([
    ['szotag', 300], ['kezdo', 300], ['papagaj', 300], ['kirako', 300], ['parkereso', 300],
    ['arnyek', 150], ['rimelo', 140], ['melyik', 140], ['hol', 90], ['szamol', 90], ['zs', 60],
]);

it('never pairs a compound with its own base word as a rhyme', function () {
    $rows = collect(json_decode(file_get_contents(database_path('seeders/data/beszed/rimelo.json')), true));

    // Only generated words are checked: the hand-written "autó / tó" is a real rhyme.
    $rows->groupBy('payload.rhyme')->each(function ($group) {
        $words = $group->pluck('payload.word');
        foreach ($group->where('gen', true)->pluck('payload.word') as $a) {
            foreach ($words as $b) {
                expect($a !== $b && str_ends_with($a, $b))->toBeFalse("$a / $b");
            }
        }
    });
});

it('takes a word removed from the bank back out of the merged baskets', function () {
    $empty = array_fill_keys(ContentGenerator::GAMES, []);
    $basket = ['level' => 1, 'payload' => ['key' => 'allat', 'label' => 'Állatok', 'singular' => 'állat', 'icon' => '🐾',
        'items' => [['🐶', 'kutya'], ['🐱', 'cica'], ['🐰', 'nyuszi'], ['🐸', 'béka']]]];

    $merged = (new ContentGenerator([['w' => 'zebra', 'e' => '🦓', 'c' => 'animal', 'f' => 1]], ['valogato' => [$basket]] + $empty))
        ->build()['valogato'][0];
    expect($merged['payload']['items'])->toContain(['🦓', 'zebra'])->and($merged['gen_emojis'])->toBe(['🦓']);

    $again = (new ContentGenerator([], ['valogato' => [$merged]] + $empty))->build()['valogato'][0];
    expect($again)->toBe($basket);
});
