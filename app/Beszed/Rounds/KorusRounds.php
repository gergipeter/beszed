<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Animal choir (a Simon game): four animals sing one after another, each with
 * its own note; the child taps them in the same order. The same four sing the
 * whole session, so their places and notes can be learnt.
 *
 * $level (1-100) scales the sequence length from 2 notes up to a cap of 8: a
 * Simon run longer than that is unplayable for a small child (NUM, the spoken
 * number list, only reaches "tíz" anyway) and keeps climbing feels unfair once
 * recall itself is the limit. The length reaches 8 by level 60 and holds there;
 * from level 60 to 100 difficulty keeps rising through pace instead — the
 * replay hint ("Figyeld újra!") is offered only below level 60, so the back
 * half of the range asks the child to remember the tune in one listen.
 */
class KorusRounds extends RoundFactory
{
    private const PADS = 4;

    /** Sequence length saturates at this many notes (see class doc). */
    private const MAX_NOTES = 8;

    /** $level at which the note count has fully saturated and pace/no-replay takes over. */
    private const SATURATE_AT = 60;

    public function build(Collection $items, int $level, int $count): array
    {
        $pads = $items->shuffle()->take(self::PADS)->values()
            ->map(fn ($i) => ['id' => (string) $i->id, 'emoji' => $i->payload['emoji'], 'label' => $i->payload['name']]);
        $ids = $pads->pluck('id')->all();
        $n = $this->scaleInt(min($level, self::SATURATE_AT), 2, self::MAX_NOTES, self::SATURATE_AT);
        $allowReplayHint = $level < self::SATURATE_AT;
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
                'replayParts' => $allowReplayHint ? ['Figyeld újra!'] : [],
            ]);
        }

        return $rounds;
    }
}
