@if(config('pwa.enabled'))
<link rel="manifest" href="{{ route('aura.pwa.manifest') }}">
<link rel="icon" href="{{ asset('assets/aura/pwa/icon-192.png') }}" sizes="192x192">
<link rel="apple-touch-icon" href="{{ asset('assets/aura/pwa/apple-touch-icon.png') }}">
<meta name="theme-color" content="{{ config('pwa.theme_color') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ config('pwa.short_name') }}">
@endif
