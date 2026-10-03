<?php

namespace App\Beszed\Content;

/**
 * Checks one content item against its game's schema (config/beszed_content.php)
 * and the Hungarian rules the game depends on. Used by the content editor and by
 * the tests that validate the seed JSON, so bad content can't reach a child.
 */
final class ContentRules
{
    private const VOWELS = '/[aáeéiíoóöőuúüű]/iu';

    /** Digraphs and the trigraph, longest first: "szék" starts with "sz", not "s". */
    private const MULTI = ['dzs', 'cs', 'dz', 'gy', 'ly', 'ny', 'sz', 'ty', 'zs'];

    /** @return array<string, string> field => Hungarian error message (empty = valid) */
    public static function check(string $game, array $payload): array
    {
        $schema = config("beszed_content.schemas.$game");
        if (! $schema) {
            return ['game' => 'Ismeretlen játék.'];
        }

        $errors = [];
        foreach ($schema['fields'] as $field => $spec) {
            if (($spec['when'] ?? null) && ($payload[$spec['when'][0]] ?? null) !== $spec['when'][1]) {
                continue; // field only applies to another variant (e.g. Okoska category)
            }
            $error = self::checkField($spec, $payload[$field] ?? null);
            if ($error) {
                $errors[$field] = $error;
            }
        }

        return $errors ?: self::checkGame($game, $payload);
    }

    public static function vowelCount(string $word): int
    {
        return preg_match_all(self::VOWELS, $word);
    }

    /** First sound, digraph-aware ("gyöngy" → "gy", "dzsem" → "dzs"). */
    public static function firstSound(string $word): string
    {
        $w = mb_strtolower($word);
        foreach (self::MULTI as $multi) {
            if (str_starts_with($w, $multi)) {
                return $multi;
            }
        }

        return mb_substr($w, 0, 1);
    }

    /** Does the word contain an "s" sound that isn't part of "sz", "zs" or "cs" (or their long forms)? */
    public static function hasPlainS(string $word): bool
    {
        return str_contains(str_replace(['ssz', 'zzs', 'ccs', 'sz', 'zs', 'cs'], '', mb_strtolower($word)), 's');
    }

    private static function checkField(array $spec, mixed $value): ?string
    {
        $required = $spec['required'] ?? true;
        if ($value === null || $value === '' || $value === []) {
            return $required ? 'Kötelező.' : null;
        }

        return match ($spec['type']) {
            'text' => is_string($value) && mb_strlen($value) <= ($spec['max'] ?? 160) ? null : 'Szöveget adj meg (legfeljebb '.($spec['max'] ?? 160).' karakter).',
            'emoji' => self::isPicture($value) ? null : 'Adj meg egy emojit vagy egy piktogramot (arasaac:szám).',
            'select' => in_array($value, array_keys($spec['options']), true) ? null : 'Válassz a listából.',
            'list' => self::stringList($value, $spec['min'] ?? 1) ? null : 'Legalább '.($spec['min'] ?? 1).' elem kell.',
            'emoji_list' => self::stringList($value, $spec['min'] ?? 1) && collect($value)->every(fn ($e) => self::isPicture($e)) ? null : 'Legalább '.($spec['min'] ?? 1).' kép kell (emoji vagy arasaac:szám).',
            'int' => is_int($value) && $value >= ($spec['range'][0] ?? PHP_INT_MIN) && $value <= ($spec['range'][1] ?? PHP_INT_MAX)
                ? null : 'Egész számot adj meg ('.($spec['range'][0] ?? '').'–'.($spec['range'][1] ?? '').').',
            'int_list' => is_array($value) && array_is_list($value) && count($value) >= ($spec['min'] ?? 1)
                && collect($value)->every(fn ($n) => is_int($n) && $n >= ($spec['range'][0] ?? PHP_INT_MIN) && $n <= ($spec['range'][1] ?? PHP_INT_MAX))
                ? null : 'Legalább '.($spec['min'] ?? 1).' szám kell ('.($spec['range'][0] ?? '').'–'.($spec['range'][1] ?? '').').',
            'pairs' => is_array($value) && count($value) >= ($spec['min'] ?? 1)
                && collect($value)->every(fn ($p) => is_array($p) && count($p) === 2 && is_string($p[1]) && $p[1] !== '' && self::isPicture($p[0]))
                ? null : 'Legalább '.($spec['min'] ?? 1).' „emoji név” pár kell.',
            default => 'Ismeretlen mezőtípus.',
        };
    }

    /** An emoji (or a few, for a scene), an ARASAAC pictogram: "arasaac:2462", or an uploaded image: "upload:123". */
    public static function isPicture(mixed $value): bool
    {
        return is_string($value) && (
            (mb_strlen($value) <= 24 && preg_match('/\p{Extended_Pictographic}/u', $value))
            || (config('beszed_content.pictograms') && preg_match('/^arasaac:\d{1,6}$/', $value))
            || preg_match('/^upload:\d+$/', $value)
        );
    }

    private static function stringList(mixed $value, int $min): bool
    {
        return is_array($value) && array_is_list($value) && count($value) >= $min
            && collect($value)->every(fn ($s) => is_string($s) && trim($s) !== '');
    }

    /** Rules between fields, specific to each game. */
    private static function checkGame(string $game, array $p): array
    {
        $lower = fn ($s) => mb_strtolower(trim($s));

        return match ($game) {
            'zs' => match (true) {
                $p['sound'] === 'zs' && ! str_contains($lower($p['word']), 'zs') => ['word' => 'A szóban nincs „zs”.'],
                $p['sound'] === 's' && str_contains($lower($p['word']), 'zs') => ['word' => 'A szóban „zs” van, nem „s”.'],
                $p['sound'] === 's' && ! self::hasPlainS($p['word']) => ['word' => 'A szóban nincs önálló „s” (az „sz” és a „zs” nem számít).'],
                default => [],
            },
            'szotag' => match (true) {
                $lower(implode('', $p['syllables'])) !== $lower($p['word']) => ['syllables' => 'A szótagok együtt nem adják ki a szót.'],
                count($p['syllables']) !== self::vowelCount($p['word']) => ['syllables' => 'Ahány magánhangzó, annyi szótag: '.self::vowelCount($p['word']).' kell.'],
                default => [],
            },
            'kezdo' => self::firstSound($p['word']) === $lower($p['sound']) ? [] : ['sound' => 'A szó nem ezzel a hanggal kezdődik (kezdőhang: „'.self::firstSound($p['word']).'”).'],
            'rimelo', 'rimparok' => str_ends_with($lower($p['word']), $lower($p['rhyme'])) ? [] : ['rhyme' => 'A szó nem erre végződik.'],
            'mondd' => $lower(implode(' ', $p['chunks'])) === $lower($p['text']) ? [] : ['chunks' => 'A darabok együtt nem adják ki a mondatot.'],
            'betuk' => self::firstSound($p['word']) === $lower($p['letter']) ? [] : ['letter' => 'A szó nem ezzel a betűvel kezdődik (kezdőhang: „'.self::firstSound($p['word']).'”).'],
            'tobbes' => match (true) {
                ! str_ends_with($lower($p['pl']), 'k') => ['pl' => 'A többes szám -k végű.'],
                mb_substr($lower($p['pl']), 0, 1) !== mb_substr($lower($p['sg']), 0, 1) => ['pl' => 'A többes szám ugyanazzal a betűvel kezdődik, mint az egyes.'],
                default => [],
            },
            'ellentet' => match (true) {
                $lower($p['a']) === $lower($p['b']) => ['b' => 'A két szó nem lehet ugyanaz.'],
                $p['emojiA'] === $p['emojiB'] => ['emojiB' => 'A két szóhoz két különböző kép kell.'],
                default => [],
            },
            'napirend', 'keszul' => count(array_unique(array_column($p['steps'], 0))) === count($p['steps']) ? [] : ['steps' => 'Egy képet csak egyszer használj.'],
            'elohely', 'szobak' => count(array_unique(array_column($p['items'], 0))) === count($p['items']) ? [] : ['items' => 'Egy képet csak egyszer használj.'],
            'mondat' => match (true) {
                count(explode(' ', trim($p['text']))) < 3 || count(explode(' ', trim($p['text']))) > 5 => ['text' => 'A mondat 3–5 szóból álljon.'],
                count(array_unique(array_map($lower, explode(' ', trim($p['text']), 9)))) !== count(explode(' ', trim($p['text']), 9)) => ['text' => 'Egy szó csak egyszer szerepelhet (különben nem lehet sorrendbe rakni).'],
                default => [],
            },
            'ido' => preg_match('/^([1-9]|1[0-2]):(00|15|30|45)$/', trim($p['time'])) ? [] : ['time' => 'Az idő ó:pp alakú legyen, 1–12 óra, a perc 00, 15, 30 vagy 45.'],
            // the strokes of a letter are drawn by the app (engines/letter/glyphs.js): only those can be written
            'betuiro' => str_contains('IMLTHEFANKVZOCDUPBRSGJliotcunmadbpesáéíóöőúüűÁÉÍÓÖŐÚÜŰ', $p['glyph']) ? [] : ['glyph' => 'Ehhez a betűhöz még nincs rajzolt útvonal.'],
            'szokirako' => preg_match('/^[a-záéíóöőúüű]{2,12}$/u', $p['word']) && $lower($p['word']) === $p['word'] ? [] : ['word' => 'Csak kisbetűs magyar betűk, 2–12 betű.'],
            'olvasd' => $lower(implode('', $p['syllables'])) === $lower($p['word']) ? [] : ['syllables' => 'A szótagok együtt nem adják ki a szót.'],
            'mese' => count(array_unique(array_map(fn ($k) => $p["o$k"][0][0] ?? '', [1, 2, 3]))) >= 1
                && collect([1, 2, 3])->every(fn ($k) => count(array_unique(array_column($p["o$k"], 0))) === count($p["o$k"]))
                ? [] : ['o1' => 'Egy kérdés válaszaihoz különböző képek kellenek.'],
            'melyik' => $lower($p['good']) !== $lower($p['bad']) ? [] : ['bad' => 'A két mondat ugyanaz.'],
            'szajtorna' => count(array_unique(array_column($p['moves'], 1))) === count($p['moves']) ? [] : ['moves' => 'Minden mozdulat más legyen.'],
            // a pair of the same word, or of two words with the same picture, can't be told apart by ear or by eye
            'ikerhangok' => match (true) {
                $lower($p['wordA']) === $lower($p['wordB']) => ['wordB' => 'A két szó nem lehet ugyanaz.'],
                $p['emojiA'] === $p['emojiB'] => ['emojiB' => 'A két szóhoz két különböző kép kell.'],
                default => [],
            },
            // "Koppints a kutyára": the -ra/-re form of the name (a final a/e lengthens: kutya → kutyára)
            'utasitas' => preg_match('/(ra|re)$/u', $lower($p['onto'])) && mb_substr(Hungarian::fold($p['onto']), 0, 2) === mb_substr(Hungarian::fold($p['name']), 0, 2)
                ? [] : ['onto' => 'A szó -ra/-re végű alakja kell (pl. kutya → kutyára, kenyér → kenyérre).'],
            // two identical pictures would make the order ambiguous
            'tortenet' => count(array_unique(array_column($p['steps'], 0))) === count($p['steps']) ? [] : ['steps' => 'Egy képet csak egyszer használj a történetben.'],
            // Same start, ignoring vowel length: ló → lovat, kéz → kezet, kő → követ.
            'szamol', 'merleg', 'osztozas', 'szamok' => str_ends_with($lower($p['accusative']), 't') && mb_substr(Hungarian::fold($p['accusative']), 0, 2) === mb_substr(Hungarian::fold($p['name']), 0, 2)
                ? [] : ['accusative' => 'A tárgyrag -t végű alakja kell (pl. alma → almát).'],
            default => [],
        };
    }
}
