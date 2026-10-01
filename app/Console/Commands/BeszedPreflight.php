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
        $add('Debug mode off', ! config('app.debug'), 'blocker', 'set APP_DEBUG=false');
        $add('App URL is https', str_starts_with((string) config('app.url'), 'https://'), 'blocker', 'set APP_URL to the public https address');
        $add('Demo sign-in off', ! app()->isLocal(), 'blocker', 'APP_ENV=local enables the one-tap demo sign-in; use production');
        $add('ARASAAC pictograms off (non-commercial licence)', ! config('beszed_content.pictograms'), 'blocker', 'set BESZED_PICTOGRAMS=false unless ARASAAC agreed in writing to commercial use (docs/pictogram-licensing.md)');
        $add('Child voice stays on our server (STT)', ! in_array(config('stt.driver'), ['azure'], true), 'blocker', 'STT_DRIVER=azure sends the child\'s voice to Microsoft; use whisper (own server) or null');
        $add('Child voice stays on our server (TTS)', config('tts.driver') !== 'azure', 'warning', 'TTS_DRIVER=azure sends sentence text to Microsoft (no voice of the child); piper keeps it here');
        $add('Mail is really sent (password reset, reports)', config('mail.default') !== 'log', 'warning', 'MAIL_MAILER=log only writes mails to the log; password-reset mails will not arrive');
        $add('Review account exists', \App\Models\User::where('email', ReviewAccount::EMAIL)->exists(), 'warning', 'run: php artisan beszed:review-account (credentials go into the App Review notes)');

        $this->table(['', 'Check', 'What to do'], $rows);
        $this->line($blockers ? "<error> $blockers blocker(s) left. </error>" : '<info>No blockers.</info>');

        return $blockers ? self::FAILURE : self::SUCCESS;
    }
}
