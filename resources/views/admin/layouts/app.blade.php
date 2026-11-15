<!doctype html>
<html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#100d25"><title>Aura Admin — {{ $pageTitle ?? 'Administrare' }}</title>
@if($auraAdminUseVite ?? false)
@vite(['resources/css/admin.css', 'resources/js/admin.js'])
@else
<link rel="stylesheet" href="{{ asset('assets/admin/shared.css') }}"><link rel="stylesheet" href="{{ asset('assets/admin/admin.css') }}">
@endif
</head><body><div id="admin-app">
@include('admin.components.topbar')
@include('admin.components.sidebar')
<main class="admin-main"><div class="crumb"><span>Aura Admin / {{ $pageTitle ?? 'Administrare' }}</span><span class="demo">DATE ȘI ACȚIUNI DEMO</span></div>
@yield('content')
@include('admin.components.footer')
</main></div>
<dialog id="admin-dialog"></dialog><div id="admin-toast" role="status"></div>
<script>window.AURA_ADMIN_ASSET_BASE = @json(asset('assets/admin')); window.AURA_ADMIN_INITIAL_ROUTE = @json($demoPage ?? 'dashboard');</script>
@unless($auraAdminUseVite ?? false)
<script src="{{ asset('assets/admin/data.js') }}"></script><script src="{{ asset('assets/admin/admin.js') }}"></script>
@endunless
</body></html>
