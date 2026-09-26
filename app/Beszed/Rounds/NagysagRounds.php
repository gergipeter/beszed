<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Seriation: the same picture in 3–5 sizes, tapped from the smallest to the
 * biggest. $level = number of sizes; at the top level every other round goes
 * the other way (biggest first), so the child has to listen, not just repeat.
 */
class NagysagRounds extends RoundFactory
{
    /** level → sizes */
    public const SIZES = [1 => 3, 2 => 4, 3 => 5];

    /** Smallest picture, relative to the biggest. */
    private const MIN_SCALE = 0.35;

    public function build(Collection $items, int $level, int $count): array
    {
        $n = self::SIZES[$level] ?? self::SIZES[1];
        $rounds = [];

        foreach ($this->cycle($items, $count)->values() as $r => $item) {
            $down = $level >= 3 && $r % 2 === 1;
            $pieces = collect(range(0, $n - 1))->map(fn ($k) => [
                'id' => "s$k",
                'emoji' => $item->payload['emoji'],
                'scale' => round(self::MIN_SCALE + (1 - self::MIN_SCALE) * $k / ($n - 1), 2),
            ]);
            $order = $pieces->pluck('id')->when($down, fn ($ids) => $ids->reverse())->values()->all();
            // Shuffled, but never already in order.
            do {
                $shuffled = $pieces->shuffle()->values();
            } while ($shuffled->pluck('id')->all() === $order);

            // "kicsi, nagyobb, még nagyobb, a legnagyobb": the comparison words, said at the end.
            $words = $down
                ? ['nagy', 'kisebb', ...array_fill(0, $n - 3, 'még kisebb'), 'a legkisebb']
                : ['kicsi', 'nagyobb', ...array_fill(0, $n - 3, 'még nagyobb'), 'a legnagyobb'];

            $prompt = match (true) {
                $down => 'Most fordítva! Kezdd a legnagyobbal, és haladj a legkisebbig!',
                $r === 0 => 'Rakd sorba a képeket kicsitől a nagyig! Koppints először a legkisebbre!',
                default => 'Kicsitől a nagyig! Melyik a legkisebb?',
            };

            $rounds[] = $this->round('order', $prompt, [
                'items' => $shuffled->all(),
                'order' => $order,
                'wrong' => $down
                    ? 'Ez még nem a legnagyobb. Keresd meg a legnagyobbat a maradékból!'
                    : 'Ez még nem a legkisebb. Keresd meg a legkisebbet a maradékból!',
                'onCorrect' => 'Ügyes! '.$this->ucfirst(implode(', ', $words)).'!',
            ], $item->id);
        }

        return $rounds;
    }
}
