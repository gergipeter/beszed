<?php

namespace App\Console\Commands;

use App\Models\Child;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * The account Apple's (and Google's) reviewer signs in with: a parent with a premium plan, consent given and one child,
 * so every screen can be reached without a Google account or a purchase. Put the printed credentials into the review notes.
 */
class ReviewAccount extends Command
{
    public const EMAIL = 'review@beszed.app';

    protected $signature = 'beszed:review-account {--password= : use this password instead of a random one}';

    protected $description = 'Create or reset the App Store review account (premium, consent given, one child)';

    public function handle(): int
    {
        $password = $this->option('password') ?: Str::password(16, symbols: false);

        $user = User::firstOrNew(['email' => self::EMAIL]);
        $user->name = 'App Review';
        $user->password = $password;
        $user->forceFill([
            'subscription_plan' => 'premium',
            'consented_at' => now(),
            'consent_version' => config('privacy.version'),
            'email_verified_at' => now(),
        ])->save();

        if (! $user->children()->exists()) {
            Child::create(['user_id' => $user->id, 'name' => 'Teszt']);
        }

        $this->info('Review account ready.');
        $this->table(['E-mail', 'Password', 'Plan'], [[self::EMAIL, $password, 'premium']]);
        $this->line('Copy these into App Store Connect → App Review Information. The password is shown only now.');

        return self::SUCCESS;
    }
}
