<?php

use App\Http\Controllers\Admin\ProjectCatalogController;
use App\Http\Controllers\Admin\ProjectOperationsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Services\CatalogEditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

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
    if ($page === 'commissions') {
        $tableRows = array_values(array_filter($tableRows, static fn ($row) => $row['type'] === 'Comision ecosistem'));
    }
    $demoTable = ['columns' => explode(',', $demoData['tables'][$page][1] ?? ''), 'rows' => $tableRows];
    $demoPage = $page.($recordId !== null ? '/'.$recordId : '').($mode !== null ? '/'.$mode : '');

    return view($recordId === null ? $routeMap[$page] : 'admin.pages.record', compact('demoData', 'demoTable', 'demoPage', 'pageTitle'));
};

Route::get('/admin/login', fn (AuthController $controller) => $controller->showLogin('admin'))->name('aura.admin.login');
Route::post('/admin/login', fn (Request $request, AuthController $controller) => $controller->login($request, 'admin'))->middleware('throttle:5,1')->name('aura.admin.login.store');
Route::get('/admin/forgot-password', fn (AuthController $controller) => $controller->showForgotPassword('admin'))->name('aura.admin.password.request');
Route::post('/admin/forgot-password', fn (Request $request, AuthController $controller) => $controller->sendResetLink($request, 'admin'))->middleware('throttle:3,1')->name('aura.admin.password.email');

Route::prefix('admin')->name('aura.admin.')->middleware(['auth', 'active', 'super_admin'])->group(static function () use ($loadAuraAdminDemo) {
    Route::get('/', static fn () => $loadAuraAdminDemo('dashboard'))->name('index');
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::post('/users/{user}/password', [UserController::class, 'resetPassword'])
        ->middleware('throttle:admin-password-reset')
        ->name('users.password.reset');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    foreach (array_keys(CatalogEditor::TITLES) as $entity) {
        Route::get($entity, [ProjectCatalogController::class, 'index'])->defaults('entity', $entity)->name($entity);
        Route::get($entity.'/export', [ProjectCatalogController::class, 'export'])->defaults('entity', $entity)->name($entity.'.export');
    }
    foreach (array_keys(CatalogEditor::MODELS) as $entity) {
        Route::get($entity.'/create', [ProjectCatalogController::class, 'form'])->defaults('entity', $entity)->name($entity.'.create');
        Route::post($entity, [ProjectCatalogController::class, 'save'])->defaults('entity', $entity)->name($entity.'.store');
        Route::get($entity.'/{record}/edit', [ProjectCatalogController::class, 'form'])->whereNumber('record')->defaults('entity', $entity)->name($entity.'.edit');
        Route::get($entity.'/{record}', [ProjectCatalogController::class, 'form'])->whereNumber('record')->defaults('entity', $entity)->name($entity.'.show');
        Route::patch($entity.'/{record}', [ProjectCatalogController::class, 'save'])->whereNumber('record')->defaults('entity', $entity)->name($entity.'.update');
    }
    foreach (['opportunities', 'categories', 'eligibility'] as $entity) {
        Route::patch($entity.'/{record}/archive', [ProjectCatalogController::class, 'archive'])->whereNumber('record')->defaults('entity', $entity)->name($entity.'.archive');
    }
    foreach (['projects', 'missions'] as $entity) {
        Route::get($entity.'/create', [ProjectOperationsController::class, 'form'])->defaults('entity', $entity)->name($entity.'.create');
        Route::post($entity, [ProjectOperationsController::class, 'allocate'])->defaults('entity', $entity)->name($entity.'.store');
    }
    Route::get('projects/{project}', [ProjectOperationsController::class, 'project'])->whereNumber('project')->name('projects.show');
    Route::get('missions/{mission}', [ProjectOperationsController::class, 'mission'])->whereNumber('mission')->name('missions.show');
    Route::patch('missions/{mission}', [ProjectOperationsController::class, 'deadline'])->name('missions.update');
    Route::get('verification/{submission}', [ProjectOperationsController::class, 'verification'])->whereNumber('submission')->name('verification.show');
    Route::patch('verification/{submission}', [ProjectOperationsController::class, 'review'])->name('verification.review');
    Route::get('/subscriptions', static fn () => $loadAuraAdminDemo('subscriptions'))->name('subscriptions');
    Route::get('/levels', static fn () => $loadAuraAdminDemo('levels'))->name('levels');
    Route::get('/xprules', static fn () => $loadAuraAdminDemo('xprules'))->name('xprules');
    Route::get('/achievements', static fn () => $loadAuraAdminDemo('achievements'))->name('achievements');
    Route::get('/transactions', static fn () => $loadAuraAdminDemo('transactions'))->name('transactions');
    Route::get('/commissions', static fn () => $loadAuraAdminDemo('commissions'))->name('commissions');
    Route::get('/payouts', static fn () => $loadAuraAdminDemo('payouts'))->name('payouts');
    foreach (['referrals', 'referral-rules', 'conversions', 'links'] as $section) {
        Route::get($section.'/{legacyPath?}', static fn () => redirect()->route('aura.admin.users'))
            ->where('legacyPath', '.*')->name($section);
    }
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
    Route::get('/gamification', static fn () => $loadAuraAdminDemo('gamification'))->name('gamification');
    Route::get('/progress', static fn () => $loadAuraAdminDemo('progress'))->name('progress');
    Route::get('/wallet', static fn () => $loadAuraAdminDemo('wallet'))->name('wallet');
    Route::get('/integrations', static fn () => $loadAuraAdminDemo('integrations'))->name('integrations');
    Route::get('/integration-config', static fn () => $loadAuraAdminDemo('integration-config'))->name('integration-config');
    Route::get('/delivery-log', static fn () => $loadAuraAdminDemo('delivery-log'))->name('delivery-log');
    Route::get('/roles', static fn () => $loadAuraAdminDemo('roles'))->name('roles');
    Route::get('/settings', static fn () => $loadAuraAdminDemo('settings'))->name('settings');
    Route::get('/{entity}/{recordId}/{mode?}', static fn (string $entity, string $recordId, ?string $mode = null) => $loadAuraAdminDemo($entity, $recordId, $mode))->where('entity', '(?!(?:opportunities|categories|eligibility|mission-templates|projects|missions|verification)(?:/|$))[^/]+')->where('mode', 'edit')->name('record');
});
