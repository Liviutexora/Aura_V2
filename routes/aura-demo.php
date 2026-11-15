<?php

use Illuminate\Support\Facades\Route;

// UI-only preview routes. Replace with authenticated controllers before production.
Route::get('/', fn () => view('public.landing', ['demoRoute' => 'home']))->name('aura.home');
Route::get('/pricing', fn () => view('public.pricing', ['demoRoute' => 'pricing']))->name('aura.pricing');
Route::get('/dashboard', fn () => view('dashboard.index', ['demoRoute' => 'dashboard']))->name('aura.dashboard');
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
Route::get('/login', fn () => view('auth.login', ['demoRoute' => 'login']))->name('aura.login');
Route::get('/register', fn () => view('auth.register', ['demoRoute' => 'register']))->name('aura.register');
Route::get('/forgot-password', fn () => view('auth.forgot-password', ['demoRoute' => 'forgot-password']))->name('aura.forgot-password');
Route::get('/reset-password', fn () => view('auth.reset-password', ['demoRoute' => 'reset-password']))->name('aura.reset-password');
Route::get('/verify-email', fn () => view('auth.verify-email', ['demoRoute' => 'verify-email']))->name('aura.verify-email');
