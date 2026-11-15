<!doctype html>
<html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#100d25"><title>@yield('title', 'Aura — Oportunități, pas cu pas')</title>@if($auraUseVite ?? false)
@vite(['resources/css/app.css', 'resources/js/app.js'])
@else
<link rel="stylesheet" href="{{ asset('assets/aura/app.css') }}">
@endif</head><body><div id="app">@yield('body')</div><div id="toast" role="status"></div><dialog id="modal"></dialog><script>window.AURA_INITIAL_ROUTE = @json($demoRoute ?? 'dashboard');window.AURA_ASSET_BASE = @json(asset('assets/aura'));</script>@unless($auraUseVite ?? false)
<script src="{{ asset('assets/aura/premium-icons.js') }}"></script><script src="{{ asset('assets/aura/app.js') }}"></script>
@endunless</body></html>