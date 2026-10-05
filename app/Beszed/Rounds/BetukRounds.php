<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Letters: which letter does the word start with, and (from tier 2) which picture starts with the letter.
 * $level spans 1–100 (RoundFactory::tier(), 3 bands matching the content's own 1–3 `level` field): tier 1
 * (levels 1–33) → the plain letters, tier 2 (34–66) → + the long vowels, tier 3 (67–100) → + the two-letter
 * sounds (cs, sz, gy…). favorLevel()/the pool filter track that tier, not a hard content level, so the mix
 * of letters widens gradually across a tier instead of jumping only at its first level. Within tiers 2–3
 * the picture-from-letter round (vs. letter-from-word) also grows from occasional to as common as the old
 * "every other round". Each content item is one picture word with its first letter.
 */
class BetukRounds extends RoundFactory
{
    /** Letters whose name starts with a vowel take "az" (az f, az l, az sz…). */
    private const AZ = ['a', 'á', 'e', 'é', 'i', 'í', 'o', 'ó', 'ö', 'ő', 'u', 'ú', 'ü', 'ű', 'f', 'l', 'm', 'n', 'r', 's', 'x', 'sz', 'ly', 'ny'];

    public function build(Collection $items, int $level, int $count): array
    {
        $tier = $this->tier($level, 3);
        $pool = $items->filter(fn ($i) => ($i->level ?? 1) <= $tier)->values();
        $pool = $pool->groupBy(fn ($i) => $i->payload['letter'])->count() >= 4 ? $pool : $items;
        $this->favorLevel($pool, $level);
        $byLetter = $pool->groupBy(fn ($i) => $i->payload['letter']);
        // tier 1: never the picture round; tiers 2–3: a growing share of rounds pick the picture from the letter.
        $pictureShare = $tier === 1 ? 0.0 : $this->scale($level, 0.3, 0.6);
        $rounds = [];
        $last = null;

        for ($r = 0; $r < $count && $byLetter->count() >= 3; $r++) {
            $letter = $last = $this->pickNot($byLetter->keys()->all(), $last);
            $word = $this->weightedShuffle($byLetter[$letter])->first();
            $others = $byLetter->keys()->reject(fn ($l) => $l === $letter)->shuffle()->take(2)->values();

            $rounds[] = ($tier >= 2 && (mt_rand() / mt_getrandmax()) < $pictureShare)
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
