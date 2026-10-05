<?php

use App\Beszed\Rounds\RobotRounds as R;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Kis robot: every grid can be solved, the shortest way is as long as the level asks, the sent solution walks
// round the obstacles to the goal, and level 1 moves at once while levels 2–3 are programmed.

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function robotSession(int $level): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=robot&level=$level")
        ->assertOk()
        ->json();
}

/** Shortest number of steps from start to goal (independent BFS), or null. */
function robotDistance(array $g): ?int
{
    $blocked = array_flip($g['blocks']);
    $dist = [$g['start'] => 0];
    $queue = [$g['start']];
    while ($queue) {
        $cell = array_shift($queue);
        if ($cell === $g['goal']) {
            return $dist[$cell];
        }
        $x = $cell % $g['cols'];
        $y = intdiv($cell, $g['cols']);
        foreach ([[0, -1], [0, 1], [-1, 0], [1, 0]] as [$dx, $dy]) {
            [$nx, $ny] = [$x + $dx, $y + $dy];
            $next = $ny * $g['cols'] + $nx;
            if ($nx >= 0 && $ny >= 0 && $nx < $g['cols'] && $ny < $g['rows'] && ! isset($blocked[$next]) && ! isset($dist[$next])) {
                $dist[$next] = $dist[$cell] + 1;
                $queue[] = $next;
            }
        }
    }

    return null;
}

/** Runs the move ids from the start: the cells visited, or null when it hits an obstacle or the edge. */
function robotRun(array $g, array $moves): ?array
{
    $step = ['up' => [0, -1], 'down' => [0, 1], 'left' => [-1, 0], 'right' => [1, 0]];
    $cell = $g['start'];
    $cells = [$cell];
    foreach ($moves as $m) {
        [$dx, $dy] = $step[$m];
        $x = $cell % $g['cols'] + $dx;
        $y = intdiv($cell, $g['cols']) + $dy;
        if ($x < 0 || $y < 0 || $x >= $g['cols'] || $y >= $g['rows'] || in_array($y * $g['cols'] + $x, $g['blocks'], true)) {
            return null;
        }
        $cells[] = $cell = $y * $g['cols'] + $x;
    }

    return $cells;
}

it('makes solvable grids with the shortest way inside the level band', function (int $level) {
    [$cols, $rows, $fewest, $most, $min, $max] = R::LEVELS[$level];
    foreach (range(1, 80) as $i) {
        $g = R::grid($level);
        $best = robotDistance($g);
        $straight = abs($g['start'] % $cols - $g['goal'] % $cols) + abs(intdiv($g['start'], $cols) - intdiv($g['goal'], $cols));

        expect([$g['cols'], $g['rows']])->toBe([$cols, $rows])
            ->and($best)->not->toBeNull()
            ->and($best)->toBe($g['best'])
            ->and($best)->toBeGreaterThanOrEqual($min)->toBeLessThanOrEqual($max)
            ->and(count($g['blocks']))->toBeGreaterThanOrEqual($fewest)->toBeLessThanOrEqual($most)
            ->and($g['blocks'])->not->toContain($g['start'])->not->toContain($g['goal'])
            ->and(array_unique($g['blocks']))->toHaveCount(count($g['blocks']))
            // the solution is a shortest program and really walks round the obstacles to the goal
            ->and($g['solution'])->toHaveCount($best)
            ->and(robotRun($g, $g['solution']))->toBe($g['path'])
            ->and(end($g['path']))->toBe($g['goal']);
        if ($level === 3) {
            expect($best)->toBeGreaterThan($straight); // the goal is behind an obstacle
        }
        if ($level === 2) {
            expect(count(array_unique($g['solution'])))->toBeGreaterThanOrEqual(2);
        }
    }
})->with([1, 2, 3]);

it('keeps the fallback grids solvable too', function (int $level) {
    $g = R::FALLBACK[$level];

    expect(robotDistance($g))->toBe($g['best'])
        ->and(count($g['path']) - 1)->toBe($g['best'])
        ->and(end($g['path']))->toBe($g['goal'])
        ->and($g['best'])->toBeGreaterThanOrEqual(R::LEVELS[$level][4])->toBeLessThanOrEqual(R::LEVELS[$level][5]);
})->with([1, 2, 3]);

it('serves direct control on level 1 and programs on levels 2–3, with every sentence', function (int $level, int $tier) {
    $session = robotSession($level);

    expect($session['rounds'])->toHaveCount(4);
    foreach ($session['rounds'] as $r => $round) {
        $d = $round['data'];
        expect($round['engine'])->toBe('grid')
            ->and($d['mode'])->toBe('program')
            ->and($d['direct'])->toBe($tier === 1)
            ->and($d['maxSteps'])->toBe($tier === 1 ? 0 : R::LEVELS[$tier][6])
            ->and(robotDistance($d))->toBe($d['best'])
            ->and(collect($d['moves'])->pluck('say')->all())->toBe(['Fel!', 'Le!', 'Balra!', 'Jobbra!'])
            ->and($d['target'])->not->toBeEmpty()
            ->and($d['obstacle'])->not->toBeEmpty()
            ->and($d['onCorrect'])->not->toBeEmpty()
            ->and($d['onBumpBlock'])->not->toBeEmpty()
            ->and($d['onBumpEdge'])->not->toBeEmpty()
            ->and($d['onShort'])->not->toBeEmpty();
        if ($tier > 1) {
            expect($d['maxSteps'])->toBeGreaterThan($d['best']);
        }
        // the first round explains the buttons
        expect(count($round['prompt']['parts']))->toBe($r === 0 ? 2 : 1);
    }
})->with([[1, 1], [50, 2], [100, 3]]); // level → tier(level, 3)

it('has at least ten themes with a goal and an obstacle that look different', function () {
    $items = BeszedContentItem::forGame('robot')->get();

    expect($items->count())->toBeGreaterThanOrEqual(10);
    foreach ($items as $item) {
        expect($item->payload['goal'])->not->toBe($item->payload['obstacle'])
            ->and($item->payload['task'])->toContain('robotot');
    }
});
