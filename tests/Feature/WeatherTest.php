<?php

use App\Beszed\Weather\WeatherService;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Cache::flush();
    $this->user = User::factory()->create();
});

it('reports the weather of the chosen city, boiled down', function () {
    Http::fake(['*' => Http::response(['current' => ['temperature_2m' => 17.6, 'weather_code' => 61, 'is_day' => 1]])]);

    actingAs($this->user)->getJson('/api/beszed/weather?city=szeged')
        ->assertOk()
        ->assertJsonPath('weather', ['city' => 'Szeged', 'temp' => 18, 'kind' => 'rain', 'day' => true]);

    Http::assertSent(fn ($r) => str_contains($r->url(), 'latitude=46.253'));
});

it('falls back to the default city for an unknown one, and caches the answer', function () {
    Http::fake(['*' => Http::response(['current' => ['temperature_2m' => -3.2, 'weather_code' => 71, 'is_day' => 0]])]);

    actingAs($this->user)->getJson('/api/beszed/weather?city=atlantis')
        ->assertJsonPath('weather.city', 'Budapest')
        ->assertJsonPath('weather.kind', 'snow')
        ->assertJsonPath('weather.temp', -3);
    actingAs($this->user)->getJson('/api/beszed/weather')->assertOk();

    Http::assertSentCount(1);
});

it('stays quiet (null) when the weather service is down', function () {
    Http::fake(['*' => Http::response('nope', 500)]);

    actingAs($this->user)->getJson('/api/beszed/weather')->assertOk()->assertJsonPath('weather', null);
});

it('needs a signed-in parent', function () {
    $this->getJson('/api/beszed/weather')->assertUnauthorized();
});

it('maps weather codes to simple kinds', function () {
    expect(array_map(WeatherService::kind(...), [0, 2, 3, 45, 53, 65, 75, 81, 86, 96]))
        ->toBe(['clear', 'partly', 'cloudy', 'fog', 'rain', 'rain', 'snow', 'rain', 'snow', 'storm']);
});
