<?php

use App\Beszed\Rounds\LabirintusRounds as L;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Labirintus: every maze is perfect (all cells reachable, exactly one way between any two), the walls agree on
// both sides, the path really leads from the start to the goal, and the star of level 3 lies on that path.

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function labirintusSession(int $level): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=labirintus&level=$level")
        ->assertOk()
        ->json();
}

/** Open sides between neighbours, checked from both cells; returns the passages as "a-b" keys. */
function labirintusPassages(array $walls, int $cols, int $rows): array
{
    $passages = [];
    foreach ($walls as $cell => $mask) {
        $x = $cell % $cols;
        $y = intdiv($cell, $cols);
        // the outer border is always closed
        if ($y === 0) {
            expect($mask & L::N)->toBe(L::N);
        }
        if ($y === $rows - 1) {
            expect($mask & L::S)->toBe(L::S);
        }
        if ($x === 0) {
            expect($mask & L::W)->toBe(L::W);
        }
        if ($x === $cols - 1) {
            expect($mask & L::E)->toBe(L::E);
        }
        foreach (L::neighbours($cell, $cols, $rows) as [$nb, $side]) {
            // a wall seen from one side is a wall from the other side too
            expect((bool) ($mask & $side))->toBe((bool) ($walls[$nb] & L::opposite($side)));
            if (! ($mask & $side)) {
                $passages[min($cell, $nb).'-'.max($cell, $nb)] = true;
            }
        }
    }

    return array_keys($passages);
}

function labirintusReachable(array $walls, int $cols, int $rows, int $from): int
{
    $seen = [$from => true];
    $queue = [$from];
    while ($queue) {
        $cell = array_shift($queue);
        foreach (L::neighbours($cell, $cols, $rows) as [$nb, $side]) {
            if (! ($walls[$cell] & $side) && ! isset($seen[$nb])) {
                $seen[$nb] = true;
                $queue[] = $nb;
            }
        }
    }

    return count($seen);
}

it('makes perfect mazes: connected, a tree, walls agree on both sides', function (int $cols, int $rows) {
    foreach (range(1, 40) as $i) {
        $maze = L::maze($cols, $rows, true, 2);
        $n = $cols * $rows;

        expect($maze['walls'])->toHaveCount($n)
            ->and(labirintusReachable($maze['walls'], $cols, $rows, $maze['start']))->toBe($n)
            // connected with exactly n − 1 passages = a tree: one way between any two cells
            ->and(labirintusPassages($maze['walls'], $cols, $rows))->toHaveCount($n - 1);
    }
})->with([[4, 4], [6, 6], [8, 8]]);

it('starts in a corner, ends in the opposite one, and the path walks through open sides', function () {
    foreach (range(1, 40) as $i) {
        $maze = L::maze(6, 6);
        $path = $maze['path'];

        expect($maze['start'])->toBeIn([0, 5, 30, 35])
            ->and($maze['goal'])->toBe(35 - $maze['start'])
            ->and($path[0])->toBe($maze['start'])
            ->and(end($path))->toBe($maze['goal'])
            ->and(array_unique($path))->toHaveCount(count($path));
        for ($k = 1; $k < count($path); $k++) {
            $side = collect(L::neighbours($path[$k - 1], 6, 6))->firstWhere(0, $path[$k])[1] ?? null;
            expect($side)->not->toBeNull()
                ->and($maze['walls'][$path[$k - 1]] & $side)->toBe(0);
        }
        expect(L::solve($maze['walls'], 6, 6, $maze['start'], $maze['goal']))->toBe($path);
    }
});

it('puts the star on the way, never on the start or the goal', function () {
    foreach (range(1, 60) as $i) {
        $maze = L::maze(8, 8, true, 3);

        expect($maze['star'])->toBeIn(array_slice($maze['path'], 1, -1));
    }
    expect(L::maze(4, 4)['star'])->toBeNull();
});

it('serves rounds of the right size, a star only on level 3, and every sentence', function (int $level) {
    $session = labirintusSession($level);
    [$cols, $rows] = L::SIZES[$level];

    expect($session['rounds'])->toHaveCount(3);
    foreach ($session['rounds'] as $r => $round) {
        $d = $round['data'];
        expect($round['engine'])->toBe('grid')
            ->and($d['mode'])->toBe('maze')
            ->and([$d['cols'], $d['rows']])->toBe([$cols, $rows])
            ->and($d['walls'])->toHaveCount($cols * $rows)
            ->and(labirintusReachable($d['walls'], $cols, $rows, $d['start']))->toBe($cols * $rows)
            ->and($d['path'][0])->toBe($d['start'])
            ->and(end($d['path']))->toBe($d['goal'])
            ->and($d['hero'])->not->toBeEmpty()
            ->and($d['target'])->not->toBeEmpty()
            ->and($d['grade'])->toHaveCount(2)
            ->and($d['onCorrect'])->not->toBeEmpty()
            ->and($d['onBump'])->not->toBeEmpty()
            ->and($d['onDeadEnd'])->not->toBeEmpty()
            ->and($round['prompt']['text'])->not->toBeEmpty();
        if ($level === 3) {
            expect($d['star'])->toBeIn(array_slice($d['path'], 1, -1))
                ->and($round['prompt']['text'])->toContain('csillagot');
        } else {
            expect($d['star'])->toBeNull();
        }
        // only the first round explains how to move
        expect(str_contains($round['prompt']['text'], 'Húzd az ujjadat'))->toBe($r === 0);
    }
})->with([1, 2, 3]);

it('has at least ten themes, each with its own hero and goal', function () {
    $items = BeszedContentItem::forGame('labirintus')->get();

    expect($items->count())->toBeGreaterThanOrEqual(10);
    foreach ($items as $item) {
        expect($item->payload['hero'])->not->toBe($item->payload['goal'])
            ->and($item->payload['task'])->toEndWith('!');
    }
});
