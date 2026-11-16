<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeeInvitation extends Notification
{
    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Uitnodiging voor '.config('app.name'))
            ->greeting('Hallo '.$notifiable->name.',')
            ->line('Je bent uitgenodigd om in te loggen. Kies een wachtwoord om je account te activeren.')
            ->action('Account activeren', $this->url($notifiable))
            ->line('Deze link is 7 dagen geldig.');
    }

    public function url(object $notifiable): string
    {
        return route('invitation.show', ['token' => $this->token, 'email' => $notifiable->email]);
    }
}
