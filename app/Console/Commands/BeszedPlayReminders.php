<?php

namespace App\Console\Commands;

use App\Beszed\Rewards\Stats;
use App\Mail\PlayReminderMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * The evening nudge (scheduled in routes/console.php): a parent who asked for it gets one e-mail a day when a child who
 * played in the last two weeks has not played today. Children who have stopped playing for longer are left alone.
 */
class BeszedPlayReminders extends Command
{
    protected $signature = 'beszed:play-reminders {--user= : only this parent (id)} {--force : ignore "already sent today" and the opt-in}';

    protected $description = 'E-mail parents (who opted in) whose child has not played today';

    public function handle(): int
    {
        $tz = config('beszed.rewards.timezone');
        $sent = 0;

        User::query()
            ->when($this->option('user'), fn ($q, $id) => $q->whereKey($id))
            ->when(! $this->option('force'), fn ($q) => $q->where('play_reminder_enabled', true))
            ->whereNotNull('email')
            ->chunkById(100, function ($users) use ($tz, &$sent) {
                foreach ($users as $user) {
                    $due = [];
                    foreach ($user->children()->orderBy('id')->get() as $child) {
                        $stats = Stats::for($child, $tz);
                        $playedLately = collect($stats->recentDays)->contains('played', true);
                        if ($stats->playedToday || ! $playedLately) {
                            continue;
                        }
                        $due[] = [
                            'id' => $child->id, 'name' => $child->name, 'sign' => $child->sign_emoji ?? '🦄',
                            'streak' => $stats->streak, 'url' => url("/beszed/{$child->id}"),
                        ];
                    }
                    if (! $due || (! $this->option('force') && ! Cache::add("play-reminder:{$user->id}:".now($tz)->toDateString(), true, now()->addDay()))) {
                        continue;
                    }
                    Mail::to($user->email)->send(new PlayReminderMail($due, URL::signedRoute('play-reminder.unsubscribe', ['user' => $user->id])));
                    $sent++;
                }
            });
        $this->info("Play reminders sent: $sent");

        return self::SUCCESS;
    }
}
