<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Number-line hopping: an animal hops along numbered pads. Rounds alternate:
 * the child hops it themself ("ugorj 3-at előre") or watches it hop and taps the
 * pad it lands on. The adaptive level (1–100) splits into three equal tiers
 * (tier()): tier 1: forward only, pads 0–5 growing to 0–10 and the hop size
 * growing from 3 to 4 across the tier · tier 2: forward and back, pads 0–10 ·
 * tier 3: also two-part hops ("3-at előre, aztán 1-et vissza"). The top of
 * the pad range (and so the hop size) is reached by the end of tier 1 and
 * stays there for tiers 2–3.
 */
class BekaRounds extends RoundFactory
{
    /** A number of hops in the accusative, as it is spoken: "ugorj kettőt előre". */
    private const ACC = [1 => 'egyet', 2 => 'kettőt', 3 => 'hármat', 4 => 'négyet'];

    public function build(Collection $items, int $level, int $count): array
    {
        $tier = $this->tier($level, 3);
        // pads and hop size both reach their ceiling by the end of tier 1 (level ~33), tiers 2-3 stay maxed
        $max = $this->scaleInt($level, 5, 10, 33);
        $hopMax = $this->scaleInt($level, 3, 4, 33);
        $backward = $tier >= 2;
        $twoPart = $tier >= 3;

        return $this->cycle($items, $count)->map(function ($it, $i) use ($backward, $twoPart, $max, $hopMax) {
            // the first round of a session is always a hop the child does; then in turn
            return $i % 2 === 0 ? $this->hop($it, $backward, $twoPart, $max, $hopMax) : $this->land($it, $backward, $twoPart, $max, $hopMax);
        })->values()->all();
    }

    /** @return array{0: int, 1: list<int>} start pad and the signed moves, all staying on the pads */
    private function moves(bool $backward, bool $twoPart, int $max, int $hopMax, int $parts): array
    {
        $pos = random_int(0, $backward ? $max - 3 : 2);
        $start = $pos;
        $moves = [];
        for ($k = 0; $k < $parts; $k++) {
            $options = [];
            foreach (range(1, $hopMax) as $n) {
                if ($pos + $n <= $max) {
                    $options[] = $n;
                }
                if ($backward && $pos - $n >= 0) {
                    $options[] = -$n;
                }
            }
            // the second part of a two-part hop goes the other way: forward, then back
            if ($k === 1) {
                $options = array_values(array_filter($options, fn ($n) => $n < 0)) ?: $options;
            }
            $m = $options[array_rand($options)];
            $moves[] = $m;
            $pos += $m;
        }

        return [$start, $moves];
    }

    private function hop($it, bool $backward, bool $twoPart, int $max, int $hopMax): array
    {
        [$start, $moves] = $this->moves($backward, $twoPart, $max, $hopMax, 1);
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

    private function land($it, bool $backward, bool $twoPart, int $max, int $hopMax): array
    {
        [$start, $moves] = $this->moves($backward, $twoPart, $max, $hopMax, $twoPart ? 2 : 1);
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
