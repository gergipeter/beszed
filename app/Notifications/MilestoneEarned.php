<?php

namespace App\Notifications;

use App\Models\Child;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One badge a child just earned, mailed to the parent (opt-out in account preferences). */
class MilestoneEarned extends Notification
{
    /** @param  array{id: string, name: string, emoji: string}  $badge */
    public function __construct(private Child $child, private array $badge) {}

    public function badgeId(): string
    {
        return $this->badge['id'];
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->badge['emoji']} {$this->child->name} új matricát szerzett: {$this->badge['name']}")
            ->greeting("{$this->badge['emoji']} Szép munka!")
            ->line("{$this->child->name} most szerezte meg a „{$this->badge['name']}” matricát a Beszéd appban.")
            ->action('Megnézem', url("/beszed/{$this->child->id}/matricak"))
            ->line('Ezt az e-mailt azért kaptad, mert bekapcsoltad a matrica-értesítéseket. Kikapcsolhatod a fiókbeállításokban.');
    }
}
