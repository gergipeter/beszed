<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Labirintus: a maze made on the server for every round (a "perfect" maze: randomised depth-first search, the
 * recursive backtracker, so every cell can be reached and there is exactly one way from the start to the goal).
 * The child drags the hero along the corridors (or taps the next cell) to the goal: 4×4 on level 1, 6×6 on
 * level 2, 8×8 with a star to pick up on the way on level 3. The engine (grid, mode maze) only draws and moves;
 * the walls, the solution path and every sentence come from here. The win is graded by wrong turns and bumps.
 */
class LabirintusRounds extends RoundFactory
{
    /** Wall bits of a cell: a set bit is a wall on that side. */
    public const N = 1;

    public const E = 2;

    public const S = 4;

    public const W = 8;

    /** level => [columns, rows] */
    public const SIZES = [1 => [4, 4], 2 => [6, 6], 3 => [8, 8]];

    /** Forks (cells with a side way) the way to the goal passes at least, so there is something to choose. */
    private const FORKS = [1 => 1, 2 => 2, 3 => 3];

    /** Wrong turns allowed for a smooth (tries 1) and an OK (tries 2) win; bumps count half. */
    private const GRADE = [1 => [1, 3], 2 => [2, 5], 3 => [3, 7]];

    private const STAR = ['Megvan a csillag! Menjünk tovább!', 'Ügyes, felvetted a csillagot!', 'Csillag a zsebben! Tovább!'];

    public function build(Collection $items, int $level, int $count): array
    {
        $level = max(1, min(3, $level));
        [$cols, $rows] = self::SIZES[$level];

        return $this->cycle($items, $count)->values()->map(function ($item, $r) use ($level, $cols, $rows) {
            $p = $item->payload;
            $maze = self::maze($cols, $rows, $level === 3, self::FORKS[$level]);
            $parts = [$p['task']];
            if ($maze['star'] !== null) {
                $parts[] = 'Útközben vedd fel a csillagot is!';
            }
            if ($r === 0) {
                $parts[] = 'Húzd az ujjadat az úton, vagy koppints a szomszéd mezőre!';
            }

            return $this->round('grid', implode(' ', $parts), [
                'mode' => 'maze',
                'cols' => $cols,
                'rows' => $rows,
                'walls' => $maze['walls'],
                'start' => $maze['start'],
                'goal' => $maze['goal'],
                'path' => $maze['path'],
                'star' => $maze['star'],
                'hero' => $p['hero'],
                'target' => $p['goal'],
                'grade' => self::GRADE[$level],
                // after this many wrong turns, footprints show the next few steps
                'hintAfter' => $level + 2,
                'onCorrect' => $p['says'],
                'onStar' => self::STAR[array_rand(self::STAR)],
                'onBump' => 'Arra fal van. Keress másik utat!',
                'onDeadEnd' => 'Ez zsákutca, innen nincs tovább. Fordulj vissza!',
            ], $item->id, $parts);
        })->all();
    }

    /**
     * A perfect maze on a cols×rows grid. Cells are numbered row by row (index = row × cols + col). The start is a
     * random corner, the goal the opposite one; `path` is the one way between them (cell indices, start first);
     * `star` a cell in the middle part of that path (or null).
     *
     * @return array{cols: int, rows: int, walls: list<int>, start: int, goal: int, path: list<int>, star: ?int}
     */
    public static function maze(int $cols, int $rows, bool $star = false, int $forks = 0): array
    {
        $corners = [0, $cols - 1, ($rows - 1) * $cols, $rows * $cols - 1];
        $best = null;
        for ($attempt = 0; $attempt < 40; $attempt++) {
            $walls = self::carve($cols, $rows);
            $start = $corners[array_rand($corners)];
            $goal = $rows * $cols - 1 - $start; // the opposite corner
            $path = self::solve($walls, $cols, $rows, $start, $goal);
            $found = self::forks($walls, $path);
            if ($best === null || $found > $best[3]) {
                $best = [$walls, $start, $goal, $found, $path];
            }
            if ($found >= $forks) {
                break;
            }
        }
        [$walls, $start, $goal, , $path] = $best;

        $starCell = null;
        if ($star) {
            $n = count($path);
            $from = max(1, (int) floor($n * 0.35));
            $to = min($n - 2, (int) ceil($n * 0.65));
            $starCell = $path[random_int($from, max($from, $to))];
        }

        return ['cols' => $cols, 'rows' => $rows, 'walls' => $walls, 'start' => $start, 'goal' => $goal, 'path' => $path, 'star' => $starCell];
    }

    /**
     * The recursive backtracker (iterative): from a random cell, walk to a random unvisited neighbour, knocking
     * the wall down, and step back when there is none. Visits every cell once, so the passages form a tree.
     *
     * @return list<int>
     */
    private static function carve(int $cols, int $rows): array
    {
        $n = $cols * $rows;
        $walls = array_fill(0, $n, self::N | self::E | self::S | self::W);
        $first = random_int(0, $n - 1);
        $visited = [$first => true];
        $stack = [$first];
        while ($stack) {
            $cell = end($stack);
            $next = array_values(array_filter(self::neighbours($cell, $cols, $rows), fn ($nb) => ! isset($visited[$nb[0]])));
            if (! $next) {
                array_pop($stack);

                continue;
            }
            [$to, $side] = $next[random_int(0, count($next) - 1)];
            $walls[$cell] &= ~$side;
            $walls[$to] &= ~self::opposite($side);
            $visited[$to] = true;
            $stack[] = $to;
        }

        return $walls;
    }

    /**
     * The way from $from to $to through open sides (breadth-first; in a perfect maze the only one).
     *
     * @param  list<int>  $walls
     * @return list<int>
     */
    public static function solve(array $walls, int $cols, int $rows, int $from, int $to): array
    {
        $prev = [$from => -1];
        $queue = [$from];
        while ($queue) {
            $cell = array_shift($queue);
            if ($cell === $to) {
                break;
            }
            foreach (self::neighbours($cell, $cols, $rows) as [$nb, $side]) {
                if (! ($walls[$cell] & $side) && ! isset($prev[$nb])) {
                    $prev[$nb] = $cell;
                    $queue[] = $nb;
                }
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

    /** Cells of the way (not the goal) where a side way branches off: places the child has to choose. */
    private static function forks(array $walls, array $path): int
    {
        $count = 0;
        foreach (array_slice($path, 0, -1) as $i => $cell) {
            $open = 4 - self::bits($walls[$cell]);
            // the start has no way in, every other cell one: more than that opening is a fork
            if ($open > ($i === 0 ? 1 : 2)) {
                $count++;
            }
        }

        return $count;
    }

    private static function bits(int $mask): int
    {
        return ($mask & 1) + ($mask >> 1 & 1) + ($mask >> 2 & 1) + ($mask >> 3 & 1);
    }

    /** @return list<array{0: int, 1: int}> [neighbour cell, side of $cell it lies on] */
    public static function neighbours(int $cell, int $cols, int $rows): array
    {
        $x = $cell % $cols;
        $y = intdiv($cell, $cols);
        $out = [];
        if ($y > 0) {
            $out[] = [$cell - $cols, self::N];
        }
        if ($x < $cols - 1) {
            $out[] = [$cell + 1, self::E];
        }
        if ($y < $rows - 1) {
            $out[] = [$cell + $cols, self::S];
        }
        if ($x > 0) {
            $out[] = [$cell - 1, self::W];
        }

        return $out;
    }

    public static function opposite(int $side): int
    {
        return match ($side) {
            self::N => self::S,
            self::S => self::N,
            self::E => self::W,
            self::W => self::E,
        };
    }
}
