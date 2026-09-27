<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Picture puzzle: swap pieces until the picture is whole. 100 levels ("pályák"):
 * the grid grows from 2×2 to 5×5, from level 4 on the picture stands in one of
 * the drawn scenes (so every piece shows something), every other level has a
 * fairy-tale princess among its pictures, and on the high levels the example
 * picture fades after a while (tap it to see it again).
 */
class KirakoRounds extends RoundFactory
{
    public const LEVELS = 100;

    /** [from level, columns, rows] */
    private const BANDS = [[1, 2, 2], [7, 3, 2], [16, 3, 3], [31, 4, 3], [46, 4, 4], [66, 5, 4], [86, 5, 5]];

    /** The drawn scenes (SceneBackdrop.vue), taken in turn from level to level. */
    private const SCENES = ['meadow', 'beach', 'castle', 'underwater', 'snow', 'forest', 'sky'];

    /** @return array{0: int, 1: int} [columns, rows] of a level */
    public static function grid(int $level): array
    {
        $grid = [2, 2];
        foreach (self::BANDS as [$from, $cols, $rows]) {
            if ($level >= $from) {
                $grid = [$cols, $rows];
            }
        }

        return $grid;
    }

    /** How long the example picture stays: always (null) up to level 35, then 5 s, and 2.5 s from level 71. */
    public static function previewMs(int $level): ?int
    {
        return match (true) {
            $level <= 35 => null,
            $level <= 70 => 5000,
            default => 2500,
        };
    }

    public function build(Collection $items, int $level, int $count): array
    {
        $level = max(1, min(self::LEVELS, $level));
        [$cols, $rows] = self::grid($level);
        $themed = $this->theme($items);
        if ($themed) {
            // a picture theme the child picked: only its pictures
            $picked = $this->cycle($themed, $count)->values();
        } else {
            $tales = $items->filter(fn ($i) => ($i->payload['kind'] ?? null) === 'tale')->values();
            $things = $items->reject(fn ($i) => ($i->payload['kind'] ?? null) === 'tale')->values();
            $picked = $this->cycle($things->isEmpty() ? $items : $things, $count)->values();
            // every other level from 4 on: a princess (or another fairy-tale figure) in the middle round
            if ($level >= 4 && $level % 2 === 0 && $tales->isNotEmpty() && $count > 1) {
                $picked[1] = $this->weightedShuffle($tales)->first();
            }
        }

        return $picked->map(function ($it, $r) use ($level, $cols, $rows) {
            $tale = ($it->payload['kind'] ?? null) === 'tale';
            $scene = $it->payload['scene'] ?? ($level >= 4 ? self::SCENES[($level + $r) % count(self::SCENES)] : null);

            return $this->round('puzzle', 'Rakd ki a képet! Koppints két darabra, vagy húzd az egyiket a másikra!', array_filter([
                'emoji' => $it->payload['emoji'],
                'prop' => $it->payload['prop'] ?? null,
                'scene' => $scene,
                'cols' => $cols,
                'rows' => $rows,
                'pieces' => $this->shuffled($cols * $rows),
                'previewMs' => self::previewMs($level),
                'levelLabel' => "$level. pálya",
                'onCorrect' => $tale
                    ? "Hurrá! Kész a kép: {$it->payload['name']}!"
                    : "Hurrá! Kész a kép! Ez egy {$it->payload['name']}!",
            ], fn ($v) => $v !== null), $it->id);
        })->values()->all();
    }

    /** The pictures of the theme the child picked (config kirako.categories), or null for all of them. */
    private function theme(Collection $items): ?Collection
    {
        $theme = config('beszed.games.kirako.categories.'.($this->options['category'] ?? ''));
        if (! $theme || empty($theme['lexicon'] ?? $theme['emojis'] ?? $theme['tales'] ?? null)) {
            return null;
        }
        $pool = $items->filter(fn ($i) => in_array(self::lexiconCategory($i->payload['emoji']), $theme['lexicon'] ?? [], true)
            || in_array($i->payload['emoji'], $theme['emojis'] ?? [], true)
            || (($theme['tales'] ?? false) && ($i->payload['kind'] ?? null) === 'tale'))->values();

        return $pool->count() >= 3 ? $pool : null;
    }

    /** A picture's word-bank category (animal, vehicle…), by its emoji or its pictogram. */
    private static function lexiconCategory(string $picture): ?string
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (json_decode(file_get_contents(database_path('lexicon/hu.json')), true, flags: JSON_THROW_ON_ERROR) as $w) {
                if (isset($w['e'])) {
                    $map[$w['e']] = $w['c'];
                }
                if (isset($w['p'])) {
                    $map["arasaac:{$w['p']}"] = $w['c'];
                }
            }
        }

        return $map[$picture] ?? null;
    }

    /** pieces[position] = the piece lying there; never already solved. */
    private function shuffled(int $n): array
    {
        $solved = range(0, $n - 1);
        do {
            $order = collect($solved)->shuffle()->values()->all();
        } while ($order === $solved);

        return $order;
    }
}
