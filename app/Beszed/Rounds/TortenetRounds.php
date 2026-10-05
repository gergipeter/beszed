<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Story order: the pictures of a little story, shuffled; the child taps them
 * from what happened first to what happened last, and Csillám names each step.
 * Steps grow smoothly from three at level 1 to six at level 100 (scaleInt()):
 * a longer story keeps its first, last and some middle steps picked at random,
 * still in order, so a story with fewer steps than the target still works.
 */
class TortenetRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $n = $this->scaleInt($level, 3, 6);
        $stories = $items->filter(fn ($i) => count($i->payload['steps']) >= $n)->values();
        $stories = $stories->isEmpty() ? $items : $stories;
        $rounds = [];

        foreach ($this->cycle($stories, $count)->values() as $r => $story) {
            $steps = $this->shorten($story->payload['steps'], $n);
            $pieces = collect($steps)->map(fn ($s, $k) => ['id' => "t$k", 'emoji' => $s[0], 'label' => $s[1]]);
            $order = $pieces->pluck('id')->all();
            do {
                $shuffled = $pieces->shuffle()->values();
            } while ($shuffled->pluck('id')->all() === $order);

            $prompt = $r === 0
                ? 'Mi történt előbb? Koppints arra a képre, ami először történt, aztán arra, ami utána!'
                : 'Mi történt előbb? Rakd sorba a képeket!';

            $rounds[] = $this->round('order', $prompt, [
                'items' => $shuffled->all(),
                'order' => $order,
                'arrows' => true,
                'wrong' => 'Hmm, ez még nem most jön. Mi történt előbb?',
                'onCorrect' => 'Így van! '.$story->payload['story'],
            ], $story->id);
        }

        return $rounds;
    }

    /** $n steps of a story: the first, the last, and middle ones picked at random, in their order. */
    private function shorten(array $steps, int $n): array
    {
        if (count($steps) <= $n) {
            return array_values($steps);
        }
        $middle = range(1, count($steps) - 2);
        shuffle($middle);
        $keep = array_slice($middle, 0, $n - 2);
        sort($keep);

        return [$steps[0], ...array_map(fn ($k) => $steps[$k], $keep), $steps[count($steps) - 1]];
    }
}
