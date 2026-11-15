<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Register admin-only anonymous components. Move to AppServiceProvider when replacing demo routes.
Blade::anonymousComponentPath(resource_path('views/admin/components'), 'aura-admin');

// LOCAL PREVIEW ONLY: no new authentication system or production authorization is implemented.
$loadAuraAdminDemo = static function (string $page, ?string $recordId = null, ?string $mode = null) {
    abort_unless(app()->environment(['local', 'testing']), 403);
    $demoData = json_decode(file_get_contents(resource_path('data/admin-demo.json')), true, 512, JSON_THROW_ON_ERROR);
    $routeMap = json_decode(file_get_contents(resource_path('data/admin-route-map.json')), true, 512, JSON_THROW_ON_ERROR);
    abort_unless(isset($routeMap[$page]), 404);
    $pageTitle = $demoData['tables'][$page][0] ?? match ($page) {
        'dashboard' => 'Dashboard', 'analytics' => 'Rapoarte & Analize', 'financial-reports' => 'Rapoarte financiare',
        'integrations' => 'Integrări aplicații', 'gamification' => 'Gamification', 'wallet' => 'Comisioane & Wallet',
        'settings' => 'Setări', 'roles' => 'Roluri & permisiuni', default => 'Administrare',
    };
    $tableKey = $page === 'commissions' ? 'transactions' : $page;
    $tableRows = $demoData['fixtures'][$tableKey] ?? [];
    if ($page === 'commissions') $tableRows = array_values(array_filter($tableRows, static fn ($row) => $row['type'] === 'Comision ecosistem'));
    $demoTable = ['columns' => explode(',', $demoData['tables'][$page][1] ?? ''), 'rows' => $tableRows];
    $demoPage = $page . ($recordId !== null ? '/'.$recordId : '') . ($mode !== null ? '/'.$mode : '');
    return view($recordId === null ? $routeMap[$page] : 'admin.pages.record', compact('demoData', 'demoTable', 'demoPage', 'pageTitle'));
};

Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('aura.admin.login');
Route::post('/admin/login', [AuthController::class, 'login'])->defaults('area', 'admin')->middleware('throttle:5,1')->name('aura.admin.login.store');
Route::get('/admin/forgot-password', [AuthController::class, 'showForgotPassword'])->defaults('area', 'admin')->name('aura.admin.password.request');
Route::post('/admin/forgot-password', [AuthController::class, 'sendResetLink'])->defaults('area', 'admin')->middleware('throttle:3,1')->name('aura.admin.password.email');

Route::prefix('admin')->name('aura.admin.')->middleware(['auth', 'active', 'super_admin'])->group(static function () use ($loadAuraAdminDemo) {
    Route::get('/', static fn () => $loadAuraAdminDemo('dashboard'))->name('index');
    Route::get('/users', static fn () => $loadAuraAdminDemo('users'))->name('users');
    Route::get('/subscriptions', static fn () => $loadAuraAdminDemo('subscriptions'))->name('subscriptions');
    Route::get('/opportunities', static fn () => $loadAuraAdminDemo('opportunities'))->name('opportunities');
    Route::get('/categories', static fn () => $loadAuraAdminDemo('categories'))->name('categories');
    Route::get('/eligibility', static fn () => $loadAuraAdminDemo('eligibility'))->name('eligibility');
    Route::get('/missions', static fn () => $loadAuraAdminDemo('missions'))->name('missions');
    Route::get('/verification', static fn () => $loadAuraAdminDemo('verification'))->name('verification');
    Route::get('/levels', static fn () => $loadAuraAdminDemo('levels'))->name('levels');
    Route::get('/xprules', static fn () => $loadAuraAdminDemo('xprules'))->name('xprules');
    Route::get('/achievements', static fn () => $loadAuraAdminDemo('achievements'))->name('achievements');
    Route::get('/transactions', static fn () => $loadAuraAdminDemo('transactions'))->name('transactions');
    Route::get('/commissions', static fn () => $loadAuraAdminDemo('commissions'))->name('commissions');
    Route::get('/payouts', static fn () => $loadAuraAdminDemo('payouts'))->name('payouts');
    Route::get('/referrals', static fn () => $loadAuraAdminDemo('referrals'))->name('referrals');
    Route::get('/conversions', static fn () => $loadAuraAdminDemo('conversions'))->name('conversions');
    Route::get('/links', static fn () => $loadAuraAdminDemo('links'))->name('links');
    Route::get('/events', static fn () => $loadAuraAdminDemo('events'))->name('events');
    Route::get('/notifications', static fn () => $loadAuraAdminDemo('notifications'))->name('notifications');
    Route::get('/announcements', static fn () => $loadAuraAdminDemo('announcements'))->name('announcements');
    Route::get('/messageTemplates', static fn () => $loadAuraAdminDemo('messageTemplates'))->name('messageTemplates');
    Route::get('/plans', static fn () => $loadAuraAdminDemo('plans'))->name('plans');
    Route::get('/audit', static fn () => $loadAuraAdminDemo('audit'))->name('audit');
    Route::get('/dashboard', static fn () => $loadAuraAdminDemo('dashboard'))->name('dashboard');
    Route::get('/analytics', static fn () => $loadAuraAdminDemo('analytics'))->name('analytics');
    Route::get('/financial-reports', static fn () => $loadAuraAdminDemo('financial-reports'))->name('financial-reports');
    Route::get('/activity', static fn () => $loadAuraAdminDemo('activity'))->name('activity');
    Route::get('/mission-templates', static fn () => $loadAuraAdminDemo('mission-templates'))->name('mission-templates');
    Route::get('/gamification', static fn () => $loadAuraAdminDemo('gamification'))->name('gamification');
    Route::get('/progress', static fn () => $loadAuraAdminDemo('progress'))->name('progress');
    Route::get('/wallet', static fn () => $loadAuraAdminDemo('wallet'))->name('wallet');
    Route::get('/referral-rules', static fn () => $loadAuraAdminDemo('referral-rules'))->name('referral-rules');
    Route::get('/integrations', static fn () => $loadAuraAdminDemo('integrations'))->name('integrations');
    Route::get('/integration-config', static fn () => $loadAuraAdminDemo('integration-config'))->name('integration-config');
    Route::get('/delivery-log', static fn () => $loadAuraAdminDemo('delivery-log'))->name('delivery-log');
    Route::get('/roles', static fn () => $loadAuraAdminDemo('roles'))->name('roles');
    Route::get('/settings', static fn () => $loadAuraAdminDemo('settings'))->name('settings');
    Route::get('/{entity}/{recordId}/{mode?}', static fn (string $entity, string $recordId, ?string $mode = null) => $loadAuraAdminDemo($entity, $recordId, $mode))->where('mode', 'edit')->name('record');
});
