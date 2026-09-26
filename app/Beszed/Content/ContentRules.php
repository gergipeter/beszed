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
            || preg_match('/^arasaac:\d{1,6}$/', $value)
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
            'melyik' => $lower($p['good']) !== $lower($p['bad']) ? [] : ['bad' => 'A két mondat ugyanaz.'],
            // two identical pictures would make the order ambiguous
            'tortenet' => count(array_unique(array_column($p['steps'], 0))) === count($p['steps']) ? [] : ['steps' => 'Egy képet csak egyszer használj a történetben.'],
            // Same start, ignoring vowel length: ló → lovat, kéz → kezet, kő → követ.
            'szamol' => str_ends_with($lower($p['accusative']), 't') && mb_substr(Hungarian::fold($p['accusative']), 0, 2) === mb_substr(Hungarian::fold($p['name']), 0, 2)
                ? [] : ['accusative' => 'A tárgyrag -t végű alakja kell (pl. alma → almát).'],
            default => [],
        };
    }
}
