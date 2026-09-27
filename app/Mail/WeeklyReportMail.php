<?php

namespace App\Mail;

use App\Beszed\Reports\WeeklyReportPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

/** The Sunday e-mail: a child's week in a styled HTML letter, with the same report as a PDF. */
class WeeklyReportMail extends Mailable
{
    use Queueable;

    /** @param  array  $report  App\Beszed\Reports\WeeklyReport::for() */
    public function __construct(public array $report, public string $unsubscribeUrl) {}

    public function envelope(): Envelope
    {
        $child = $this->report['child'];

        return new Envelope(subject: trim(($child['sign'] ?? '🦄').' '.$child['name'].' heti beszámolója · '.$this->report['period']));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.weekly-report', with: ['r' => $this->report, 'unsubscribeUrl' => $this->unsubscribeUrl]);
    }

    public function attachments(): array
    {
        $file = Str::slug($this->report['child']['name']).'-heti-beszamolo-'.now()->toDateString().'.pdf';

        return [Attachment::fromData(fn () => WeeklyReportPdf::render($this->report), $file)->withMime('application/pdf')];
    }
}
