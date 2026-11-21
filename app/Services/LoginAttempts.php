<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Timebox;

use function Illuminate\Support\defer;

class LoginAttempts
{
    public const INVALID = 'Datele de autentificare nu sunt valide.';
    public const LOCKED = 'Autentificarea este temporar blocată timp de 24 de ore de la a treia încercare greșită.';

    public function authenticate(Request $request, string $email, #[\SensitiveParameter] string $password, string $role): ?string
    {
        $lookup = static fn (Builder $query): Builder => $query->whereRaw('LOWER(email) = ?', [$email]);
        $id = $lookup(User::query())->value('id');
        $request->session()->put('login_challenge', true);

        return (new Timebox)->call(fn () => DB::transaction(function () use ($request, $id, $role, $lookup, $password): ?string {
            $account = $id ? $lookup(User::whereKey($id))->where('role', $role)->where('status', 'active')->lockForUpdate()->first() : null;
            if ($account) {
                $key = 'login-proof:'.hash_hmac('sha256', $account->id.'|'.$role.'|'.$account->email, config('app.key'));
                if ($account->login_locked_until?->isFuture()) {
                    $owner = $request->session()->get('known_login_lock') === $key || Hash::check($password, $account->getAuthPassword());

                    return $owner ? self::LOCKED : self::INVALID;
                }

                $failures = $account->login_locked_until ? 0 : $account->login_failures;
                if ($failures === 2 && ! $this->hasProof($key, $request->input('answer'))) {
                    $this->sendProof($account, $key);

                    return self::INVALID;
                }
            }

            $authenticated = Auth::attemptWhen(
                [$lookup, 'password' => $password],
                static fn (User $user): bool => $account !== null && $user->is($account) && $user->isActive() && $user->role === $role,
                $request->boolean('remember'),
            );
            if (! $account) {
                return self::INVALID;
            }
            if ($authenticated) {
                if ($account->login_failures || $account->login_locked_until) {
                    $account->forceFill(['login_failures' => 0, 'login_locked_until' => null])->save();
                }
                Cache::forget($key);
                $request->session()->forget(['login_challenge', 'known_login_lock']);

                return null;
            }

            $failures++;
            $account->forceFill([
                'login_failures' => $failures,
                'login_locked_until' => $failures === 3 ? now()->addHours(24) : null,
            ])->save();

            if ($failures === 3) {
                Cache::forget($key);
                $request->session()->put('known_login_lock', $key);

                return self::LOCKED;
            }
            if ($failures === 2) {
                $this->sendProof($account, $key);
            }

            return self::INVALID;
        }), 200000);
    }

    private function hasProof(string $key, #[\SensitiveParameter] mixed $answer): bool
    {
        $nonce = Cache::get($key);

        return is_string($nonce) && is_string($answer) && hash_equals($this->proof($key, $nonce), strtoupper(trim($answer)));
    }

    private function sendProof(User $account, string $key): void
    {
        if (RateLimiter::tooManyAttempts($key.':hour', 5) || ! Cache::add($key.':delivery', true, 60)) {
            return;
        }

        $nonce = Cache::remember($key, 600, static fn (): string => bin2hex(random_bytes(16)));
        $proof = $this->proof($key, $nonce);
        RateLimiter::hit($key.':hour', 3600);
        DB::afterCommit(static function () use ($account, $proof): void {
            defer(static fn () => app(AccountMail::class)->send($account, new VerifyEmailCode($proof, forLogin: true)));
        });
    }

    private function proof(string $key, string $nonce): string
    {
        return strtoupper(substr(hash_hmac('sha256', $key.'|'.$nonce, config('app.key')), 0, 16));
    }
}
