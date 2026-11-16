<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AuthController;

// UI-only preview routes. Replace with authenticated controllers before production.
Route::get('/', fn () => view('public.landing', ['demoRoute' => 'home', 'publicOnly' => ! Auth::check()]))->name('aura.home');
Route::get('/pricing', fn () => view('public.pricing', ['demoRoute' => 'pricing']))->name('aura.pricing');
Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/dashboard', fn () => view('dashboard.index', ['demoRoute' => 'dashboard']))->name('aura.dashboard');
});
Route::middleware(['auth', 'active'])->group(function (): void {
Route::get('/opportunities', fn () => view('opportunities.index', ['demoRoute' => 'opportunities']))->name('aura.opportunities');
Route::get('/opportunities/demo', fn () => view('opportunities.show', ['demoRoute' => 'opportunity/1']))->name('aura.opportunities.demo');
Route::get('/missions', fn () => view('missions.index', ['demoRoute' => 'missions']))->name('aura.missions');
Route::get('/missions/demo', fn () => view('missions.show', ['demoRoute' => 'mission/1']))->name('aura.missions.demo');
Route::get('/projects', fn () => view('projects.index', ['demoRoute' => 'projects']))->name('aura.projects');
Route::get('/achievements', fn () => view('achievements.index', ['demoRoute' => 'achievements']))->name('aura.achievements');
Route::get('/wallet', fn () => view('wallet.index', ['demoRoute' => 'wallet']))->name('aura.wallet');
Route::get('/referrals', fn () => view('referrals.index', ['demoRoute' => 'referrals']))->name('aura.referrals');
Route::get('/integrations', fn () => view('integrations.index', ['demoRoute' => 'integrations']))->name('aura.integrations');
Route::get('/subscriptions', fn () => view('subscriptions.index', ['demoRoute' => 'subscriptions']))->name('aura.subscriptions');
Route::get('/profile', fn () => view('profile.index', ['demoRoute' => 'profile']))->name('aura.profile');
Route::get('/settings', fn () => view('profile.settings', ['demoRoute' => 'settings']))->name('aura.settings');
Route::post('/settings/profile', [AuthController::class, 'updateProfile'])->middleware('throttle:5,1')->name('aura.settings.profile.update');
Route::post('/settings/password', [AuthController::class, 'updatePassword'])->middleware('throttle:5,1')->name('aura.settings.password.update');
});
Route::get('/login', [AuthController::class, 'showLogin'])->name('aura.login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('aura.login.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('aura.logout');
Route::get('/register', fn () => view('auth.register', ['demoRoute' => 'register']))->name('aura.register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1')->name('aura.register.store');
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('aura.forgot-password');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:3,1')->name('aura.password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('aura.password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,1')->name('aura.password.update');
Route::get('/verify-email', fn () => view('auth.verify-email', ['demoRoute' => 'verify-email']))->name('aura.verify-email');
