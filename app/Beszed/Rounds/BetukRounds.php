<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Letters: which letter does the word start with, and (from level 2) which picture starts with the letter.
 * $level = the letters in play: 1 → the plain ones, 2 → + the long vowels, 3 → + the two-letter sounds (cs, sz, gy…).
 * Each content item is one picture word with its first letter.
 */
class BetukRounds extends RoundFactory
{
    /** Letters whose name starts with a vowel take "az" (az f, az l, az sz…). */
    private const AZ = ['a', 'á', 'e', 'é', 'i', 'í', 'o', 'ó', 'ö', 'ő', 'u', 'ú', 'ü', 'ű', 'f', 'l', 'm', 'n', 'r', 's', 'x', 'sz', 'ly', 'ny'];

    public function build(Collection $items, int $level, int $count): array
    {
        $pool = $items->filter(fn ($i) => ($i->level ?? 1) <= $level)->values();
        $pool = $pool->groupBy(fn ($i) => $i->payload['letter'])->count() >= 4 ? $pool : $items;
        $this->favorLevel($pool, $level);
        $byLetter = $pool->groupBy(fn ($i) => $i->payload['letter']);
        $rounds = [];
        $last = null;

        for ($r = 0; $r < $count && $byLetter->count() >= 3; $r++) {
            $letter = $last = $this->pickNot($byLetter->keys()->all(), $last);
            $word = $this->weightedShuffle($byLetter[$letter])->first();
            $others = $byLetter->keys()->reject(fn ($l) => $l === $letter)->shuffle()->take(2)->values();

            $rounds[] = ($level >= 2 && $r % 2 === 1)
                ? $this->pictureRound($byLetter, $letter, $word, $others)
                : $this->letterRound($letter, $word, $others);
        }

        return $rounds;
    }

    /** "A a" for a letter: the capital and the small one, as on a school sheet. */
    public static function show(string $letter): string
    {
        return mb_strtoupper(mb_substr($letter, 0, 1)).mb_substr($letter, 1).' '.$letter;
    }

    private function named(string $letter): string
    {
        return (in_array($letter, self::AZ, true) ? 'az' : 'a')." „{$letter}”";
    }

    private function letterRound(string $letter, $word, Collection $others): array
    {
        $w = $word->payload['word'];
        $opts = $others->push($letter)->shuffle()->values();

        return $this->round('choice',
            "Melyik betűvel kezdődik {$this->art($w)} {$w}?", [
                'stimulus' => ['emoji' => $word->payload['emoji'], 'label' => $w, 'say' => $w, 'highlight' => true],
                'layout' => 'three',
                'options' => $opts->map(fn ($l) => ['id' => $l, 'letter' => self::show($l)])->all(),
                'answer' => $letter,
                'onCorrect' => "Igen! {$this->ucfirst($w)}: {$this->named($letter)} betűvel kezdődik!",
                'onWrong' => 'Nem ez az. Mondd ki a szót lassan, és figyeld az elejét!',
            ], $word->id);
    }

    private function pictureRound(Collection $byLetter, string $letter, $word, Collection $others): array
    {
        $opts = $others->map(fn ($l) => $byLetter[$l]->random())->push($word)->shuffle()->values();

        return $this->round('choice',
            "Melyik szó kezdődik {$this->named($letter)} betűvel?", [
                'stimulus' => ['letter' => self::show($letter), 'emoji' => ''],
                'layout' => 'three',
                'options' => $opts->map(fn ($o) => [
                    'id' => (string) $o->id, 'emoji' => $o->payload['emoji'], 'label' => $o->payload['word'],
                    'say' => $o->payload['word'],
                ])->all(),
                'answer' => (string) $word->id,
                'onCorrect' => "Igen! {$this->ucfirst($word->payload['word'])}: {$this->named($letter)} betűvel kezdődik!",
                'onWrong' => $opts->mapWithKeys(fn ($o) => [
                    (string) $o->id => "{$this->ucfirst($o->payload['word'])}. Az nem ezzel a betűvel kezdődik. Próbáld újra!",
                ])->all(),
            ], $word->id);
    }
}
