<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetAccountPassword extends Notification
{
    public function __construct(public readonly string $token) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $path = route('aura.password.reset', [
            'token' => strtr(Crypt::encryptString(implode('|', [$notifiable->getKey(), $notifiable->role, $this->token])), '+/', '-_'),
            'email' => $notifiable->getEmailForPasswordReset(),
            'area' => $notifiable->isSuperAdmin() ? 'admin' : 'user',
        ], false);

        return (new MailMessage)->subject('Resetarea parolei AURA')
            ->view('auth.mail.account', ['resetUrl' => rtrim(config('app.url'), '/').'/'.ltrim($path, '/')]);
    }
    /** @return array{id: string, token: string}|null */
    public static function readLinkToken(#[\SensitiveParameter] string $linkToken, string $role): ?array
    {
        try {
            $parts = explode('|', Crypt::decryptString(strtr($linkToken, '-_', '+/')), 3);
        } catch (DecryptException) {
            return null;
        }

        if (count($parts) !== 3 || ! ctype_digit($parts[0]) || $parts[1] !== $role) {
            return null;
        }

        return ['id' => $parts[0], 'token' => $parts[2]];
    }
}
