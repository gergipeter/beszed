<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Piano: eight keys, do–re–mi–fa–szó–la–ti–do, low to high. Basic music
 * education in three kinds of round: find the named/heard note, say which of
 * two notes is higher or lower, and play a short tune back. The content items
 * are the eight notes, so a note the child keeps missing comes back sooner.
 * $level 1: coloured, named keys · 2: named keys and the note is played · 3: by ear only, closer notes, longer tunes.
 */
class ZongoraRounds extends RoundFactory
{
    private const KEYS = ['DO', 'RE', 'MI', 'FA', 'SZÓ', 'LA', 'TI', 'DO'];

    /** What each of the session's rounds is, in turn. */
    private const MODES = ['find', 'compare', 'echo', 'find', 'echo'];

    public function build(Collection $items, int $level, int $count): array
    {
        $level = max(1, min(3, $level));
        $notes = $this->cycle($items, $count)->values();
        $rounds = [];

        foreach ($notes as $r => $it) {
            $mode = self::MODES[$r % count(self::MODES)];
            $base = [
                'keys' => array_map(fn ($label) => ['label' => $label], self::KEYS),
                'colors' => $level === 1,
                'labels' => $level < 3,
            ];
            $rounds[] = match ($mode) {
                'find' => $this->find($it, $level, $base),
                'compare' => $this->compare($it, $level, $base),
                default => $this->echo($it, $level, $base, $r === 0),
            };
        }

        return $rounds;
    }

    private function find($it, int $level, array $base): array
    {
        $note = (int) $it->payload['note'];
        $name = $this->ucfirst($it->payload['name']);
        $prompt = $level >= 3
            ? 'Hallgasd meg a hangot, és keresd meg a zongorán!'
            : "Keresd meg a {$name} hangot! Koppints arra a billentyűre!";

        return $this->round('piano', $prompt, $base + [
            'mode' => 'find',
            'target' => $note,
            'hear' => $level >= 2,
            'onCorrect' => $level >= 3 ? 'Jó füled van! Ez volt az!' : "Igen, ez a {$name}!",
        ], $it->id);
    }

    private function compare($it, int $level, array $base): array
    {
        $minGap = [1 => 4, 2 => 2, 3 => 1][$level];
        $a = random_int(0, 7);
        do {
            $b = random_int(0, 7);
        } while (abs($a - $b) < $minGap);
        $higher = random_int(0, 1) === 1;
        $answer = $higher ? ($a > $b ? '0' : '1') : ($a < $b ? '0' : '1');

        return $this->round('piano', 'Két hangot hallasz. Melyik volt '.($higher ? 'magasabb' : 'mélyebb').'?', $base + [
            'mode' => 'compare',
            'notes' => [$a, $b],
            'answer' => $answer,
            'onCorrect' => $higher ? 'Igen, az volt a magasabb hang!' : 'Igen, az volt a mélyebb hang!',
        ], $it->id);
    }

    private function echo($it, int $level, array $base, bool $first): array
    {
        $n = [1 => 2, 2 => 3, 3 => random_int(4, 5)][$level];
        $top = $level === 1 ? 4 : 7; // the first five keys are enough for the smallest
        $melody = [random_int(0, $top)];
        while (count($melody) < $n) {
            $next = random_int(0, $top);
            if ($next !== end($melody)) {
                $melody[] = $next;
            }
        }

        return $this->round('piano', $first
            ? 'Figyelj! Lejátszom egy kis dallamot. Utána te jössz: játszd le ugyanazt a zongorán!'
            : 'Figyelj! Most '.self::NUM[$n].' hang jön.', $base + [
            'mode' => 'echo',
            'melody' => $melody,
            'onCorrect' => 'Szuper! Pontosan ezt játszottam!',
            'replayParts' => ['Figyeld újra!'],
        ], $it->id);
    }
}
