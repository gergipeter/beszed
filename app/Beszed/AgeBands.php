<?php

namespace App\Beszed;

use App\Models\Child;
use Carbon\CarbonImmutable;

/**
 * Age groups used to pick a sensible starting difficulty per game.
 * Bands follow Hungarian preschool/school stages: 3–4 (kiscsoport/középső),
 * 5–6 (nagycsoport, DIFER-kor), 7+ (iskoláskor).
 */
class AgeBands
{
    public const BAND_3_4 = '3-4';

    public const BAND_5_6 = '5-6';

    public const BAND_7_PLUS = '7+';

    /** Ordered youngest to oldest, for clamping levels below/above the known bands. */
    public const ORDER = [self::BAND_3_4, self::BAND_5_6, self::BAND_7_PLUS];

    public static function years(Child $child): ?int
    {
        return $child->birth_date?->age;
    }

    public static function of(Child $child): ?string
    {
        return self::forAge(self::years($child));
    }

    public static function forAge(?int $years): ?string
    {
        if ($years === null) {
            return null;
        }

        return match (true) {
            $years < 5 => self::BAND_3_4,
            $years < 7 => self::BAND_5_6,
            default => self::BAND_7_PLUS,
        };
    }

    public static function label(?string $band): string
    {
        return match ($band) {
            self::BAND_3_4 => '3–4 év',
            self::BAND_5_6 => '5–6 év',
            self::BAND_7_PLUS => '7+ év',
            default => 'Nincs megadva',
        };
    }
}
