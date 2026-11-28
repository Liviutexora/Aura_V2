<?php

namespace App\Http\Controllers;

use App\Services\EmailVerificationCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail() && ! $request->session()->has('status')) {
            return redirect()->route($user->isSuperAdmin() ? 'aura.admin.index' : 'aura.dashboard');
        }

        $sentAt = $user->email_code_sent_at?->timestamp ?? 0;

        return view('auth.verify-email', [
            'demoRoute' => 'verify-email',
            'user' => $user,
            'expiresIn' => max(0, $sentAt + EmailVerificationCodes::LIFETIME - now()->timestamp),
            'resendIn' => max(0, $sentAt + EmailVerificationCodes::RESEND_DELAY - now()->timestamp),
        ]);
    }

    public function verify(Request $request, EmailVerificationCodes $codes): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']], [
            'code.required' => 'Introdu codul primit prin email.',
            'code.regex' => 'Introdu un cod de exact 6 cifre.',
        ]);
        $error = $codes->verify($request->user(), $data['code']);
        if ($error !== null) {
            return redirect()->route('aura.verify-email')->withErrors(['code' => $error]);
        }

        $request->user()->refresh();
        $request->session()->regenerate();
        if ($request->session()->has('subscriptions.onboarding') || $request->session()->has('subscriptions.selected_plan')) {
            $request->session()->forget('url.intended');

            return redirect()->route('aura.subscriptions');
        }

        return redirect()->route('aura.verify-email')->with('status', 'Adresa ta de email a fost verificată.');
    }

    public function resend(Request $request, EmailVerificationCodes $codes): RedirectResponse
    {
        return redirect()->route('aura.verify-email')->with('status', $codes->issue($request->user(), resend: true));
    }
}
