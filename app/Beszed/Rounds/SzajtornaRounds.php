<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Mouth-muscle exercises (orofacial gymnastics), done in front of a mirror with a parent: the picture
 * alternates between the exercise's moves and the child taps once per repetition done.
 * $level = repetitions: 1 → three, 2 → five, 3 → ten (what a speech therapist's sheet asks for).
 */
class SzajtornaRounds extends RoundFactory
{
    /** level → [repetitions, how to say it ("háromszor")] */
    public const REPS = [1 => [3, 'háromszor'], 2 => [5, 'ötször'], 3 => [10, 'tízszer']];

    public function build(Collection $items, int $level, int $count): array
    {
        [$reps, $times] = self::REPS[$level] ?? self::REPS[1];
        $rounds = [];

        foreach ($this->cycle($items->values(), $count)->values() as $r => $exercise) {
            $p = $exercise->payload;
            $moves = collect($p['moves'])->map(fn ($m) => ['emoji' => $m[0], 'label' => $m[1]])->values()->all();
            $how = $r === 0 ? 'Nézz tükörbe, és csináld utánam!' : 'Csináld utánam!';

            $rounds[] = $this->round('mimic', "{$p['name']}. $how {$this->ucfirst($times)}.", [
                'name' => $p['name'],
                'moves' => $moves,
                'reps' => $reps,
                'onCorrect' => 'Ügyes vagy! Dolgoztak a szájizmaid!',
            ], $exercise->id, [$p['name'].'.', $how, $this->ucfirst($times).'.']);
        }

        return $rounds;
    }
}
