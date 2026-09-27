<?php

namespace App\Beszed\Reports;

use App\Mail\WeeklyReportMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Sends a parent one weekly e-mail per child who played that week. At most
 * one per child per week (a scheduler run twice sends nothing twice), unless
 * `force` (the parent asked for a sample now).
 */
final class WeeklyReportSender
{
    public function __construct(private WeeklyReport $reports) {}

    /** @return int e-mails sent */
    public function sendFor(User $user, bool $force = false): int
    {
        if (! $user->email || (! $force && ! $user->weekly_report_enabled)) {
            return 0;
        }
        $unsubscribe = URL::signedRoute('weekly-report.unsubscribe', ['user' => $user->id]);
        $sent = 0;
        foreach ($user->children()->orderBy('id')->get() as $child) {
            $report = $this->reports->for($child);
            if (! $report) {
                continue; // didn't play this week: no e-mail
            }
            if (! $force && ! Cache::add("weekly-report:{$child->id}:".now()->format('o-W'), true, now()->addDays(8))) {
                continue;
            }
            Mail::to($user->email)->send(new WeeklyReportMail($report, $unsubscribe));
            $sent++;
        }

        return $sent;
    }
}
