<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Number-line hopping: an animal hops along numbered pads. Rounds alternate:
 * the child hops it themself ("ugorj 3-at előre") or watches it hop and taps the
 * pad it lands on. Level 1: pads 0–5, forward only · 2: pads 0–10, forward and
 * back · 3: pads 0–10, and two-part hops ("3-at előre, aztán 1-et vissza").
 */
class BekaRounds extends RoundFactory
{
    /** A number of hops in the accusative, as it is spoken: "ugorj kettőt előre". */
    private const ACC = [1 => 'egyet', 2 => 'kettőt', 3 => 'hármat', 4 => 'négyet'];

    public function build(Collection $items, int $level, int $count): array
    {
        $level = max(1, min(3, $level));
        $max = $level === 1 ? 5 : 10;

        return $this->cycle($items, $count)->map(function ($it, $i) use ($level, $max) {
            // the first round of a session is always a hop the child does; then in turn
            return $i % 2 === 0 ? $this->hop($it, $level, $max) : $this->land($it, $level, $max);
        })->values()->all();
    }

    /** @return array{0: int, 1: list<int>} start pad and the signed moves, all staying on the pads */
    private function moves(int $level, int $max, int $parts): array
    {
        $pos = random_int(0, $level === 1 ? 2 : $max - 3);
        $start = $pos;
        $moves = [];
        for ($k = 0; $k < $parts; $k++) {
            $options = [];
            foreach (range(1, $level === 1 ? 3 : 4) as $n) {
                if ($pos + $n <= $max) {
                    $options[] = $n;
                }
                if ($level > 1 && $pos - $n >= 0) {
                    $options[] = -$n;
                }
            }
            // the second part of a level-3 hop goes the other way: forward, then back
            if ($k === 1) {
                $options = array_values(array_filter($options, fn ($n) => $n < 0)) ?: $options;
            }
            $m = $options[array_rand($options)];
            $moves[] = $m;
            $pos += $m;
        }

        return [$start, $moves];
    }

    private function hop($it, int $level, int $max): array
    {
        [$start, $moves] = $this->moves($level, $max, 1);
        $n = $moves[0];
        $p = $it->payload;

        return $this->round('hop', "A {$p['name']} a {$start}-as számon áll. Ugrasd ".self::ACC[abs($n)].($n > 0 ? ' előre' : ' hátra').'! Aztán koppints a kész gombra!', [
            'mode' => 'hop',
            'emoji' => $p['emoji'],
            'max' => $max,
            'start' => $start,
            'moves' => $moves,
            'onCorrect' => "Igen! {$start} után ".abs($n).' ugrással a '.($start + $n).'-es számra érkezett!',
        ], $it->id);
    }

    private function land($it, int $level, int $max): array
    {
        [$start, $moves] = $this->moves($level, $max, $level === 3 ? 2 : 1);
        $end = $start + array_sum($moves);
        $p = $it->payload;
        $say = collect($moves)->map(fn ($n) => self::ACC[abs($n)].($n > 0 ? ' előre' : ' hátra'))->implode(', aztán ');

        return $this->round('hop', "Nézd, merre ugrik a {$p['name']}! Melyik számra érkezik?", [
            'mode' => 'land',
            'emoji' => $p['emoji'],
            'max' => $max,
            'start' => $start,
            'moves' => $moves,
            'onCorrect' => "Igen! A {$end}-es számra érkezett!",
            'onWrong' => "Nem ide. A {$p['name']} a {$start}-as számról indult, nézd meg újra!",
            'replayParts' => ['Figyeld újra!'],
            'said' => "Ugrik {$say}.",
        ], $it->id);
    }
}
