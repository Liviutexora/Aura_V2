<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\ResetAccountPassword;
use App\Services\AccountMail;
use App\Services\EmailVerificationCodes;
use App\Services\LoginAttempts;
use Closure;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const REGISTRATION_SPONSOR = 'registration.sponsor_id';

    private const PHONE_PATTERN = '/\A\+[1-9][0-9]{7,14}\z/D';

    private const FORM_MESSAGES = [
        'email.required' => 'Introdu adresa de email.',
        'email.email' => 'Introdu o adresă de email validă.',
        'email.max' => 'Adresa de email este prea lungă.',
        'phone.required' => 'Introdu numărul de telefon.',
        'phone.string' => 'Introdu un număr de telefon valid.',
        'phone.regex' => 'Introdu un număr internațional cu + sau un număr românesc 07xxxxxxxx.',
        'name.required' => 'Introdu numele complet.',
        'name.string' => 'Introdu un nume valid.',
        'name.min' => 'Numele trebuie să aibă cel puțin 2 caractere.',
        'name.max' => 'Numele poate avea maximum 255 de caractere.',
        'password.required' => 'Introdu parola.',
        'password.string' => 'Introdu o parolă validă.',
        'password.min' => 'Parola trebuie să aibă cel puțin 8 caractere.',
        'password.confirmed' => 'Confirmarea parolei nu corespunde.',
        'terms.accepted' => 'Acceptă termenii și politica de confidențialitate pentru a continua.',
        'current_password.required' => 'Introdu parola curentă.',
        'token.*' => 'Linkul este invalid sau a expirat. Solicită un link nou.',
    ];

    public function __construct(private EmailVerificationCodes $codes) {}

    public function showLogin(string $area = 'user'): View
    {
        return view('auth.login', ['demoRoute' => 'login', 'authArea' => $area]);
    }

    public function login(Request $request, string $area = 'user'): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['bail', 'required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string'],
        ], self::FORM_MESSAGES);

        $email = strtolower($credentials['email']);
        $role = $area === 'admin' ? 'super_admin' : 'user';
        $error = app(LoginAttempts::class)->authenticate($request, $email, $credentials['password'], $role);
        if ($error !== null) {
            return back()->withErrors(['email' => $error])->onlyInput('email');
        }

        $request->session()->forget(self::REGISTRATION_SPONSOR);

        if (! $request->user()->hasVerifiedEmail()) {
            return redirect()->route('aura.verify-email')->with('status', $this->codes->issue($request->user()));
        }

        return redirect()->intended($area === 'admin' ? route('aura.admin.index') : route('aura.dashboard'));
    }

    public function showRegister(Request $request): View
    {
        $code = $request->query('ref');
        if (! $request->session()->has(self::REGISTRATION_SPONSOR)
            && is_string($code) && preg_match(User::REFERRAL_CODE_PATTERN, $code)) {
            $sponsorId = User::where('referral_code', $code)->where('status', 'active')->value('id');
            if ($sponsorId !== null) {
                $request->session()->put(self::REGISTRATION_SPONSOR, $sponsorId);
            }
        }

        return view('auth.register', [
            'demoRoute' => 'register',
            'hasReferral' => $request->session()->has(self::REGISTRATION_SPONSOR),
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower($request->input('email'))]);
        }
        $this->normalizePhone($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['bail', 'required', 'email:rfc', 'max:255', $this->uniqueEmail()],
            'phone' => ['bail', 'required', 'string', 'regex:'.self::PHONE_PATTERN],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
            'terms' => ['accepted'],
        ], self::FORM_MESSAGES);

        $sponsorId = $request->session()->get(self::REGISTRATION_SPONSOR);
        $skipEmailVerification = app()->environment('local') && ! config('auth.email_verification');
        $user = DB::transaction(function () use ($validated, $sponsorId, $skipEmailVerification): User {
            $user = new User;
            $user->forceFill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'role' => 'user',
                'status' => 'active',
            ]);
            if ($sponsorId !== null) {
                $sponsor = User::whereKey($sponsorId)->where('status', 'active')->sharedLock()->first(['id']);
                $user->sponsor()->associate($sponsor);
            }
            if ($skipEmailVerification) {
                $user->email_verified_at = now();
            }
            $user->save();
            $user->profile()->create();

            return $user;
        });

        $request->session()->forget(self::REGISTRATION_SPONSOR);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('subscriptions.onboarding', true);

        if ($skipEmailVerification) {
            return redirect()->route('aura.subscriptions');
        }

        return redirect()->route('aura.verify-email')->with('status', $this->codes->issue($user));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $this->normalizePhone($request);
        $user = $request->user();
        $inputEmail = $request->input('email');
        $normalizedEmail = is_string($inputEmail) ? Str::lower($inputEmail) : '';
        $emailChanged = $normalizedEmail !== Str::lower($user->email);

        $request->merge(['email' => $normalizedEmail]);

        $validated = $request->validateWithBag('profileUpdate', [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['bail', 'required', 'email:rfc', 'max:255', $this->uniqueEmail($user)],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:'.self::PHONE_PATTERN],
            'locale' => ['sometimes', 'required', 'in:ro'],
            'current_password' => $emailChanged
                ? ['required', 'string', 'current_password:web']
                : ['exclude'],
        ], [
            'current_password.required' => 'Introdu parola curentă pentru a schimba adresa de email.',
            'current_password.current_password' => 'Parola curentă este incorectă.',
        ] + self::FORM_MESSAGES);

        $phoneAttributes = array_key_exists('phone', $validated) ? ['phone' => $validated['phone']] : [];

        if ($emailChanged) {
            DB::transaction(function () use ($user, $validated, $phoneAttributes): void {
                $current = User::query()->lockForUpdate()->findOrFail($user->getKey());
                abort_unless($current->isActive() && $current->hasVerifiedEmail(), 403);
                if (! Hash::check($validated['current_password'], $current->getAuthPassword())) {
                    throw ValidationException::withMessages(['current_password' => 'Parola curentă este incorectă.'])->errorBag('profileUpdate');
                }
                Password::deleteToken($current);
                $current->forceFill([
                    'name' => $validated['name'], 'email' => $validated['email'],
                    'email_verified_at' => null, 'email_code_digest' => null,
                    'email_code_sent_at' => null, 'email_code_failures' => 0,
                ] + $phoneAttributes)->save();
                if (array_key_exists('locale', $validated)) {
                    $current->profile()->updateOrCreate([], ['locale' => $validated['locale']]);
                }
                Password::deleteToken($current);
            });

            return redirect()->route('aura.verify-email')->with('status', $this->codes->issue($user));
        }

        DB::transaction(function () use ($user, $validated, $phoneAttributes): void {
            $user->forceFill(['name' => $validated['name']] + $phoneAttributes)->save();
            if (array_key_exists('locale', $validated)) {
                $user->profile()->updateOrCreate([], ['locale' => $validated['locale']]);
            }
        });

        return back()->with('profile_status', 'Datele contului au fost actualizate.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ], [
            'current_password.current_password' => 'Parola actuală este incorectă.',
        ] + self::FORM_MESSAGES);

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return back()->with('password_status', 'Parola a fost schimbată.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('aura.login');
    }

    public function showForgotPassword(string $area = 'user'): View
    {
        return view('auth.forgot-password', ['demoRoute' => 'forgot-password', 'authArea' => $area]);
    }

    public function sendResetLink(Request $request, string $area = 'user'): RedirectResponse
    {
        $data = $request->validate(['email' => ['bail', 'required', 'email:rfc', 'max:255']], self::FORM_MESSAGES);
        $credentials = $this->resetCredentials($data['email'], $area);
        DB::transaction(function () use ($credentials): void {
            Password::sendResetLink($credentials, function (User $user, string $token): void {
                DB::afterCommit(static function () use ($user, $token): void {
                    \Illuminate\Support\defer(static fn () => app(AccountMail::class)->send($user, new ResetAccountPassword($token)));
                });
            });
        });

        return back()->with('status', 'Dacă adresa este înregistrată, vei primi instrucțiuni pentru resetarea parolei.');
    }

    public function showResetPassword(Request $request): Response
    {
        $email = $request->query('email');

        return response()->view('auth.reset-password', [
            'demoRoute' => 'reset-password',
            'token' => $request->route('token'),
            'email' => is_string($email) ? $email : '',
            'authArea' => $request->query('area') === 'admin' ? 'admin' : 'user',
        ])->withHeaders(['Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store, private']);
    }

    public function resetPassword(Request $request, string $area = 'user'): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:2048'],
            'email' => ['bail', 'required', 'email:rfc'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ], self::FORM_MESSAGES);

        $credentials = $this->resetCredentials($validated['email'], $area);
        $link = ResetAccountPassword::readLinkToken($validated['token'], $credentials['role']);
        $status = Password::INVALID_TOKEN;
        if ($link !== null) {
            $credentials += $link + ['password' => $validated['password']];
            $status = DB::transaction(fn () => Password::reset($credentials, function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }));
        }

        return $status === Password::PASSWORD_RESET
            ? redirect()->route($area === 'admin' ? 'aura.admin.login' : 'aura.login')->with('status', 'Parola a fost schimbată. Te poți autentifica.')
            : redirect()->route($area === 'admin' ? 'aura.admin.password.request' : 'aura.forgot-password')
                ->withErrors(['email' => 'Linkul este invalid sau a expirat. Solicită un link nou.']);
    }

    private function normalizePhone(Request $request): void
    {
        if (! is_string($request->input('phone'))) {
            return;
        }

        $phone = preg_replace('/[\x20\t().-]+/', '', trim($request->input('phone')));
        if (preg_match('/\A07[0-9]{8}\z/D', $phone)) {
            $phone = '+4'.$phone;
        }

        $request->merge(['phone' => $phone === '' ? null : $phone]);
    }

    private function uniqueEmail(?User $ignore = null): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
            $query = User::whereRaw('LOWER(email) = ?', [Str::lower($value)]);
            if ($ignore) {
                $query->whereKeyNot($ignore->getKey());
            }
            if ($query->exists()) {
                $fail('Adresa de email este deja utilizată.');
            }
        };
    }

    /** @return array{0: Closure, status: string, role: string} */
    private function resetCredentials(string $email, string $area): array
    {
        $email = Str::lower($email);
        $id = User::whereRaw('LOWER(email) = ?', [$email])->value('id');

        return [
            static fn (Builder $query): Builder => $query->whereKey($id)->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate(),
            'status' => 'active',
            'role' => $area === 'admin' ? 'super_admin' : 'user',
        ];
    }
}
