<!doctype html>
<html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#100d25"><title>@yield('title', 'Aura — Oportunități, pas cu pas')</title><x-aura.pwa-head />@if($auraUseVite ?? false)
@vite(['resources/css/app.css', 'resources/js/app.js'])
@else
<link rel="stylesheet" href="{{ asset('assets/aura/app.css') }}">
@endif</head><body><div id="app">@yield('body')</div><div id="toast" role="status"></div><dialog id="modal"></dialog>@php
$auraPwaConfig = [
    'enabled' => (bool) config('pwa.enabled'),
    'workerEnabled' => (bool) config('pwa.worker_enabled'),
    'workerRetirement' => (bool) config('pwa.worker_retirement'),
    'workerUrl' => route('aura.pwa.worker'),
    'manualUrl' => route('aura.settings', ['install' => 'manual']),
];
@endphp
<script>window.AURA_ASSET_BASE = @json(asset('assets/aura'));window.AURA_PWA = @json($auraPwaConfig);</script>@if(config('pwa.enabled') || config('pwa.worker_retirement'))<script src="{{ asset('assets/aura/pwa.js') }}" defer></script>@endif @unless($auraUseVite ?? false)
@if(! in_array($demoRoute ?? '', ['login', 'register', 'forgot-password', 'reset-password', 'verify-email'], true) && ! ($publicOnly ?? false))
<script src="{{ asset('assets/aura/premium-icons.js') }}"></script>
@if(request()->routeIs('aura.referrals'))
<script src="{{ asset('assets/aura/referrals.js') }}" defer></script>
@elseif(request()->routeIs('aura.dashboard','aura.projects*','aura.missions*','aura.opportunities*','aura.home'))
<script src="{{ asset('assets/aura/project-ui.js') }}"></script>
@else
<script src="{{ asset('assets/aura/app.js') }}?v={{ filemtime(public_path('assets/aura/app.js')) }}"></script>
@endif
@endif
@auth
<script>window.addEventListener('pageshow',function(event){if(localStorage.getItem('aura-logged-out')==='1'){window.location.replace('{{ route('aura.login') }}');return}if(event.persisted)window.location.reload();});</script>
<style>.aura-avatar-menu{position:relative;display:inline-block}.aura-avatar-menu .aura-account-dropdown{display:none;position:absolute;right:0;top:calc(100% + 10px);z-index:50;min-width:190px;padding:8px;border:1px solid rgba(255,255,255,.14);border-radius:14px;background:#17132f;box-shadow:0 14px 35px rgba(0,0,0,.35)}.aura-avatar-menu.open .aura-account-dropdown{display:grid;gap:4px}.aura-account-dropdown .aura-account-demo{display:flex;justify-content:space-between;gap:12px;padding:9px 10px;color:#c4b5d8;cursor:default}.aura-account-demo small{font-size:10px;color:#ffd166}.aura-account-dropdown a,.aura-account-dropdown button{border:0;border-radius:9px;background:transparent;color:inherit;padding:9px 10px;text-align:left;font:inherit;cursor:pointer;text-decoration:none}.aura-account-dropdown a:hover,.aura-account-dropdown button:hover,.aura-account-dropdown a:focus-visible,.aura-account-dropdown button:focus-visible{background:rgba(255,255,255,.1);outline:none}</style>
<script>(function(){var avatar=document.querySelector('a.avatar');if(!avatar||avatar.closest('.aura-avatar-menu'))return;var wrap=document.createElement('div');wrap.className='aura-avatar-menu';avatar.parentNode.insertBefore(wrap,avatar);wrap.appendChild(avatar);avatar.setAttribute('role','button');avatar.setAttribute('aria-haspopup','menu');avatar.setAttribute('aria-expanded','false');var menu=document.createElement('div');menu.className='aura-account-dropdown';menu.setAttribute('role','menu');menu.innerHTML='<a href="{{ route('aura.settings') }}" role="menuitem">Setări cont</a><a href="{{ route('aura.subscriptions') }}" role="menuitem">Abonamente</a><span class="aura-account-demo" role="menuitem" aria-disabled="true">Securitate <small>DEMO</small></span><span class="aura-account-demo" role="menuitem" aria-disabled="true">Preferințe <small>DEMO</small></span><form method="POST" action="{{ route('aura.logout') }}" onsubmit="localStorage.setItem(\'aura-logged-out\',\'1\');localStorage.removeItem(\'aura-demo-v1\')"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button type="submit" role="menuitem">Deconectare</button></form>';wrap.appendChild(menu);function close(){wrap.classList.remove('open');avatar.setAttribute('aria-expanded','false')}avatar.addEventListener('click',function(e){e.preventDefault();var open=wrap.classList.toggle('open');avatar.setAttribute('aria-expanded',open?'true':'false')});document.addEventListener('click',function(e){if(!wrap.contains(e.target))close()});document.addEventListener('keydown',function(e){if(e.key==='Escape')close()});})();</script>
@endauth
@endunless</body></html>
