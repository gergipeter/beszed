<?php

namespace App\Beszed\Content;

/**
 * Pictures in the games are emojis or ARASAAC pictograms ("arasaac:2462").
 * Content stores the emoji where a word has one; when pictograms are on
 * (config beszed_content.pictograms), a session swaps every emoji the word
 * bank has a pictogram for, keeping the emoji as the fallback after "~"
 * ("arasaac:2462~🍎"), so the app still shows something offline.
 *
 * An in-house AI-generated picture (ours outright, "ai:apple" in
 * database/lexicon/substitutes.json, public/ai-pics/) wins over ARASAAC whenever a word has one —
 * it is deliberately better art, not merely the non-commercial fallback, so pictograms being on
 * doesn't hide it. With pictograms off (ARASAAC is licensed for non-commercial use only) a pictogram
 * that has no AI picture is replaced instead by a Mulberry symbol (mulberrysymbols.org, CC BY-SA 4.0,
 * "mulberry:badger") or a plain emoji ("emoji:🏛️" in the file) — those two stay off-only, since
 * they exist purely to stand in for ARASAAC, not to be preferred over it.
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
     * Every string in the rounds that is exactly a mapped emoji becomes its picture (content stores
     * the plain emoji, "arasaac:<id>" only ever appears live, from here): an AI picture first if the
     * word has one, regardless of the pictograms setting; otherwise its ARASAAC pictogram when
     * pictograms are on, or (pictograms off) a Mulberry symbol / plain emoji substitute. A value
     * already written as "arasaac:<id>" by hand (rare, content-editor-entered) is matched the same
     * way via its id.
     */
    public static function apply(array $rounds): array
    {
        $byEmoji = self::map(); // emoji => "arasaac:<id>~<emoji>"
        $substitutes = self::substitutes();
        $pictogramsOn = (bool) config('beszed_content.pictograms');
        array_walk_recursive($rounds, function (&$value) use ($byEmoji, $substitutes, $pictogramsOn) {
            if (! is_string($value)) {
                return;
            }
            $id = null;
            $fallback = '';
            if (preg_match('/^arasaac:(\d+)(~(.*))?$/su', $value, $m)) {
                $id = (int) $m[1];
                $fallback = $m[3] ?? '';
            } elseif (isset($byEmoji[$value])) {
                $id = (int) explode(':', $byEmoji[$value])[1];
                $fallback = $value;
            } else {
                return;
            }
            $sub = $substitutes[$id] ?? null;
            if ($sub !== null && str_starts_with($sub, 'ai:')) {
                // wins over ARASAAC too: deliberately better art, not merely the non-commercial fallback
                $value = $sub.($fallback !== '' ? "~$fallback" : '');
            } elseif ($pictogramsOn) {
                $value = "arasaac:$id".($fallback !== '' ? "~$fallback" : '');
            } elseif ($sub !== null) {
                $value = match (true) {
                    str_starts_with($sub, 'emoji:') => substr($sub, 6),
                    str_starts_with($sub, 'mulberry:') => $sub.($fallback !== '' ? "~$fallback" : ''),
                    default => "mulberry:$sub".($fallback !== '' ? "~$fallback" : ''),
                };
            }
            // pictograms off and no substitute at all: leave the plain emoji as is
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
