<?php

namespace App\Beszed;

/** Lines a parent can record in their own voice (Csillám plays them instead of TTS). */
class Lines
{
    /** @return array<int, array{key:string,label:string,text:string}> */
    public static function all(): array
    {
        $out = [];
        $add = function (string $key, string $label, string $text) use (&$out): void {
            $out[] = compact('key', 'label', 'text');
        };

        [$l, $t] = config('beszed.lines.greet');
        $add('greet', $l, $t);
        foreach (config('beszed.games') as $id => $g) {
            $add("intro_$id", "Bevezető: {$g['name']}", $g['intro']);
        }
        foreach (config('beszed.praise') as $i => $t) {
            $add('praise'.($i + 1), 'Dicséret '.($i + 1), $t);
        }
        foreach (config('beszed.retry') as $i => $t) {
            $add('retry'.($i + 1), 'Újrapróbálás '.($i + 1), $t);
        }
        foreach (['help', 'finish'] as $k) {
            [$l, $t] = config("beszed.lines.$k");
            $add($k, $l, $t);
        }

        return $out;
    }

    public static function keys(): array
    {
        return array_column(self::all(), 'key');
    }
}
