<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The content editor: parents whose e-mail is in ADMIN_EMAILS.
        Gate::define('edit-content', fn (User $user) => in_array(strtolower((string) $user->email), config('beszed_content.admins'), true));
    }
}
