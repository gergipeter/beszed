<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * "Can this build go to a store?": the things in docs/ios-app-store-compliance.md that are settings, checked in one go.
 * Exit code 1 when a blocker is left, so a release script can stop on it.
 */
class BeszedPreflight extends Command
{
    protected $signature = 'beszed:preflight';

    protected $description = 'Check the settings an App Store / Google Play release depends on';

    public function handle(): int
    {
        $blockers = 0;
        $rows = [];
        $add = function (string $name, bool $ok, string $level, string $fix) use (&$rows, &$blockers) {
            $rows[] = [$ok ? '✓' : ($level === 'blocker' ? '✗ BLOCKER' : '! warning'), $name, $ok ? '' : $fix];
            if (! $ok && $level === 'blocker') {
                $blockers++;
            }
        };

        $placeholder = fn (?string $v) => blank($v) || str_contains((string) $v, 'kitöltendő');
        $add('Privacy notice: controller', ! $placeholder(config('privacy.controller')), 'blocker', 'set PRIVACY_CONTROLLER (who publishes the app)');
        $add('Privacy notice: contact', ! $placeholder(config('privacy.contact')), 'blocker', 'set PRIVACY_CONTACT (a monitored e-mail address)');
        $add('Imprint: registration number', ! $placeholder(config('privacy.registration')), 'blocker', 'set PRIVACY_REGISTRATION (company or sole-trader registration number, shown on /impresszum)');
        $add('Imprint: tax number', ! $placeholder(config('privacy.tax_id')), 'blocker', 'set PRIVACY_TAX_ID');
        $add('Imprint: hosting provider', ! $placeholder(config('privacy.hosting')), 'blocker', 'set PRIVACY_HOSTING (name, address, e-mail of the hosting provider)');
        $add('Terms: conciliation body', ! $placeholder(config('privacy.conciliation')), 'blocker', 'set PRIVACY_CONCILIATION (the consumer conciliation body, shown in the terms)');
        $add('Debug mode off', ! config('app.debug'), 'blocker', 'set APP_DEBUG=false');
        $add('App URL is https', str_starts_with((string) config('app.url'), 'https://'), 'blocker', 'set APP_URL to the public https address');
        $add('Demo sign-in off', ! app()->isLocal(), 'blocker', 'APP_ENV=local enables the one-tap demo sign-in; use production');
        $add('ARASAAC pictograms off (non-commercial licence)', ! config('beszed_content.pictograms'), 'blocker', 'set BESZED_PICTOGRAMS=false unless ARASAAC agreed in writing to commercial use (docs/pictogram-licensing.md)');
        $add('Child voice stays on our server (STT)', ! in_array(config('stt.driver'), ['azure'], true), 'blocker', 'STT_DRIVER=azure sends the child\'s voice to Microsoft; use whisper (own server) or null');
        $add('Child voice stays on our server (TTS)', config('tts.driver') !== 'azure', 'warning', 'TTS_DRIVER=azure sends sentence text to Microsoft (no voice of the child); piper keeps it here');
        $add('Mail is really sent (password reset, reports)', config('mail.default') !== 'log', 'warning', 'MAIL_MAILER=log only writes mails to the log; password-reset mails will not arrive');
        $add('In-app purchases: RevenueCat server key + webhook secret', filled(config('billing.revenuecat.secret_key')) && filled(config('billing.revenuecat.webhook_secret')), 'blocker', 'set REVENUECAT_SECRET_KEY and REVENUECAT_WEBHOOK_SECRET (docs/billing.md); without them the premium plan cannot be bought');
        $add('In-app purchases: RevenueCat SDK keys for the apps', filled(config('billing.revenuecat.public_key_ios')) || filled(config('billing.revenuecat.public_key_android')), 'warning', 'set REVENUECAT_PUBLIC_KEY_IOS / _ANDROID (the native app starts RevenueCat with them)');
        $add('Cookie sessions only for our own domain', collect(config('sanctum.stateful'))->map(fn ($d) => strtolower(trim((string) $d)))->doesntContain(fn ($d) => preg_match('/^(localhost|127\.0\.0\.1|::1)(:\d+)?$/', $d)), 'warning', 'SANCTUM_STATEFUL_DOMAINS lists localhost: set it to the real domain only (the app\'s own origin must not count as the cookie web app)');
        $add('Only our own proxies are believed (X-Forwarded-For)', trim((string) getenv('TRUSTED_PROXIES')) !== '*', 'warning', 'TRUSTED_PROXIES=* lets any visitor forge their address and get around the sign-in throttles; list the proxy\'s addresses, or leave it empty');
        $add('Content editors are real people', collect(config('beszed_content.admins'))->doesntContain(fn ($e) => preg_match('/@example\.(test|com|org)$/', $e)), 'warning', 'ADMIN_EMAILS still has an example address');
        $add('Database backups are kept apart from the data', config('database.default') !== 'sqlite' || ! str_starts_with(str_replace('\\', '/', (string) config('beszed_backup.path')), str_replace('\\', '/', storage_path())), 'warning', 'set BACKUP_PATH to a separate volume and copy it off the server (docs/backup.md)');
        $add('Review account exists', \App\Models\User::where('email', ReviewAccount::EMAIL)->exists(), 'warning', 'run: php artisan beszed:review-account (credentials go into the App Review notes)');

        $this->table(['', 'Check', 'What to do'], $rows);
        $this->line($blockers ? "<error> $blockers blocker(s) left. </error>" : '<info>No blockers.</info>');

        return $blockers ? self::FAILURE : self::SUCCESS;
    }
}
