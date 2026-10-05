<?php

namespace App\Beszed\Rounds;

use App\Beszed\Content\Hungarian;
use Illuminate\Support\Collection;

/**
 * Hangvonat: where is the sound in the word — at the start, in the middle or at the end?
 * The word is a train of three cars; the child taps the car where the sound is.
 * Tier 1 (levels 1-33): start or end only (two trains). Tier 2 (34-66): start, middle or end
 * (three trains). Tier 3 (67-100): a session of two look-alike sounds (s and sz, or z and zs)
 * side by side, so the child must listen for the one asked, not just recognise it. Within every
 * tier the content pool is biased towards harder (content-level) items the higher $level climbs.
 *
 * The voices (Azure and Piper) spell out a sound written on its own ("sss" → "es es es"), so a sound
 * is named the way the voice can say it: by its letter ("az sz hangot" → "az esz hangot"), with a
 * helper picture of the sound the first time for the ones that have a familiar one (a kígyó sziszeg).
 */
class HangvonatRounds extends RoundFactory
{
    /** Sounds that can be held long (no stops: those can't be heard apart from their vowel). */
    public const SOUNDS = ['s', 'sz', 'z', 'zs', 'm', 'n', 'l', 'r', 'f', 'v', 'j'];

    /** Look-alike sounds: an item with one of these beside the target is a level-3 item (the sound asked is the one to find). */
    public const LOOKALIKES = ['s' => ['sz', 'zs', 'cs'], 'sz' => ['s', 'z', 'c'], 'z' => ['zs', 'sz'], 'zs' => ['z', 's'], 'j' => ['ly']];

    /** Level 3 practises one of these pairs per session. */
    private const PAIRS = [['s', 'sz'], ['z', 'zs']];

    /** Said after the sound's name the first time it comes up in a session. */
    private const HELPER = [
        'sz' => 'a kígyó sziszegését',
        'r' => 'amit a kutya morog',
        'm' => 'amit akkor mondunk, ha valami nagyon finom',
    ];

    /**
     * The word's three cars, left to right: the green one is where the sound is. Shown on "plates" (the
     * only tiles that line an `emojis` list up in a row, small enough for three on a phone).
     */
    public const TRAINS = ['start' => ['🟩', '⬜', '⬜'], 'middle' => ['⬜', '🟩', '⬜'], 'end' => ['⬜', '⬜', '🟩']];

    public const PLACES = ['start' => 'az elején', 'middle' => 'a közepén', 'end' => 'a végén'];

    private const PRAISE = ['Igen!', 'Így van!', 'Ügyes vagy!', 'Pontosan!', 'Szuper!'];

    /** Doubled spellings (asszony): the same sound, said long. */
    private const LONG = ['ssz' => 'sz', 'zzs' => 'zs', 'ccs' => 'cs', 'lly' => 'ly', 'nny' => 'ny', 'ggy' => 'gy', 'tty' => 'ty', 'ddz' => 'dz', 'ddzs' => 'dzs'];

    private const VOICELESS = ['p', 't', 'k', 'sz', 's', 'f', 'c', 'cs', 'ty', 'h'];

    private const VOICED = ['b', 'd', 'g', 'z', 'zs', 'dz', 'dzs', 'gy'];

    /** The word's sounds, digraph-aware (szőlő → sz, ő, l, ő; a long "ssz" is one sz). @return list<string> */
    public static function sounds(string $word): array
    {
        return array_map(fn ($l) => self::LONG[$l['letter']] ?? $l['letter'], Hungarian::letters(trim($word)));
    }

    /** Where the sound is: start, middle or end; null if it isn't there exactly once. */
    public static function position(string $word, string $sound): ?string
    {
        $sounds = self::sounds($word);
        $at = array_keys(array_filter($sounds, fn ($s) => $s === $sound || ($sound === 'j' && $s === 'ly')));

        return match (true) {
            count($at) !== 1 => null,
            $at[0] === 0 => 'start',
            $at[0] === count($sounds) - 1 => 'end',
            default => 'middle',
        };
    }

    /**
     * Content rule: the sound is in the word exactly once, where `pos` says, and is said as written
     * (no neighbour that changes it: "vízpart" says s, "színpad" says m, "golyó" has a j sound in "ly").
     *
     * @return array<string, string>
     */
    public static function check(array $p): array
    {
        $sound = $p['sound'];
        $word = mb_strtolower(trim($p['word']));
        $sounds = self::sounds($word);
        $q = "„{$sound}”";
        $pos = self::position($word, $sound);
        if ($pos === null) {
            return ['word' => "A {$q} hang pontosan egyszer legyen a szóban (az sz nem s, a zs nem z, az ly j-nek hangzik)."];
        }
        if ($pos !== $p['pos']) {
            return ['pos' => "A {$q} a szó ".self::PLACES[$pos].' van.'];
        }
        $i = array_search($sound === 'j' && ! in_array('j', $sounds, true) ? 'ly' : $sound, $sounds, true);
        $next = $sounds[$i + 1] ?? null;
        $changed = match (true) {
            in_array($sound, ['z', 'zs', 'v'], true) => in_array($next, self::VOICELESS, true),
            in_array($sound, ['s', 'sz', 'f'], true) => in_array($next, self::VOICED, true),
            $sound === 'n' => in_array($next, ['b', 'p', 'm', 'gy', 'ty'], true),
            $sound === 'j' => in_array('ly', $sounds, true),
            $sound === 'm' => (bool) preg_match('/n[bp]/u', $word),
            default => false,
        };

        return $changed ? ['word' => "Ebben a szóban a {$q} másképp hangzik, mint ahogy írjuk (a szomszédja megváltoztatja). Válassz másikat!"] : [];
    }

    public function build(Collection $items, int $level, int $count): array
    {
        $tier = $this->tier($level, 3);
        $fits = fn ($i) => $tier > 1 || $i->payload['pos'] !== 'middle';
        $pool = $items->filter(fn ($i) => ($i->level ?? 1) <= $tier && $fits($i))->values();
        if ($pool->count() < $count) {
            $pool = $items->filter($fits)->values();
        }
        $this->favorLevel($pool, $level);
        $picked = $tier >= 3 ? $this->lookalikes($pool, $count) : $this->cycle($pool, $count);

        $rounds = [];
        $seen = [];
        $last = null;
        $praise = null;
        foreach ($picked->values() as $r => $item) {
            $p = $item->payload;
            $sound = $p['sound'];
            $word = $p['word'];
            $name = Hungarian::letterArticle($sound)." {$sound} hangot";
            $helper = ! isset($seen[$sound]) && isset(self::HELPER[$sound]) ? ', '.self::HELPER[$sound] : '';
            $question = match (true) {
                $r === 0 => "Figyeld meg, hol hallod {$name}{$helper}!",
                $tier >= 3 && $last !== null && $last !== $sound => 'Most '.Hungarian::letterArticle($sound)." {$sound} hangot keresd{$helper}! Hol hallod?",
                default => "Hol hallod {$name}{$helper}?",
            };
            $seen[$sound] = true;
            $last = $sound;
            $places = $tier === 1 ? ['start', 'end'] : ['start', 'middle', 'end'];
            $ask = $r === 0 ? ($tier === 1 ? 'Az elején vagy a végén?' : 'Az elején, a közepén vagy a végén?') : null;
            $praise = $this->pickNot(self::PRAISE, $praise);
            $spoken = $this->ucfirst($word).'.';

            $rounds[] = $this->round('choice', trim("$question $spoken ".($ask ?? '')), [
                'stimulus' => ['emoji' => $p['emoji'], 'label' => $word, 'say' => $word],
                // two big trains per row: a row of three cars needs the room (the third train goes below)
                'layout' => 'two',
                'variant' => 'plates',
                'options' => array_map(fn ($place) => ['id' => $place, 'emojis' => self::TRAINS[$place], 'label' => self::PLACES[$place]], $places),
                'answer' => $p['pos'],
                'onCorrect' => "{$praise} ".$this->ucfirst($word).': '.Hungarian::letterArticle($sound)." {$sound} ".self::PLACES[$p['pos']].' van.',
                'onWrong' => "Figyeld még egyszer: {$word}. Hol hallod {$name}?",
            ], $item->id, array_values(array_filter([$question, $spoken, $ask])));
        }

        return $rounds;
    }

    /** Level 3: two look-alike sounds take turns (s, sz, s, sz…), so the child must listen for the one asked. */
    private function lookalikes(Collection $pool, int $count): Collection
    {
        $pairs = array_values(array_filter(self::PAIRS, fn ($pair) => collect($pair)
            ->every(fn ($s) => $pool->where('payload.sound', $s)->count() >= 2)));
        if (! $pairs) {
            return $this->cycle($pool, $count);
        }
        $pair = $pairs[array_rand($pairs)];
        if (random_int(0, 1)) {
            $pair = array_reverse($pair);
        }
        $a = $this->cycle($pool->where('payload.sound', $pair[0])->values(), (int) ceil($count / 2))->values();
        $b = $this->cycle($pool->where('payload.sound', $pair[1])->values(), intdiv($count, 2))->values();

        return collect(range(0, $count - 1))->map(fn ($i) => $i % 2 ? $b[intdiv($i, 2)] : $a[intdiv($i, 2)]);
    }
}
