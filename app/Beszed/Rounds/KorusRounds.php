<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Animal choir (a Simon game): four animals sing one after another, each with
 * its own note; the child taps them in the same order. The same four sing the
 * whole session, so their places and notes can be learnt. $level = notes (2–7).
 */
class KorusRounds extends RoundFactory
{
    private const PADS = 4;

    public function build(Collection $items, int $level, int $count): array
    {
        $pads = $items->shuffle()->take(self::PADS)->values()
            ->map(fn ($i) => ['id' => (string) $i->id, 'emoji' => $i->payload['emoji'], 'label' => $i->payload['name']]);
        $ids = $pads->pluck('id')->all();
        $n = max(2, $level);
        $rounds = [];

        for ($r = 0; $r < $count; $r++) {
            // No animal three times in a row: that is a pause for the child, not a tune.
            $order = [];
            while (count($order) < $n) {
                $next = $ids[array_rand($ids)];
                $k = count($order);
                if ($k >= 2 && $order[$k - 1] === $next && $order[$k - 2] === $next) {
                    continue;
                }
                $order[] = $next;
            }

            $prompt = $r === 0
                ? 'Figyelj! Az állatok egymás után énekelnek. Utána te jössz: koppints rájuk ugyanabban a sorrendben!'
                : 'Figyelj! Most '.self::NUM[$n].' hang jön.';

            $rounds[] = $this->round('simon', $prompt, [
                'pads' => $pads->all(),
                'order' => $order,
                'onCorrect' => 'Szuper! Pontosan így énekeltek!',
                'replayParts' => ['Figyeld újra!'],
            ]);
        }

        return $rounds;
    }
}
