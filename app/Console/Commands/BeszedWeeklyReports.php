<?php

namespace App\Console\Commands;

use App\Beszed\Reports\WeeklyReport;
use App\Beszed\Reports\WeeklyReportPdf;
use App\Beszed\Reports\WeeklyReportSender;
use App\Models\Child;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** The Sunday e-mails (scheduled in routes/console.php); --preview writes one child's report to storage instead. */
class BeszedWeeklyReports extends Command
{
    protected $signature = 'beszed:weekly-reports
        {--user= : only this parent (id)}
        {--force : send even if already sent this week or turned off}
        {--preview= : write this child\'s report (id) as PDF + HTML to storage/app/weekly-preview, send nothing}';

    protected $description = 'E-mail each parent the week of every child who played (HTML + PDF)';

    public function handle(WeeklyReportSender $sender, WeeklyReport $reports): int
    {
        if ($id = $this->option('preview')) {
            $report = $reports->for(Child::findOrFail($id));
            if (! $report) {
                $this->warn('No play in the last 7 days: no report.');

                return self::SUCCESS;
            }
            Storage::disk('local')->put('weekly-preview/report.pdf', WeeklyReportPdf::render($report));
            Storage::disk('local')->put('weekly-preview/report.html', view('emails.weekly-report', ['r' => $report, 'unsubscribeUrl' => '#'])->render());
            $this->info('Written: '.Storage::disk('local')->path('weekly-preview'));

            return self::SUCCESS;
        }

        $sent = 0;
        User::query()
            ->when($this->option('user'), fn ($q, $id) => $q->whereKey($id))
            ->when(! $this->option('force'), fn ($q) => $q->where('weekly_report_enabled', true))
            ->whereNotNull('email')
            ->chunkById(100, function ($users) use ($sender, &$sent) {
                foreach ($users as $user) {
                    $sent += $sender->sendFor($user, (bool) $this->option('force'));
                }
            });
        $this->info("Weekly reports sent: $sent");

        return self::SUCCESS;
    }
}
