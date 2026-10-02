<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WeatherServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/beszed')
            ->group(base_path('routes/weather.php'));
    }
}
