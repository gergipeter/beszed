<?php

namespace App\Beszed\Content;

/**
 * Pictures in the games are emojis or ARASAAC pictograms ("arasaac:2462").
 * Content stores the emoji where a word has one; when pictograms are on
 * (config beszed_content.pictograms), a session swaps every emoji the word
 * bank has a pictogram for, keeping the emoji as the fallback after "~"
 * ("arasaac:2462~🍎"), so the app still shows something offline.
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

    /** Every string in the rounds that is exactly a mapped emoji becomes its pictogram. */
    public static function apply(array $rounds): array
    {
        if (! config('beszed_content.pictograms')) {
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
