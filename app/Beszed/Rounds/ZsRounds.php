<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * zs or s? Hungarian has far fewer everyday zs words than s words, so a draw from
 * the whole pool would make "s" the right answer four times in five and a child who
 * always taps "s" would look good. Half of every session is zs, half is s, whatever
 * the pool sizes (the odd round of an odd count goes either way), in random order.
 *
 * $level spans 1–100 and only drives word difficulty here (RoundFactory::favorLevel(), tiered against the
 * content's own 1–3 `level` field: short/common words at low levels, long/rare ones as $level climbs) —
 * the zs/s split itself stays an even 50/50 at every level, so the sound contrast is never made easier by
 * skewing the draw.
 */
class ZsRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);

        $zs = $items->filter(fn ($it) => $it->payload['sound'] === 'zs')->values();
        $s = $items->filter(fn ($it) => $it->payload['sound'] === 's')->values();
        $wantZs = match (true) {
            $zs->isEmpty() => 0,
            $s->isEmpty() => $count,
            default => intdiv($count, 2) + ($count % 2 ? random_int(0, 1) : 0),
        };
        $picked = $this->cycle($zs, $wantZs)->merge($this->cycle($s, $count - $wantZs))->shuffle()->values();

        return $picked->map(function ($it) {
            $p = $it->payload;
            $w = $p['word'];

            return $this->round('choice', "Figyelj! {$w}. Zümmögő zs van benne, vagy csendes s?", [
                'stimulus' => ['emoji' => $p['emoji'], 'label' => $w, 'say' => $w],
                'layout' => 'two',
                'options' => [
                    ['id' => 'zs', 'emoji' => '🐝', 'label' => 'zs'],
                    ['id' => 's', 'emoji' => '🤫', 'label' => 's'],
                ],
                'answer' => $p['sound'],
                'onCorrect' => $p['sound'] === 'zs' ? "Igen! {$w}, zümmögő zs!" : "Igen! {$w}, csendes s!",
                'onWrong' => "Figyeld még egyszer: {$w}",
            ], $it->id);
        })->values()->all();
    }
}
