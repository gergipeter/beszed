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
}
