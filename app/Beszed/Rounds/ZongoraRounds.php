<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Piano: eight keys, do–re–mi–fa–szó–la–ti–do, low to high. Basic music
 * education in three kinds of round: find the named/heard note, say which of
 * two notes is higher or lower, and play a short tune back. The content items
 * are the eight notes, so a note the child keeps missing comes back sooner.
 *
 * $level spans 1–100: colours and labels fade out over tier 1 (RoundFactory::tier(), levels 1–33: coloured,
 * named keys), the note is played alongside the name over tier 2 (34–66), and by tier 3 (67–100) it is by
 * ear only; within that span compare()'s minimum gap between the two notes and echo()'s tune length both
 * shrink/grow smoothly (scale()) rather than jumping at the tier edges, so levels close to 100 ask for
 * closer notes and longer tunes than the old level 3 ever did.
 */
class ZongoraRounds extends RoundFactory
{
    private const KEYS = ['DO', 'RE', 'MI', 'FA', 'SZÓ', 'LA', 'TI', 'DO'];

    /** What each of the session's rounds is, in turn. */
    private const MODES = ['find', 'compare', 'echo', 'find', 'echo'];

    public function build(Collection $items, int $level, int $count): array
    {
        $tier = $this->tier($level, 3);
        $notes = $this->cycle($items, $count)->values();
        $rounds = [];

        foreach ($notes as $r => $it) {
            $mode = self::MODES[$r % count(self::MODES)];
            $base = [
                'keys' => array_map(fn ($label) => ['label' => $label], self::KEYS),
                'colors' => $tier === 1,
                'labels' => $tier < 3,
            ];
            $rounds[] = match ($mode) {
                'find' => $this->find($it, $level, $tier, $base),
                'compare' => $this->compare($it, $level, $base),
                default => $this->echo($it, $level, $base, $r === 0),
            };
        }

        return $rounds;
    }

    private function find($it, int $level, int $tier, array $base): array
    {
        $note = (int) $it->payload['note'];
        $name = $this->ucfirst($it->payload['name']);
        $prompt = $tier >= 3
            ? 'Hallgasd meg a hangot, és keresd meg a zongorán!'
            : "Keresd meg a {$name} hangot! Koppints arra a billentyűre!";

        return $this->round('piano', $prompt, $base + [
            'mode' => 'find',
            'target' => $note,
            'hear' => $tier >= 2,
            'onCorrect' => $tier >= 3 ? 'Jó füled van! Ez volt az!' : "Igen, ez a {$name}!",
        ], $it->id);
    }

    private function compare($it, int $level, array $base): array
    {
        // 4 at level 1, down to 1 (the smallest possible step) well before level 100.
        $minGap = $this->scaleInt($level, 4, 1, 100, 0.6);
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
        // 2 notes at level 1, up to 7 by level 100 (the old level 3 topped out at 4–5).
        $n = $this->scaleInt($level, 2, 7);
        $top = $level < 17 ? 4 : 7; // the first five keys are enough for the easiest band
        $melody = [random_int(0, $top)];
        while (count($melody) < $n) {
            $next = random_int(0, $top);
            if ($next !== end($melody)) {
                $melody[] = $next;
            }
        }

        return $this->round('piano', $first
            ? 'Figyelj! Lejátszom egy kis dallamot. Utána te jössz: játszd le ugyanazt a zongorán!'
            : 'Figyelj! Most '.(self::NUM[$n] ?? $n).' hang jön.', $base + [
            'mode' => 'echo',
            'melody' => $melody,
            'onCorrect' => 'Szuper! Pontosan ezt játszottam!',
            'replayParts' => ['Figyeld újra!'],
        ], $it->id);
    }
}
