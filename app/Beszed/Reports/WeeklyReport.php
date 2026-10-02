<?php

namespace App\Beszed\Reports;

use App\Beszed\ProgressReport;
use App\Beszed\ReportNarrative;
use App\Beszed\Rewards\Rewards;
use App\Models\Child;
use Carbon\CarbonImmutable;

/**
 * A child's week, for the Sunday e-mail and its PDF: how much they played,
 * the stars and stickers they earned, how each skill area and sound went (against
 * the week before), the games they played most, and a tip for the next week.
 * Null when the child didn't play at all that week (no e-mail then).
 */
final class WeeklyReport
{
    private const DAYS = 7;

    /** Sounds listed in the e-mail and its PDF (the most practised ones). */
    private const SOUNDS_SHOWN = 6;

    public function __construct(
        private ProgressReport $progress,
        private ReportNarrative $narrative,
        private Rewards $rewards,
    ) {}

    public function for(Child $child): ?array
    {
        $tz = config('beszed.rewards.timezone');
        $since = CarbonImmutable::now()->subDays(self::DAYS);
        $sessions = $child->beszedSessions()->where('completed_at', '>=', $since)->get();
        if ($sessions->isEmpty()) {
            return null;
        }

        $report = $this->progress->for($child, self::DAYS);
        $summary = $this->rewards->summary($child);
        $areas = collect($report['areas'])->filter(fn ($a) => $a['sessions'] > 0 || $a['band'] !== 'noData')->values()->all();
        $sign = config("beszed.signs.{$child->sign}.emoji");
        $sounds = $report['sounds'] + $this->narrative->sounds($report['sounds']);

        return [
            'child' => ['id' => $child->id, 'name' => $child->name, 'sign' => $sign, 'age' => $report['child']['age']],
            'period' => $this->period($since->setTimezone($tz), CarbonImmutable::now($tz)),
            'totals' => [
                'games' => $sessions->count(),
                'minutes' => (int) round($sessions->sum('duration_ms') / 60000),
                'stars' => (int) $child->beszedAttempts()->where('correct', true)->where('created_at', '>=', $since)->count(),
                'days' => $sessions->map(fn ($s) => $s->completed_at->setTimezone($tz)->toDateString())->unique()->count(),
            ],
            'level' => $summary['level'],
            'streak' => $summary['streak']['days'],
            'badges' => collect($summary['badges'])
                ->filter(fn ($b) => $b['earned_at'] && CarbonImmutable::parse($b['earned_at'])->gte($since))
                ->values()->all(),
            'games' => collect($report['games'])->where('sessions', '>', 0)->sortByDesc('sessions')->take(6)->values()->all(),
            'areas' => $areas,
            'bandLabels' => ReportNarrative::BAND_LABEL,
            'sounds' => ['items' => array_slice($sounds['items'], 0, self::SOUNDS_SHOWN)] + $sounds,
            'narrative' => trim($this->narrative->narrative($report['areas']).' '.$sounds['summary']),
            // The one concrete thing to do at home comes first.
            'tips' => array_values(array_filter([$sounds['tip'], ...$this->narrative->recommendations($report['areas'])])),
            'url' => url("/beszed/{$child->id}/haladas"),
        ];
    }

    /** "szept. 21. – szept. 27." */
    private function period(CarbonImmutable $from, CarbonImmutable $to): string
    {
        $month = ['jan.', 'febr.', 'márc.', 'ápr.', 'máj.', 'jún.', 'júl.', 'aug.', 'szept.', 'okt.', 'nov.', 'dec.'];
        $day = fn (CarbonImmutable $d) => $month[$d->month - 1].' '.$d->day.'.';

        return $day($from->addDay()).' – '.$day($to);
    }
}
