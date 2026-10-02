<?php

namespace App\Beszed\Content;

/**
 * Small Hungarian spelling helpers for generating game content: letters
 * (digraphs as one letter), syllables, rhyme endings, articles.
 */
final class Hungarian
{
    public const VOWELS = ['a', 'á', 'e', 'é', 'i', 'í', 'o', 'ó', 'ö', 'ő', 'u', 'ú', 'ü', 'ű'];

    /** Multi-character letters, longest first. The doubled ones (ssz = long sz) are "long". */
    private const LONG = ['ddzs', 'ccs', 'ddz', 'ggy', 'lly', 'nny', 'ssz', 'tty', 'zzs'];

    private const MULTI = ['dzs', 'cs', 'dz', 'gy', 'ly', 'ny', 'sz', 'ty', 'zs'];

    /**
     * The word's letters (a digraph such as "sz" is one letter), each with its
     * position in the word so the original spelling can be cut back out.
     *
     * @return list<array{letter: string, at: int, long: bool}>
     */
    public static function letters(string $word): array
    {
        $lower = mb_strtolower($word);
        $length = mb_strlen($lower);
        $out = [];
        for ($i = 0; $i < $length;) {
            foreach ([...self::LONG, ...self::MULTI] as $multi) {
                $n = mb_strlen($multi);
                if (mb_substr($lower, $i, $n) === $multi) {
                    $out[] = ['letter' => $multi, 'at' => $i, 'long' => in_array($multi, self::LONG, true)];
                    $i += $n;

                    continue 2;
                }
            }
            $out[] = ['letter' => mb_substr($lower, $i, 1), 'at' => $i, 'long' => false];
            $i++;
        }

        return $out;
    }

    public static function isVowel(string $letter): bool
    {
        return in_array($letter, self::VOWELS, true);
    }

    /**
     * Spoken syllables, one per vowel ("karácsonyfa" → ka-rá-csony-fa): between
     * two vowels the last consonant starts the next syllable. Null when the word
     * can't be cut that simply (a long double letter such as "ssz" between vowels
     * is hyphenated as "sz-sz", which wouldn't add up to the written word).
     *
     * @return list<string>|null
     */
    public static function syllables(string $word): ?array
    {
        if (! preg_match('/^\p{L}+$/u', $word)) {
            return null;
        }
        $letters = self::letters($word);
        $vowels = array_keys(array_filter($letters, fn ($l) => self::isVowel($l['letter'])));
        if (! $vowels) {
            return null;
        }

        $starts = [0];
        for ($k = 1; $k < count($vowels); $k++) {
            [$prev, $cur] = [$vowels[$k - 1], $vowels[$k]];
            $between = array_slice($letters, $prev + 1, $cur - $prev - 1);
            if (array_filter($between, fn ($l) => $l['long'])) {
                return null;
            }
            $starts[] = $letters[$between ? $cur - 1 : $cur]['at'];
        }

        $out = [];
        foreach ($starts as $i => $start) {
            $end = $starts[$i + 1] ?? mb_strlen($word);
            $out[] = mb_substr($word, $start, $end - $start);
        }

        return $out;
    }

    /**
     * The part that has to match for two words to rhyme: from the last vowel
     * (hal → "al", kalap → "ap", ló → "ó"), or from the one before it when the
     * word ends in a vowel and has more (cica → "ica", róka → "óka").
     */
    public static function rhyme(string $word): ?string
    {
        $lower = mb_strtolower($word);
        $chars = mb_str_split($lower);
        $vowels = array_keys(array_filter($chars, fn ($c) => self::isVowel($c)));
        if (! $vowels) {
            return null;
        }
        $from = self::isVowel(end($chars)) && count($vowels) > 1 ? $vowels[count($vowels) - 2] : end($vowels);

        return mb_substr($lower, $from);
    }

    /** "a" or "az" before the word. */
    public static function article(string $word): string
    {
        return self::isVowel(mb_substr(mb_strtolower($word), 0, 1)) ? 'az' : 'a';
    }

    /**
     * "a" or "az" before a letter or sound read by its name: "az s" (es), "az sz" (esz),
     * "az m" (em), but "a k" (ká), "a zs" (zsé). Wrong with the plain rule, which looks
     * only at the spelling. Takes the first letter of what it is given; a rhyme ending
     * written with a hyphen ("-ó") works too.
     */
    public static function letterArticle(string $letters): string
    {
        $first = self::letters(preg_replace('/^[\s\-–]+/u', '', mb_strtolower($letters)))[0]['letter'] ?? '';

        // Letters whose name starts with a vowel sound: f (ef), l (el), m (em), n (en), r (er), s (es), x (iksz), y (ipszilon) ...
        return self::isVowel($first) || in_array($first, ['f', 'l', 'ly', 'm', 'n', 'ny', 'r', 's', 'sz', 'x', 'y'], true) ? 'az' : 'a';
    }

    /** Long vowels made short (ó → o, ű → ü): ló / lovat, kéz / kezet share a stem. */
    public static function fold(string $text): string
    {
        return strtr(mb_strtolower($text), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ő' => 'ö', 'ú' => 'u', 'ű' => 'ü']);
    }
}
