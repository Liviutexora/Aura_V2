<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class EmailVerificationCodes
{
    public const LIFETIME = 600;
    public const RESEND_DELAY = 60;
    public const MAX_FAILURES = 3;

    public function __construct(private AccountMail $mail) {}

    public function issue(User $user, bool $resend = false): string
    {
        $code = DB::transaction(function () use (&$user, $resend): ?string {
            $user = User::query()->lockForUpdate()->findOrFail($user->getKey());
            abort_unless($user->isActive(), 403);

            $sentAt = $user->email_code_sent_at;
            if ($user->hasVerifiedEmail() || ($sentAt && (! $resend || $sentAt->timestamp + self::RESEND_DELAY > now()->timestamp))) {
                return null;
            }

            $key = 'verification-mails:'.$user->getKey();
            if (RateLimiter::tooManyAttempts($key, 5)) {
                return null;
            }
            RateLimiter::hit($key, 3600);

            do {
                $code = sprintf('%06d', random_int(0, 999999));
                $digest = $this->digest($user, $code);
            } while ($user->email_code_digest === $digest);

            $user->forceFill(['email_code_digest' => $digest, 'email_code_sent_at' => now(), 'email_code_failures' => 0])->save();

            return $code;
        });

        if ($code === null) {
            return 'Folosește ultimul cod primit sau solicită unul nou după intervalul de așteptare. Poți primi maximum 5 coduri pe oră.';
        }

        return $this->mail->send($user, new VerifyEmailCode($code))
            ? 'Am trimis codul de verificare la adresa ta de email.'
            : 'Codul nu a putut fi trimis. Poți solicita alt cod după intervalul de așteptare.';
    }

    public function verify(User $user, #[\SensitiveParameter] string $code): ?string
    {
        return DB::transaction(function () use ($user, $code): ?string {
            $user = User::query()->lockForUpdate()->findOrFail($user->getKey());
            abort_unless($user->isActive(), 403);
            if ($user->hasVerifiedEmail()) {
                return 'Codul nu mai este valabil. Adresa a fost deja verificată.';
            }
            if (! $user->email_code_sent_at || $user->email_code_sent_at->timestamp + self::LIFETIME <= now()->timestamp) {
                return 'Codul a expirat. Solicită un cod nou.';
            }
            if ($user->email_code_failures >= self::MAX_FAILURES || ! $user->email_code_digest) {
                return 'Ai epuizat cele 3 încercări. Solicită un cod nou.';
            }
            if (! hash_equals($user->email_code_digest, $this->digest($user, $code))) {
                $user->increment('email_code_failures');
                $remaining = self::MAX_FAILURES - $user->email_code_failures;

                return match ($remaining) {
                    0 => 'Ai epuizat cele 3 încercări. Solicită un cod nou.',
                    1 => 'Cod incorect. Mai ai o încercare.',
                    default => 'Cod incorect. Mai ai 2 încercări.',
                };
            }

            $user->forceFill(['email_code_digest' => null, 'email_code_sent_at' => null, 'email_code_failures' => 0]);
            $user->markEmailAsVerified();
            event(new \Illuminate\Auth\Events\Verified($user));

            return null;
        });
    }

    private function digest(User $user, #[\SensitiveParameter] string $code): string
    {
        return hash_hmac('sha256', implode('|', [$user->getKey(), $user->email, $code]), config('app.key'));
    }
}
