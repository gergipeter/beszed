<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Numbers to ten, the way school-readiness checks ask them: how many are there,
 * which number comes next, one more or one less, the biggest or smallest number.
 * $level (1–100) scales the number range continuously, from 1–5 at level 1 up
 * to 1–10 from level 67 up (the old level-3 range, reached early enough that
 * most of the band is spent consolidating it, not still growing). Counting
 * backwards (in "next") and taking away (in "more") start only in the top
 * third (67–100, the old level 3's qualitative addition), each picked at
 * random half the time there; the "biggest/smallest" gap between the three
 * numbers shrinks from 2 to 1 over the same span, so picking the odd one out
 * gets harder as the range fills in.
 */
class SzamokRounds extends RoundFactory
{
    private const MODES = ['count', 'next', 'more', 'biggest'];

    public function build(Collection $items, int $level, int $count): array
    {
        $level = max(1, min(100, $level));
        $max = $this->scaleInt($level, 5, 10, 100, 0.6);
        $hard = $this->tier($level, 3) === 3;

        return $this->cycle($items, $count)->map(function ($it, $i) use ($level, $max, $hard) {
            return match (self::MODES[$i % count(self::MODES)]) {
                'count' => $this->count($it, $max),
                'next' => $this->next($it, $hard, $max),
                'more' => $this->more($it, $hard, $max),
                default => $this->biggest($it, $level, $max),
            };
        })->values()->all();
    }

    private function count($it, int $max): array
    {
        $n = random_int(1, $max);
        $f = $it->payload;

        return $this->round('choice', "Hány {$f['name']} van a képen? Számold meg!", $this->numbers($n, $max) + [
            'sequence' => array_fill(0, $n, $f['emoji']),
            'noMissing' => true,
            'onCorrect' => 'Igen! '.self::NUM[$n]." {$f['name']} van a képen!",
            'onWrong' => 'Számold meg újra, egyesével!',
        ], $it->id);
    }

    private function next($it, bool $hard, int $max): array
    {
        // top tier only, sometimes counts backwards
        $down = $hard && random_int(0, 1) === 1;
        $len = 3;
        $start = $down ? random_int($len + 1, $max) : random_int(1, $max - $len);
        $row = array_map(fn ($k) => (string) ($down ? $start - $k : $start + $k), range(0, $len - 1));
        $answer = $down ? $start - $len : $start + $len;

        return $this->round('choice', $down ? 'Visszafelé számolunk. Melyik szám jön ezután?' : 'Melyik szám jön ezután?', $this->numbers($answer, $max, 0) + [
            'sequence' => $row,
            'onCorrect' => "Igen, utána jön a {$answer}!",
            'onWrong' => 'Mondd el fejben a számokat egymás után!',
        ], $it->id);
    }

    private function more($it, bool $hard, int $max): array
    {
        $f = $it->payload;
        $d = $hard && random_int(0, 1) === 1 ? -1 : 1;
        $n = $d === 1 ? random_int(1, $max - 1) : random_int(2, $max);
        $answer = $n + $d;

        $prompt = $d === 1
            ? "Van {$n} {$f['name']}. Kapsz még egyet. Hány lesz összesen?"
            : "Van {$n} {$f['name']}. Elveszel egyet. Hány marad?";

        return $this->round('choice', $prompt, $this->numbers($answer, $max) + [
            'sequence' => array_fill(0, $n, $f['emoji']),
            'noMissing' => true,
            'onCorrect' => "Igen, {$answer} lesz!",
            'onWrong' => 'Számold meg a képet, aztán gondold végig!',
        ], $it->id);
    }

    private function biggest($it, int $level, int $max): array
    {
        $gap = $this->scaleInt($level, 2, 1);
        do {
            $nums = collect(range(1, $max))->shuffle()->take(3)->sort()->values()->all();
        } while ($nums[2] - $nums[1] < $gap || $nums[1] - $nums[0] < $gap);
        $big = random_int(0, 2) > 0; // mostly "biggest", sometimes "smallest"
        $answer = $big ? $nums[2] : $nums[0];
        $word = $big ? 'legnagyobb' : 'legkisebb';

        return $this->round('choice', "Melyik a {$word} szám?", [
            'layout' => 'three',
            'options' => collect($nums)->shuffle()->values()->map(fn ($n) => ['id' => (string) $n, 'emoji' => (string) $n])->all(),
            'answer' => (string) $answer,
            'onCorrect' => "Igen, a {$answer} a {$word}!",
            'onWrong' => 'Gondold végig: melyik szám van a legközelebb az egyhez?',
        ], $it->id);
    }

    /** Three numeral choices: the answer and two other numbers in range. */
    private function numbers(int $answer, int $max, int $min = 1): array
    {
        $others = collect(range($min, $max))->reject(fn ($n) => $n === $answer)->shuffle()->take(2);

        return [
            'layout' => 'three',
            'options' => collect([$answer, ...$others])->shuffle()->values()->map(fn ($n) => ['id' => (string) $n, 'emoji' => (string) $n])->all(),
            'answer' => (string) $answer,
        ];
    }
}
