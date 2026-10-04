<?php

use App\Beszed\Rounds\LepegetoRounds;
use App\Beszed\Rounds\SzajtornaRounds;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function speechSession(string $game, string $query = ''): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=$game$query")
        ->assertOk()
        ->json();
}

it('Szájtorna asks for the repetitions of the level, with every move to copy', function (int $level, int $reps) {
    $session = speechSession('szajtorna', "&level=$level");

    expect($session['rounds'])->toHaveCount(4);
    foreach ($session['rounds'] as $round) {
        expect($round['engine'])->toBe('mimic')
            ->and($round['data']['reps'])->toBe($reps)
            ->and(count($round['data']['moves']))->toBeGreaterThanOrEqual(2)
            ->and($round['data']['moves'][0])->toHaveKeys(['emoji', 'label']);
    }
})->with([[1, 3], [2, 5], [3, 10]]);

it('Szájtorna tells the face tracker which movement each move shows', function () {
    // the keys of POSES in resources/js/modules/beszed/engines/mimic/poses.js
    $known = ['puff', 'pucker', 'smile', 'open', 'frown', 'roll', 'rollLower', 'rollUpper', 'left', 'right'];
    $exercises = json_decode(file_get_contents(database_path('seeders/data/beszed/szajtorna.json')), true);

    foreach ($exercises as $e) {
        $poses = array_map(fn ($m) => SzajtornaRounds::pose($m[0]), $e['payload']['moves']);
        expect(array_filter($poses))->not->toBeEmpty("{$e['payload']['name']}: no move the mirror can count")
            ->and(array_diff(array_filter($poses), $known))->toBe([]);
    }
    expect(SzajtornaRounds::pose('😗➡️'))->toBe('right')
        ->and(SzajtornaRounds::pose('⬅️😗'))->toBe('left')
        ->and(SzajtornaRounds::pose('😐'))->toBeNull();

    $move = speechSession('szajtorna')['rounds'][0]['data']['moves'][0];
    expect($move)->toHaveKey('pose');
});

it('Szájtorna levels name the repetitions', function () {
    expect(SzajtornaRounds::REPS)->toBe([1 => [3, 'háromszor'], 2 => [5, 'ötször'], 3 => [10, 'tízszer']]);
});

it('Lépegető builds a path from Start to Cél with a task on every field', function (int $level, int $fields) {
    $session = speechSession('lepegeto', "&level=$level&category=osz");
    $board = $session['rounds'][0]['data'];
    $tiles = $board['tiles'];

    expect($session['rounds'])->toHaveCount(1)
        ->and($session['rounds'][0]['engine'])->toBe('board')
        ->and($tiles)->toHaveCount($fields + 2)
        ->and($tiles[0]['kind'])->toBe('start')
        ->and(end($tiles)['kind'])->toBe('goal')
        ->and($board['theme'])->toBe('ősz');
    foreach (array_slice($tiles, 1, -1) as $tile) {
        expect($tile['text'])->not->toBe('')->and($tile['kind'])->toBeIn(['ask', 'echo', 'move', 'clap', 'mouth']);
    }
    // no field twice on one board
    expect(collect($tiles)->pluck('text')->duplicates()->all())->toBe([]);
})->with([[1, 8], [2, 12], [3, 16]]);

it('Lépegető has enough tasks for every season and every level', function (string $theme) {
    $rows = json_decode(file_get_contents(database_path('seeders/data/beszed/lepegeto.json')), true, flags: JSON_THROW_ON_ERROR);
    $count = collect($rows)->where('payload.theme', $theme)->count();

    expect($count)->toBeGreaterThanOrEqual($theme === 'osz' ? 16 : 10)
        ->and(array_keys(LepegetoRounds::THEMES))->toContain($theme);
})->with(array_keys(LepegetoRounds::THEMES));

it('Lépegető plays the season it is when no theme is picked', function (string $date, string $theme) {
    $this->travelTo($date);

    $texts = collect(speechSession('lepegeto')['rounds'][0]['data']['tiles'])->pluck('text');
    $rows = json_decode(file_get_contents(database_path('seeders/data/beszed/lepegeto.json')), true);
    $ofTheme = collect($rows)->where('payload.theme', $theme)->pluck('payload.text');

    expect($texts->slice(1, -1)->every(fn ($t) => $ofTheme->contains($t)))->toBeTrue();
})->with([['2026-10-03', 'osz'], ['2026-01-15', 'tel'], ['2026-04-10', 'tavasz'], ['2026-07-20', 'nyar']]);

it('Lépegető offers the seasons to pick from, with its own question', function () {
    $game = collect(actingAs($this->user)->getJson('/api/beszed/meta')->assertOk()->json('games'))->firstWhere('id', 'lepegeto');

    expect(collect($game['categories'])->pluck('id')->all())->toBe(['osz', 'tel', 'tavasz', 'nyar'])
        ->and($game['pickPrompt'])->toContain('évszak');
});
