<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

// UI-only preview routes. Replace with authenticated controllers before production.
Route::get('/', [ProjectController::class, 'landing'])->name('aura.home');
Route::get('/pricing', fn () => view('public.pricing', ['demoRoute' => 'pricing']))->name('aura.pricing');
Route::middleware(['auth', 'active', 'verified:aura.verify-email'])->group(function (): void {
    Route::get('/dashboard', [ProjectController::class, 'dashboard'])->name('aura.dashboard');
});
Route::middleware(['auth', 'active', 'verified:aura.verify-email'])->group(function (): void {
    Route::get('/opportunities', [ProjectController::class, 'catalog'])->name('aura.opportunities');
    Route::get('/opportunities/{opportunity}', [ProjectController::class, 'opportunity'])->whereNumber('opportunity')->name('aura.opportunities.show');
    Route::post('/opportunities/{opportunity}/projects', [ProjectController::class, 'accept'])->name('aura.projects.accept');
    Route::get('/projects', [ProjectController::class, 'index'])->name('aura.projects');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->whereNumber('project')->name('aura.projects.show');
    Route::post('/projects/{project}/start', [ProjectController::class, 'start'])->name('aura.projects.start');
    Route::post('/projects/{project}/external-earnings', [ProjectController::class, 'earning'])->name('aura.projects.earning');
    Route::get('/missions', [MissionController::class, 'index'])->name('aura.missions');
    Route::get('/missions/{mission}', [MissionController::class, 'show'])->whereNumber('mission')->name('aura.missions.show');
    Route::patch('/missions/{mission}/steps/{step}', [MissionController::class, 'check'])->name('aura.missions.check');
    Route::post('/missions/{mission}/submissions', [MissionController::class, 'submit'])->name('aura.missions.submit');
    Route::match(['put', 'delete'], '/missions/{mission}/reminder', [MissionController::class, 'reminder'])->name('aura.missions.reminder');
    Route::get('/mission-submissions/{submission}/evidence', [MissionController::class, 'evidence'])->name('aura.missions.evidence');
    Route::get('/achievements', fn () => view('achievements.index', ['demoRoute' => 'achievements']))->name('aura.achievements');
    Route::get('/wallet', fn () => view('wallet.index', ['demoRoute' => 'wallet']))->name('aura.wallet');
    Route::get('/referrals', ReferralController::class)->name('aura.referrals');
    Route::get('/integrations', fn () => view('integrations.index', ['demoRoute' => 'integrations']))->name('aura.integrations');
    Route::get('/subscriptions', [SubscriptionController::class, 'show'])->name('aura.subscriptions');
    Route::get('/subscriptions/overview', [SubscriptionController::class, 'overview'])->name('aura.subscriptions.overview');
    Route::post('/subscriptions/trial', [SubscriptionController::class, 'trial'])->middleware('throttle:10,1')->name('aura.subscriptions.trial');
    Route::post('/subscriptions/redeem', [SubscriptionController::class, 'redeem'])->middleware('throttle:10,1')->name('aura.subscriptions.redeem');
    Route::get('/profile', fn () => redirect()->route('aura.settings'))->name('aura.profile');
    Route::get('/settings', fn () => view('profile.settings', ['demoRoute' => 'settings']))->name('aura.settings');
    Route::post('/settings/profile', [AuthController::class, 'updateProfile'])->middleware('throttle:5,1')->name('aura.settings.profile.update');
    Route::post('/settings/password', [AuthController::class, 'updatePassword'])->middleware('throttle:5,1')->name('aura.settings.password.update');
});
Route::get('/login', [AuthController::class, 'showLogin'])->name('aura.login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1,account-login:')->name('aura.login.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('aura.logout');
Route::middleware('guest')->group(function (): void {
    Route::get('/register', [AuthController::class, 'showRegister'])->block()->name('aura.register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1')->block()->name('aura.register.store');
});
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('aura.forgot-password');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:3,1,password-request:')->name('aura.password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('aura.password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,1,password-update:')->name('aura.password.update');
Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/verify-email', [EmailVerificationController::class, 'show'])->name('aura.verify-email');
    Route::post('/verify-email', [EmailVerificationController::class, 'verify'])->middleware('throttle:10,1,email-code-check:')->name('aura.verify-email.check');
    Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:3,1,email-code-resend:')->name('aura.verify-email.resend');
});
