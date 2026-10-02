<?php

namespace App\Beszed\Weather;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The current weather of one of the configured cities, boiled down to what Csillám says:
 * a temperature and a simple kind of sky. Cached, and null (never an error) when the
 * weather service can't be reached: the app just stays quiet about the weather then.
 */
class WeatherService
{
    /** @return array{city: string, temp: int, kind: string, day: bool}|null */
    public function for(string $cityId): ?array
    {
        $city = config("weather.cities.$cityId");
        if (! $city) {
            return null;
        }

        return Cache::remember("weather:$cityId", now()->addMinutes(config('weather.cache_minutes')), function () use ($city) {
            try {
                $current = Http::timeout(config('weather.timeout'))->get(config('weather.url'), [
                    'latitude' => $city['lat'],
                    'longitude' => $city['lon'],
                    'current' => 'temperature_2m,weather_code,is_day',
                    'timezone' => 'auto',
                ])->throw()->json('current');
            } catch (Throwable) {
                return null;
            }
            if (! is_array($current) || ! isset($current['temperature_2m'], $current['weather_code'])) {
                return null;
            }

            return [
                'city' => $city['name'],
                'temp' => (int) round($current['temperature_2m']),
                'kind' => self::kind((int) $current['weather_code']),
                'day' => (bool) ($current['is_day'] ?? 1),
            ];
        });
    }

    /** WMO weather code → clear · partly · cloudy · fog · rain · snow · storm. */
    public static function kind(int $code): string
    {
        return match (true) {
            $code === 0 => 'clear',
            $code <= 2 => 'partly',
            $code === 3 => 'cloudy',
            $code === 45, $code === 48 => 'fog',
            $code >= 95 => 'storm',
            $code >= 71 && $code <= 77, $code === 85, $code === 86 => 'snow',
            default => 'rain', // drizzle, rain, showers
        };
    }
}
