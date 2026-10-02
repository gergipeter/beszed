<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\Weather\WeatherService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Csillám's weather report for a city the parent picked (only the city's id comes in: no child data). */
class WeatherController extends Controller
{
    public function __invoke(Request $request, WeatherService $weather): JsonResponse
    {
        $city = $request->query('city');
        $city = is_string($city) && config("weather.cities.$city") ? $city : config('weather.default');

        return response()->json(['weather' => $weather->for($city)]);
    }
}
