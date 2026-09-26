<?php

namespace App\Beszed;

use App\Models\BeszedSkillLevel;
use App\Models\Child;

/**
 * Adaptive difficulty. Clean first-try wins build a streak; after
 * `up_after` of them the level goes up. A skipped round (or 3+ tries)
 * drops one level, so the child never gets stuck on "too hard".
 */
class Leveler
{
    public function current(Child $child, string $game): int
    {
        $cfg = config("beszed.games.$game.adaptive");

        return $cfg ? $this->row($child, $game, $cfg)->level : 1;
    }

    public function record(Child $child, string $game, bool $correct, int $tries): int
    {
        $cfg = config("beszed.games.$game.adaptive");
        if (! $cfg) {
            return 1;
        }

        $row = $this->row($child, $game, $cfg);

        if ($correct && $tries === 1) {
            $row->streak++;
            if ($row->streak >= $cfg['up_after'] && $row->level < $cfg['max']) {
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

        return $row->level;
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
