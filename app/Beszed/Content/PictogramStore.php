<?php

namespace App\Beszed\Content;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * ARASAAC pictograms, kept on this server: fetched from ARASAAC once (300 px
 * PNG), then served from storage/app/private/pictograms. Players' browsers
 * never ask a third party for a picture.
 */
final class PictogramStore
{
    public const SOURCE = 'https://static.arasaac.org/pictograms/%1$d/%1$d_300.png';

    private const MAX_BYTES = 1_000_000;

    public static function path(int $id): string
    {
        return Storage::disk('local')->path(self::file($id));
    }

    public static function has(int $id): bool
    {
        return Storage::disk('local')->exists(self::file($id));
    }

    /** Downloads the pictogram unless it is already here. False when ARASAAC doesn't have it. */
    public static function fetch(int $id): bool
    {
        if (self::has($id)) {
            return true;
        }
        $response = Http::timeout(10)->retry(2, 300, throw: false)->get(sprintf(self::SOURCE, $id));
        $png = $response->body();
        if (! $response->successful() || ! str_starts_with($png, "\x89PNG") || strlen($png) > self::MAX_BYTES) {
            return false;
        }
        Storage::disk('local')->put(self::file($id), $png);

        return true;
    }

    private static function file(int $id): string
    {
        return "pictograms/$id.png";
    }
}
