<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Mouth-muscle exercises (orofacial gymnastics), done in front of a mirror with a parent: the picture
 * alternates between the exercise's moves and the child taps once per repetition done.
 * $level (1–100) scales repetitions continuously from three at level 1 to ten at level 100 (what a
 * speech therapist's sheet asks for, at the old levels 1 and 3) — every count from 3 to 10 is reachable,
 * said with its own Hungarian multiplier word (SAY).
 * With the mirror on, the device's face tracker can count the repetitions itself: each move carries the
 * `pose` its picture shows (POSES), and the client (engines/mimic/poses.js) watches for it.
 */
class SzajtornaRounds extends RoundFactory
{
    /** Repetitions, said ("háromszor", "négyszer"…). */
    public const SAY = [3 => 'háromszor', 4 => 'négyszer', 5 => 'ötször', 6 => 'hatszor', 7 => 'hétszer', 8 => 'nyolcszor', 9 => 'kilencszer', 10 => 'tízszer'];

    /**
     * A move's picture → the face movement the tracker watches for (the keys of POSES in
     * engines/mimic/poses.js). Taken from the picture, so content edited in the editor keeps working.
     * Moves with no pose here (resting, cheeks sucked in: no score shows them) are not watched.
     */
    public const POSES = [
        '🐡' => 'puff',
        '😗' => 'pucker', '😘' => 'pucker', '😙' => 'pucker', '😚' => 'pucker',
        '😁' => 'smile', '😊' => 'smile', '😄' => 'smile',
        '😢' => 'frown', '🙁' => 'frown', '☹️' => 'frown',
        '😮' => 'open', '😲' => 'open', '😯' => 'open',
        '🤐' => 'roll', '🧙‍♀️' => 'roll',
        '😬' => 'rollLower', '😶' => 'rollUpper',
    ];

    /** The pose a move's picture shows: an arrow makes it a sideways move ("😗➡️"), else the picture itself. */
    public static function pose(string $emoji): ?string
    {
        return match (true) {
            str_contains($emoji, '➡') => 'right',
            str_contains($emoji, '⬅') => 'left',
            default => self::POSES[$emoji] ?? null,
        };
    }

    public function build(Collection $items, int $level, int $count): array
    {
        $reps = $this->scaleInt($level, 3, 10);
        $times = self::SAY[$reps];
        $rounds = [];

        foreach ($this->cycle($items->values(), $count)->values() as $r => $exercise) {
            $p = $exercise->payload;
            $moves = collect($p['moves'])->map(fn ($m) => ['emoji' => $m[0], 'label' => $m[1], 'pose' => self::pose($m[0])])->values()->all();
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
