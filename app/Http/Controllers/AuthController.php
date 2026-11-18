<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(string $area = 'user'): View
    {
        return view('auth.login', ['demoRoute' => 'login', 'authArea' => $area]);
    }

    public function login(Request $request, string $area = 'user'): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower($credentials['email']);
        $requiredRole = $area === 'admin' ? 'super_admin' : 'user';
        $authenticated = Auth::attemptWhen(
            [
                static fn (Builder $query): Builder => $query->whereRaw('LOWER(email) = ?', [$email]),
                'password' => $credentials['password'],
            ],
            static fn (User $user): bool => $user->isActive() && $user->role === $requiredRole,
            $request->boolean('remember'),
        );

        if (! $authenticated) {
            return back()->withErrors(['email' => 'Datele de autentificare nu sunt valide.'])->onlyInput('email');
        }

        return redirect()->intended($area === 'admin' ? route('aura.admin.index') : route('aura.dashboard'));
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
            'terms' => ['accepted'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = new User;
            $user->forceFill([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'password' => Hash::make($validated['password']),
                'role' => 'user',
                'status' => 'active',
                'sponsor_id' => null,
                'referral_code' => $this->uniqueReferralCode($validated['name']),
            ]);
            $user->save();
            $user->profile()->create();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('aura.dashboard');
    }

    private function uniqueReferralCode(string $name): string
    {
        $prefix = preg_replace('/[^A-Za-z0-9]/', '', Str::ascii(trim(explode(' ', $name)[0]))) ?: 'Aura';
        $prefix = substr($prefix, 0, 12);

        do {
            $code = $prefix.Str::upper(Str::random(5));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $normalizedEmail = Str::lower($request->string('email')->toString());
        $emailChanged = $normalizedEmail !== Str::lower($user->email);

        $request->merge(['email' => $normalizedEmail]);

        $validated = $request->validateWithBag('profileUpdate', [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
            'current_password' => $emailChanged
                ? ['required', 'string', 'current_password:web']
                : ['exclude'],
        ], [
            'email.unique' => 'Adresa de email este deja utilizată.',
            'current_password.required' => 'Introdu parola curentă pentru a schimba adresa de email.',
            'current_password.current_password' => 'Parola curentă este incorectă.',
        ]);

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if ($emailChanged) {
            $attributes['email_verified_at'] = null;
        }

        $user->forceFill($attributes)->save();

        return back()->with(
            'profile_status',
            $emailChanged
                ? 'Datele contului au fost actualizate. Noua adresă de email este neverificată.'
                : 'Datele contului au fost actualizate.',
        );
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'current_password.current_password' => 'Parola actuală este incorectă.',
        ]);

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
        $request->validate(['email' => ['required', 'email:rfc']]);
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Dacă adresa este înregistrată, vei primi instrucțiuni pentru resetarea parolei.');
    }

    public function showResetPassword(Request $request): View
    {
        return view('auth.reset-password', ['demoRoute' => 'reset-password', 'token' => $request->route('token'), 'email' => $request->query('email')]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset($validated, function (User $user, string $password): void {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('aura.login')->with('status', 'Parola a fost schimbată.')->with('authArea', 'user')
            : back()->withErrors(['email' => __($status)]);
    }
}
