<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

class AccountMail
{
    public function send(User $recipient, Notification $notification): bool
    {
        try {
            $transport = Mail::mailer()->getSymfonyTransport();
            if (! $transport instanceof SmtpTransport && ! (app()->environment('testing') && $transport instanceof ArrayTransport)) {
                Log::warning('Expedierea mesajelor de cont necesită un transport SMTP configurat.');

                return false;
            }

            $recipient->notifyNow($notification);

            return true;
        } catch (Throwable $exception) {
            Log::error('Expedierea mesajului de cont a eșuat.', ['exception_type' => $exception::class]);

            return false;
        }
    }
}
