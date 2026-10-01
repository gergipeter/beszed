<?php

namespace App\Beszed;

use App\Models\BeszedAttempt;
use App\Models\BeszedSkillLevel;
use App\Models\Child;

/**
 * Adaptive difficulty. Clean first-try wins build a streak; after
 * `up_after` of them the level goes up. A skipped round (or 3+ tries)
 * drops one level, so the child never gets stuck on "too hard".
 */
class Leveler
{
    public function __construct(private Entitlements $plans) {}

    /** The level to play now; a free account stops at the plan's cap (the stored level is kept for when they upgrade). */
    public function current(Child $child, string $game): int
    {
        $cfg = config("beszed.games.$game.adaptive");

        return $cfg ? $this->capped($child, $game, $this->row($child, $game, $cfg)->level) : 1;
    }

    /** Highest level of a game the child's parent has unlocked; null = all of them. */
    public function cap(Child $child, string $game): ?int
    {
        return $this->plans->levelCap($child->user, $game);
    }

    /** A level picked by hand (Kirakó's pálya chooser): stored, clamped to the game's range, then play goes on from there. */
    public function set(Child $child, string $game, int $level): int
    {
        $cfg = config("beszed.games.$game.adaptive");
        if (! $cfg) {
            return 1;
        }

        $row = $this->row($child, $game, $cfg);
        $wanted = max($cfg['min'], min($cfg['max'], $level));
        $cap = $this->cap($child, $game);
        if ($cap !== null && $wanted > $cap) {
            // beyond the free levels: play the last free one, and keep the level the child has reached
            return $cap;
        }

        $row->level = $wanted;
        $row->streak = 0;
        $row->save();

        return $row->level;
    }

    public function record(Child $child, string $game, bool $correct, int $tries): int
    {
        $cfg = config("beszed.games.$game.adaptive");
        if (! $cfg) {
            return 1;
        }

        $row = $this->row($child, $game, $cfg);

        // a game can count a slightly less tidy win as clean too (a puzzle graded 2: a few wasted swaps)
        if ($correct && $tries <= ($cfg['clean_tries'] ?? 1)) {
            $row->streak++;
            if ($row->streak >= ($this->placing($child, $game) ? 1 : $cfg['up_after']) && $row->level < min($cfg['max'], $this->cap($child, $game) ?? PHP_INT_MAX)) {
                $row->level++;
                $row->streak = 0;
            }
        } else {
            $row->streak = 0;
            if ((! $correct || $tries >= 3) && $row->level > $cfg['min']) {
                $row->level--;
            }
        }

        $row->save();

        return $this->capped($child, $game, $row->level);
    }

    /** The game's first few answers (this one included): a quick search for the child's level. */
    private function placing(Child $child, string $game): bool
    {
        $n = (int) config('beszed_skills.placement_answers');

        return $n > 0 && BeszedAttempt::where('child_id', $child->id)->where('game', $game)->limit($n + 1)->pluck('id')->count() <= $n;
    }

    private function capped(Child $child, string $game, int $level): int
    {
        $cap = $this->cap($child, $game);

        return $cap === null ? $level : min($level, $cap);
    }

    private function row(Child $child, string $game, array $cfg): BeszedSkillLevel
    {
        return BeszedSkillLevel::firstOrCreate(
            ['child_id' => $child->id, 'game' => $game],
            ['level' => $this->startingLevel($child, $cfg), 'streak' => 0],
        );
    }

    /** Age-appropriate starting level, clamped to [min, max]; falls back to the fixed 'start'. */
    private function startingLevel(Child $child, array $cfg): int
    {
        $band = AgeBands::of($child);
        $start = $cfg['starts_by_age'][$band] ?? $cfg['start'];

        return max($cfg['min'], min($cfg['max'], $start));
    }
}
