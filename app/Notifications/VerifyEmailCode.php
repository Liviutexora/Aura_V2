<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailCode extends Notification
{
    public function __construct(public readonly string $code, public readonly bool $forLogin = false) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->forLogin ? 'Confirmă încercarea de autentificare AURA' : 'Verifică adresa de email pentru AURA')
            ->view('auth.mail.account', ['code' => $this->code, 'forLogin' => $this->forLogin]);
    }
}
