<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The evening nudge: "ma még nem játszottatok", with the streak that is at stake and a link into the app. */
class PlayReminderMail extends Mailable
{
    use Queueable;

    /** @param  array<int, array{id: int, name: string, sign: string, streak: int, url: string}>  $children */
    public function __construct(public array $children, public string $unsubscribeUrl) {}

    public function envelope(): Envelope
    {
        $names = collect($this->children)->pluck('name')->join(', ', ' és ');

        return new Envelope(subject: "🦄 Csillám várja {$names} mai játékát");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.play-reminder', with: ['children' => $this->children, 'unsubscribeUrl' => $this->unsubscribeUrl]);
    }
}
