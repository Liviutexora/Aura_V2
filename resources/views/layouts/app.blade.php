<!doctype html>
<html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#100d25"><title>@yield('title', 'Aura — Oportunități, pas cu pas')</title>@if($auraUseVite ?? false)
@vite(['resources/css/app.css', 'resources/js/app.js'])
@else
<link rel="stylesheet" href="{{ asset('assets/aura/app.css') }}">
@endif</head><body><div id="app">@yield('body')</div><div id="toast" role="status"></div><dialog id="modal"></dialog><script>window.AURA_INITIAL_ROUTE = @json($demoRoute ?? 'dashboard');window.AURA_ASSET_BASE = @json(asset('assets/aura'));</script>@unless($auraUseVite ?? false)
<script>if (window.location.hash === '#/login') window.location.replace('{{ route('aura.login') }}');</script>@if(! in_array($demoRoute ?? '', ['login', 'forgot-password', 'reset-password', 'verify-email'], true))<script src="{{ asset('assets/aura/premium-icons.js') }}"></script><script src="{{ asset('assets/aura/app.js') }}"></script><script>(function(){function realLoginLinks(){document.querySelectorAll('a[href="#/login"]').forEach(function(link){link.setAttribute('href','{{ route('aura.login') }}');});}realLoginLinks();window.addEventListener('hashchange',function(){if(window.location.hash==='#/login'){window.location.replace('{{ route('aura.login') }}');}});})();</script>@endif
@endunless</body></html>
