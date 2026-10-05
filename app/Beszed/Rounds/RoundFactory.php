<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Turns raw content items (words, sentences…) into ready-to-play rounds.
 * Engines on the client stay dumb: every option list, distractor and
 * feedback sentence is decided here.
 */
abstract class RoundFactory
{
    protected const NUM = ['nulla', 'egy', 'két', 'három', 'négy', 'öt', 'hat', 'hét', 'nyolc', 'kilenc', 'tíz'];

    /** @var array<int, float> item id => weight (SessionBuilder: missed lately → heavier) */
    protected array $weights = [];

    /** @var array{category?: ?string} what the child chose before playing */
    protected array $options = [];

    /** @param  array{category?: ?string}  $options */
    public function choose(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /** @param  array<int, float>  $weights */
    public function weigh(array $weights): static
    {
        $this->weights = $weights;

        return $this;
    }

    /**
     * @param  Collection<int, \App\Models\BeszedContentItem>  $items
     * @return array<int, array<string, mixed>>
     */
    abstract public function build(Collection $items, int $level, int $count): array;

    protected function round(string $engine, string $prompt, array $data, ?int $itemId = null, ?array $parts = null): array
    {
        $p = ['text' => $prompt];
        if ($parts) {
            $p['parts'] = array_values($parts);
        }

        return ['engine' => $engine, 'content_item_id' => $itemId, 'prompt' => $p, 'data' => $data];
    }

    /** Hungarian definite article. */
    protected function art(string $word): string
    {
        return preg_match('/^[aáeéiíoóöőuúüű]/iu', $word) ? 'az' : 'a';
    }

    protected function ucfirst(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
    }

    /**
     * Random order where heavier items tend to come first (Efraimidis–Spirakis):
     * each item still appears once, so a missed word returns sooner, not endlessly.
     */
    protected function weightedShuffle(Collection $items): Collection
    {
        return $items
            ->map(fn ($item) => [$item, (mt_rand(1, mt_getrandmax()) / mt_getrandmax()) ** (1 / max(0.05, $this->weights[$item->id] ?? 1.0))])
            ->sortByDesc(fn ($pair) => $pair[1])
            ->map(fn ($pair) => $pair[0])
            ->values();
    }

    /** $count items: whole weighted-shuffled passes, no item twice in a row. */
    protected function cycle(Collection $items, int $count): Collection
    {
        $out = [];
        while (count($out) < $count && $items->isNotEmpty()) {
            $batch = $this->weightedShuffle($items)->all();
            if ($out && count($batch) > 1 && end($out)->id === $batch[0]->id) {
                [$batch[0], $batch[1]] = [$batch[1], $batch[0]];
            }
            array_push($out, ...$batch);
        }

        return collect(array_slice($out, 0, $count));
    }

    /** Random value from a list, different from $last when possible. */
    protected function pickNot(array $list, mixed $last): mixed
    {
        $candidates = count($list) > 1 ? array_values(array_filter($list, fn ($x) => $x !== $last)) : $list;

        return $candidates[array_rand($candidates)];
    }

    /**
     * Bias $this->weights (SessionBuilder's missed-item review weights) towards
     * items at or below the child's adaptive level: items past it are heavily
     * discouraged — so the pool never runs dry the moment a level has few
     * items — but not excluded. Call once at the top of build(), before any
     * weightedShuffle()/cycle().
     *
     * `$level` is the game's 1–100 adaptive level; content items only carry a
     * coarse 1–3 (or similar) level, so this maps $level onto that smaller
     * range first (tier(), same split every game uses).
     */
    protected function favorLevel(Collection $items, int $level, int $contentMax = 3): void
    {
        $tier = $this->tier($level, $contentMax);
        foreach ($items as $item) {
            $gap = ($item->level ?? 1) - $tier;
            $this->weights[$item->id] = ($this->weights[$item->id] ?? 1.0) * ($gap <= 0 ? 1.0 : 0.35 ** $gap);
        }
    }

    /**
     * Maps the game's adaptive level (1–100) onto a small number of discrete
     * tiers (content levels, a `match` of named stages…), evenly: 1–100 split
     * into $tiers equal bands, band 1 = tier 1. The single conversion every
     * Rounds class uses so a 1–3 (or 1–4, 1–5…) tiering reads the same way
     * everywhere. `$max` lets a game whose adaptive range isn't 1–100 (still
     * rare) convert correctly too.
     */
    protected function tier(int $level, int $tiers, int $max = 100): int
    {
        return max(1, min($tiers, (int) ceil($level * $tiers / max(1, $max))));
    }

    /**
     * Smoothly scales a level-1..100 value onto a numeric range [$from, $to]
     * (can descend), evenly across the band — the parametric counterpart to
     * tier(): use this where a game's difficulty is a continuous quantity
     * (a grid size, a count, a time limit in ms) rather than a small set of
     * named modes. Linear by default; $curve > 1 keeps early levels easier
     * for longer (back-loads the increase), $curve < 1 front-loads it.
     */
    protected function scale(int $level, int|float $from, int|float $to, int $max = 100, float $curve = 1.0): float
    {
        $t = max(0.0, min(1.0, ($level - 1) / max(1, $max - 1)));

        return $from + ($to - $from) * ($t ** $curve);
    }

    /** scale(), rounded to an int (steps/counts/ms — most callers want this, not the raw float). */
    protected function scaleInt(int $level, int $from, int $to, int $max = 100, float $curve = 1.0): int
    {
        return (int) round($this->scale($level, $from, $to, $max, $curve));
    }
}
