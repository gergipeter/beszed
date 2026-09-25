<?php

namespace App\Providers;

use App\Beszed\Tts\AzureTtsClient;
use App\Beszed\Tts\NullTtsClient;
use App\Beszed\Tts\TtsClient;
use App\Console\Commands\BeszedTtsWarm;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BeszedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TtsClient::class, fn () => match (config('tts.driver')) {
            'azure' => new AzureTtsClient(config('tts.azure')),
            default => new NullTtsClient,
        });
    }

    public function boot(): void
    {
        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/beszed')
            ->group(base_path('routes/beszed.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([BeszedTtsWarm::class]);
        }
    }
}
