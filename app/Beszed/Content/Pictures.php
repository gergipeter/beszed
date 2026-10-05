<?php

namespace App\Beszed\Content;

/**
 * Pictures in the games are emojis or ARASAAC pictograms ("arasaac:2462").
 * Content stores the emoji where a word has one; when pictograms are on
 * (config beszed_content.pictograms), a session swaps every emoji the word
 * bank has a pictogram for, keeping the emoji as the fallback after "~"
 * ("arasaac:2462~🍎"), so the app still shows something offline.
 *
 * With pictograms off (ARASAAC is licensed for non-commercial use only) a pictogram is replaced by what
 * database/lexicon/substitutes.json names for it: a Mulberry symbol (mulberrysymbols.org, CC BY-SA 4.0,
 * "mulberry:badger"), an in-house AI-generated picture (ours outright, "ai:apple", public/ai-pics/),
 * or a plain emoji ("emoji:🏛️" in the file).
 */
final class Pictures
{
    /** @var array<string, string>|null emoji => "arasaac:<id>" */
    private static ?array $map = null;

    /** @return array<string, string> */
    public static function map(): array
    {
        if (self::$map === null) {
            $words = json_decode(file_get_contents(database_path('lexicon/hu.json')), true, flags: JSON_THROW_ON_ERROR);
            self::$map = collect($words)
                ->filter(fn ($w) => isset($w['e'], $w['p']))
                ->mapWithKeys(fn ($w) => [$w['e'] => "arasaac:{$w['p']}~{$w['e']}"])
                ->all();
        }

        return self::$map;
    }

    /** @var array<int, string>|null ARASAAC pictogram id => Mulberry symbol name, or "emoji:<emoji>" */
    private static ?array $substitutes = null;

    /** @return array<int, string> */
    public static function substitutes(): array
    {
        return self::$substitutes ??= array_map('strval', json_decode(file_get_contents(database_path('lexicon/substitutes.json')), true, flags: JSON_THROW_ON_ERROR));
    }

    /** Every pictogram in the list has something to show in its place (true for an empty list). @param list<int> $ids */
    public static function hasSubstitutes(array $ids): bool
    {
        return collect($ids)->every(fn ($id) => isset(self::substitutes()[$id]));
    }

    /**
     * Every string in the rounds that is exactly a mapped emoji becomes its pictogram; with pictograms
     * off, every pictogram becomes its substitute instead (a Mulberry symbol unless the entry already
     * names its own kind, "ai:" or "emoji:").
     */
    public static function apply(array $rounds): array
    {
        if (! config('beszed_content.pictograms')) {
            $substitutes = self::substitutes();
            array_walk_recursive($rounds, function (&$value) use ($substitutes) {
                if (is_string($value) && preg_match('/^arasaac:(\d+)(~.*)?$/su', $value, $m) && isset($substitutes[(int) $m[1]])) {
                    $sub = $substitutes[(int) $m[1]];
                    $value = match (true) {
                        str_starts_with($sub, 'emoji:') => substr($sub, 6),
                        str_starts_with($sub, 'ai:') => $sub.($m[2] ?? ''),
                        default => "mulberry:$sub".($m[2] ?? ''),
                    };
                }
            });

            return $rounds;
        }
        $map = self::map();
        array_walk_recursive($rounds, function (&$value) use ($map) {
            if (is_string($value) && isset($map[$value])) {
                $value = $map[$value];
            }
        });

        return $rounds;
    }

    /** Pictogram ids the content uses, for fetching them ahead. @return list<int> */
    public static function ids(): array
    {
        $words = json_decode(file_get_contents(database_path('lexicon/hu.json')), true, flags: JSON_THROW_ON_ERROR);

        return collect($words)->pluck('p')->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
    }
}
