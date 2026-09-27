<?php

namespace App\Beszed\Reports;

/**
 * An emoji's Twemoji image (public/build/emoji/<codepoints>.svg, copied there by
 * `npm run build`), for the PDF: dompdf has no colour-emoji font, so pictures
 * are drawn from the same SVGs the app shows. Same file naming as the app's
 * emojiAssetName(): code points in hex, without the variation selector unless
 * the emoji is a ZWJ sequence.
 */
final class Twemoji
{
    public static function file(string $emoji): ?string
    {
        $chars = str_contains($emoji, "\u{200D}") ? $emoji : str_replace("\u{FE0F}", '', $emoji);
        $name = implode('-', array_map(fn ($c) => dechex(mb_ord($c)), mb_str_split($chars)));
        $path = public_path("build/emoji/$name.svg");

        return is_file($path) ? $path : null;
    }

    /** An <img> for the PDF (a data URI, so dompdf needs no file access), or the emoji itself. */
    public static function img(string $emoji, int $size = 20): string
    {
        $file = self::file($emoji);
        if (! $file) {
            return e($emoji);
        }
        $data = base64_encode(file_get_contents($file));

        return sprintf('<img src="data:image/svg+xml;base64,%s" width="%d" height="%d" style="vertical-align:middle" alt="">', $data, $size, $size);
    }
}
