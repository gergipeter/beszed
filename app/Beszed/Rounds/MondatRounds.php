<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Sentence building: Csillám says a short sentence, its words lie shuffled, and the child taps them
 * in the order they were said. Word order and listening memory in one. The target sentence length
 * grows smoothly from three words at level 1 to five at level 100 (scaleInt(); content tops out at
 * five words).
 */
class MondatRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $n = $this->scaleInt($level, 3, 5);
        $pool = $items->filter(fn ($i) => count(explode(' ', $i->payload['text'])) === $n)->values();
        $pool = $pool->count() >= 3 ? $pool : $items;
        $rounds = [];

        foreach ($this->cycle($pool, $count)->values() as $r => $sentence) {
            $text = $sentence->payload['text'];
            $words = collect(explode(' ', $text))->map(fn ($w, $k) => ['id' => "w$k", 'emoji' => '', 'label' => $w]);
            $order = $words->pluck('id')->all();
            do {
                $shuffled = $words->shuffle()->values();
            } while ($words->count() > 1 && $shuffled->pluck('id')->all() === $order);

            $prompt = $r === 0
                ? "Hallgasd meg a mondatot, aztán rakd össze a szavakból! $text"
                : "Hallgasd meg, és rakd össze! $text";

            $rounds[] = $this->round('order', $prompt, [
                'items' => $shuffled->all(),
                'order' => $order,
                'wrong' => 'Hmm, ez a szó még nem ide kerül. Gondold végig, hogyan mondtam a mondatot!',
                'onCorrect' => "Így van! $text",
            ], $sentence->id, ['Figyelj!', $text]);
        }

        return $rounds;
    }
}
