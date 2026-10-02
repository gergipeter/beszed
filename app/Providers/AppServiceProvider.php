<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
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
        // The reset link opens the app's own page (the app is a single-page app; there is no Laravel view for it).
        ResetPassword::createUrlUsing(fn ($user, string $token) => url('/jelszo-visszaallitas').'?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset()));
        ResetPassword::toMailUsing(fn ($user, string $token) => (new MailMessage)
            ->subject('Jelszó-visszaállítás – '.config('app.name'))
            ->greeting('Szia!')
            ->line('Jelszó-visszaállítást kértél a fiókodhoz. Az alábbi gombbal adhatsz meg új jelszót.')
            ->action('Új jelszó megadása', url('/jelszo-visszaallitas').'?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset()))
            ->line('A link '.config('auth.passwords.users.expire').' percig érvényes.')
            ->line('Ha nem te kérted, nem kell tenned semmit: a jelszavad nem változik.')
            ->salutation('Üdv, '.config('app.name')));

        // Only an e-mail that was proved counts (Google, or a reset link sent to it): the sign-up form does not
        // confirm addresses, so anyone could otherwise register an editor's address first.
        Gate::define('edit-content', fn (User $user) => $user->email_verified_at !== null
            && in_array(strtolower((string) $user->email), config('beszed_content.admins'), true));

        // Sanctum's AuthenticateSession signs a session out once the password has changed, but it learns which password
        // a session belongs to only at that session's first API request. A session opened and left idle (say by someone
        // who registered another person's address) would escape a later reset. So note the password at sign-in.
        Event::listen(Login::class, function (Login $event) {
            if (! request()->hasSession()) {
                return;
            }
            $guard = Auth::guard($event->guard);
            $hash = $event->user->getAuthPassword();
            request()->session()->put('password_hash_'.$event->guard, method_exists($guard, 'hashPasswordForCookie') ? $guard->hashPasswordForCookie($hash) : $hash);
        });
    }
}
