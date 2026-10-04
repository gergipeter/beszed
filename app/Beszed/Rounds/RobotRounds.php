<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Kis robot: first coding with the direction words fel, le, balra, jobbra (absolute moves, like Bee-Bot).
 * Every round is a grid made here: the robot, its goal and obstacles placed at random, kept only when the
 * goal can be reached and the shortest way (breadth-first search) is as long as the level asks.
 *   level 1  each arrow moves the robot at once (2–4 steps)
 *   level 2  the child lines up 2–4 moves, then "Indulj!" runs them
 *   level 3  4–7 moves, and an obstacle stands in the straight way, so the robot has to go round
 * The engine (grid, mode program) draws and runs the program; every sentence comes from here.
 */
class RobotRounds extends RoundFactory
{
    /** level => [columns, rows, fewest obstacles, most obstacles, shortest way min, max, commands that fit the strip] */
    public const LEVELS = [
        1 => [4, 4, 1, 2, 2, 4, 0],
        2 => [4, 4, 1, 3, 2, 4, 6],
        3 => [5, 5, 3, 6, 4, 7, 10],
    ];

    /** The four moves: [id, the button's word, what Csillám says, column step, row step]. */
    public const MOVES = [
        ['up', 'Fel', 'Fel!', 0, -1],
        ['down', 'Le', 'Le!', 0, 1],
        ['left', 'Balra', 'Balra!', -1, 0],
        ['right', 'Jobbra', 'Jobbra!', 1, 0],
    ];

    public const HERO = '🤖';

    public function build(Collection $items, int $level, int $count): array
    {
        $level = max(1, min(3, $level));
        $direct = self::LEVELS[$level][6] === 0;

        return $this->cycle($items, $count)->values()->map(function ($item, $r) use ($level, $direct) {
            $p = $item->payload;
            $grid = self::grid($level);
            $parts = [];
            if ($r === 0) {
                $parts[] = $direct
                    ? 'Koppints a nyilakra, és a robot arra lép!'
                    : 'Tedd sorba a lépéseket a nyilakkal, aztán nyomd meg az Indulj gombot!';
            }
            $parts[] = $p['task'];
            $fix = $direct ? '' : ' Javítsd ki a lépéseket!';

            return $this->round('grid', implode(' ', $parts), $grid + [
                'mode' => 'program',
                'direct' => $direct,
                'maxSteps' => self::LEVELS[$level][6],
                'hero' => self::HERO,
                'target' => $p['goal'],
                'obstacle' => $p['obstacle'],
                'moves' => array_map(fn ($m) => ['id' => $m[0], 'label' => $m[1], 'say' => $m[2]], self::MOVES),
                // direct: wasted steps + bumps; program: runs that did not reach the goal
                'grade' => $direct ? [1, 3] : [0, 2],
                // after this many of them, footprints show the way
                'hintAfter' => $direct ? 3 : 2,
                'onCorrect' => $p['says'],
                'onBumpBlock' => $p['bump'].$fix,
                'onBumpEdge' => 'Bumm! Ott a pálya széle.'.$fix,
                'onShort' => 'A robot megállt, de még nem ért oda. Tegyél hozzá még lépéseket!',
            ], $item->id, $parts);
        })->all();
    }

    /**
     * A solvable grid for the level: cells numbered row by row; `blocks` the obstacle cells; `best` the length of
     * the shortest way; `path` one shortest way (cells, start first) and `solution` its moves.
     *
     * @return array{cols: int, rows: int, start: int, goal: int, blocks: list<int>, best: int, path: list<int>, solution: list<string>}
     */
    public static function grid(int $level): array
    {
        [$cols, $rows, $fewest, $most, $min, $max] = self::LEVELS[$level];
        $n = $cols * $rows;
        for ($attempt = 0; $attempt < 2000; $attempt++) {
            $start = random_int(0, $n - 1);
            $goal = random_int(0, $n - 1);
            $straight = abs($start % $cols - $goal % $cols) + abs(intdiv($start, $cols) - intdiv($goal, $cols));
            if ($goal === $start || $straight > $max) {
                continue;
            }
            $free = array_values(array_diff(range(0, $n - 1), [$start, $goal]));
            shuffle($free);
            $blocks = array_slice($free, 0, random_int($fewest, $most));
            sort($blocks);
            $path = self::shortest($cols, $rows, $blocks, $start, $goal);
            $best = count($path) - 1;
            if ($best < $min || $best > $max) {
                continue;
            }
            // level 2: at least two different directions; level 3: the straight way is blocked, go round
            if ($level === 2 && ($start % $cols === $goal % $cols || intdiv($start, $cols) === intdiv($goal, $cols))) {
                continue;
            }
            if ($level === 3 && $best <= $straight) {
                continue;
            }

            return compact('cols', 'rows', 'start', 'goal', 'blocks', 'best', 'path') + ['solution' => self::moves($cols, $path)];
        }

        return self::FALLBACK[$level] + ['solution' => self::moves(self::FALLBACK[$level]['cols'], self::FALLBACK[$level]['path'])];
    }

    /** Never needed in practice (the random search finds a grid at once); here so build() can't fail. */
    public const FALLBACK = [
        1 => ['cols' => 4, 'rows' => 4, 'start' => 0, 'goal' => 10, 'blocks' => [5], 'best' => 4, 'path' => [0, 1, 2, 6, 10]],
        2 => ['cols' => 4, 'rows' => 4, 'start' => 4, 'goal' => 10, 'blocks' => [5, 15], 'best' => 3, 'path' => [4, 8, 9, 10]],
        3 => ['cols' => 5, 'rows' => 5, 'start' => 10, 'goal' => 14, 'blocks' => [3, 7, 12, 21], 'best' => 6, 'path' => [10, 11, 16, 17, 18, 13, 14]],
    ];

    /**
     * One shortest way from $from to $to round the blocks (breadth-first search; neighbours in random order, so
     * when there are several the hint isn't always the same). Empty when there is none.
     *
     * @param  list<int>  $blocks
     * @return list<int>
     */
    public static function shortest(int $cols, int $rows, array $blocks, int $from, int $to): array
    {
        $blocked = array_flip($blocks);
        $prev = [$from => -1];
        $queue = [$from];
        while ($queue) {
            $cell = array_shift($queue);
            if ($cell === $to) {
                break;
            }
            $moves = self::MOVES;
            shuffle($moves);
            foreach ($moves as [, , , $dx, $dy]) {
                $x = $cell % $cols + $dx;
                $y = intdiv($cell, $cols) + $dy;
                $next = $y * $cols + $x;
                if ($x < 0 || $y < 0 || $x >= $cols || $y >= $rows || isset($blocked[$next]) || isset($prev[$next])) {
                    continue;
                }
                $prev[$next] = $cell;
                $queue[] = $next;
            }
        }
        if (! isset($prev[$to])) {
            return [];
        }
        $path = [];
        for ($c = $to; $c !== -1; $c = $prev[$c]) {
            $path[] = $c;
        }

        return array_reverse($path);
    }

    /** @return list<string> the move ids that walk along $path */
    private static function moves(int $cols, array $path): array
    {
        $out = [];
        for ($i = 1; $i < count($path); $i++) {
            $d = $path[$i] - $path[$i - 1];
            $out[] = match (true) {
                $d === -$cols => 'up',
                $d === $cols => 'down',
                $d === -1 => 'left',
                default => 'right',
            };
        }

        return $out;
    }
}
