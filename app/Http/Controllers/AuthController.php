<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        $user = User::whereRaw('LOWER(email) = ?', [strtolower($credentials['email'])])->first();
        $valid = $user && $user->isActive() && Hash::check($credentials['password'], $user->password);
        $valid = $valid && ($area === 'admin' ? $user->role === 'super_admin' : $user->role === 'user');

        if (! $valid) {
            return back()->withErrors(['email' => 'Datele de autentificare nu sunt valide.'])->onlyInput('email');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

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
