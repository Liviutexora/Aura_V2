<!doctype html>
<html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#100d25"><title>Aura Admin — {{ $pageTitle ?? 'Administrare' }}</title>
@if(($auraAdminUseVite ?? false) && !($projectAdmin ?? false))
@vite(['resources/css/admin.css', 'resources/js/admin.js'])
@else
<link rel="stylesheet" href="{{ asset('assets/admin/shared.css') }}"><link rel="stylesheet" href="{{ asset('assets/admin/admin.css') }}">
@endif
</head><body><div id="admin-app">
@include('admin.components.topbar')
@include('admin.components.sidebar')
<main class="admin-main"><div class="crumb"><span>Aura Admin / {{ $pageTitle ?? 'Administrare' }}</span><span class="demo">{{ ($auraAdminDemo ?? false) ? 'DATE ȘI ACȚIUNI DEMO' : (($projectAdmin ?? false) ? 'DATE REALE' : 'DATE REALE · DOAR CITIRE') }}</span></div>
@if($projectAdmin ?? false)
@if(session('status'))<div class="admin-notice" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="admin-notice error-state" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@endif
@yield('content')
@include('admin.components.footer')
</main></div>
<dialog id="admin-dialog"></dialog><div id="admin-toast" role="status"></div>
<script>window.AURA_ADMIN_ASSET_BASE = @json(asset('assets/admin')); window.AURA_ADMIN_INITIAL_ROUTE = @json($demoPage ?? 'dashboard'); window.AURA_ADMIN_SERVER_RENDERED = @json($auraAdminServerRendered ?? false); window.AURA_ADMIN_DEMO = @json($auraAdminDemo ?? false); window.AURA_USER_DASHBOARD_URL = @json(route('aura.dashboard'));</script>
@php
$adminRealRouteMap = [
    'dashboard' => ['index' => route('aura.admin.dashboard')],
    'users' => ['index' => route('aura.admin.users'), 'show' => route('aura.admin.users.show', ['user' => '__AURA_RECORD__']), 'edit' => route('aura.admin.users.edit', ['user' => '__AURA_RECORD__'])],
    'opportunities' => ['index' => route('aura.admin.opportunities'), 'create' => route('aura.admin.opportunities.create'), 'show' => route('aura.admin.opportunities.show', ['record' => '__AURA_RECORD__']), 'edit' => route('aura.admin.opportunities.edit', ['record' => '__AURA_RECORD__'])],
    'categories' => ['index' => route('aura.admin.categories'), 'create' => route('aura.admin.categories.create'), 'show' => route('aura.admin.categories.show', ['record' => '__AURA_RECORD__']), 'edit' => route('aura.admin.categories.edit', ['record' => '__AURA_RECORD__'])],
    'eligibility' => ['index' => route('aura.admin.eligibility'), 'create' => route('aura.admin.eligibility.create'), 'show' => route('aura.admin.eligibility.show', ['record' => '__AURA_RECORD__']), 'edit' => route('aura.admin.eligibility.edit', ['record' => '__AURA_RECORD__'])],
    'mission-templates' => ['index' => route('aura.admin.mission-templates'), 'create' => route('aura.admin.mission-templates.create'), 'show' => route('aura.admin.mission-templates.show', ['record' => '__AURA_RECORD__']), 'edit' => route('aura.admin.mission-templates.edit', ['record' => '__AURA_RECORD__'])],
    'projects' => ['index' => route('aura.admin.projects'), 'create' => route('aura.admin.projects.create'), 'show' => route('aura.admin.projects.show', ['project' => '__AURA_RECORD__'])],
    'missions' => ['index' => route('aura.admin.missions'), 'create' => route('aura.admin.missions.create'), 'show' => route('aura.admin.missions.show', ['mission' => '__AURA_RECORD__'])],
    'verification' => ['index' => route('aura.admin.verification'), 'show' => route('aura.admin.verification.show', ['submission' => '__AURA_RECORD__'])],
];
$demoAdminRouteNames = [
    'subscriptions', 'levels', 'xprules', 'achievements', 'transactions', 'commissions', 'payouts',
    'referrals', 'referral-rules', 'conversions', 'links', 'events', 'notifications', 'announcements',
    'messageTemplates', 'plans', 'audit', 'analytics', 'financial-reports', 'activity', 'gamification',
    'progress', 'wallet', 'integrations', 'integration-config', 'delivery-log', 'roles', 'settings',
];
foreach ($demoAdminRouteNames as $demoAdminRouteName) {
    $adminRealRouteMap[$demoAdminRouteName] = [
        'index' => route('aura.admin.'.$demoAdminRouteName),
        'create' => route('aura.admin.record', ['entity' => $demoAdminRouteName, 'recordId' => 'create']),
        'show' => route('aura.admin.record', ['entity' => $demoAdminRouteName, 'recordId' => '__AURA_RECORD__']),
        'edit' => route('aura.admin.record', ['entity' => $demoAdminRouteName, 'recordId' => '__AURA_RECORD__', 'mode' => 'edit']),
    ];
}
@endphp
<script>window.AURA_ADMIN_REAL_ROUTES = {{ Js::from($adminRealRouteMap) }};</script>
<script>window.addEventListener('pageshow',function(event){if(localStorage.getItem('aura-logged-out')==='1'){window.location.replace('{{ route('aura.login') }}');return}if(event.persisted)window.location.reload();});</script>
@unless(($auraAdminUseVite ?? false) && !($projectAdmin ?? false))
@if($projectAdmin ?? false)<script src="{{ asset('assets/admin/projects.js') }}"></script>@else<script src="{{ asset('assets/admin/data.js') }}"></script><script src="{{ asset('assets/admin/admin.js') }}"></script>@endif<script>(function(){function addLogout(){var menu=document.querySelector('.profile-menu');if(!menu||menu.querySelector('[data-aura-admin-logout]'))return;var form=document.createElement('form');form.method='POST';form.action='{{ route('aura.logout') }}';form.setAttribute('data-aura-admin-logout','1');form.setAttribute('onsubmit',"localStorage.setItem('aura-logged-out','1');localStorage.removeItem('aura-admin-demo-v1')");form.innerHTML='<input type="hidden" name="_token" value="{{ csrf_token() }}"><button type="submit" style="width:100%;text-align:left">Deconectare</button>';menu.appendChild(form)}new MutationObserver(addLogout).observe(document.body,{childList:true,subtree:true});addLogout()})();</script>
@endunless
</body></html>
