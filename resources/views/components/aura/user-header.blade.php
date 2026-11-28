@props(['notifications' => 'demo'])
<header {{ $attributes->merge(['class' => 'aura-user-header']) }}>
    <div class="aura-brand-status">
        <a class="brand" href="{{ route('aura.dashboard') }}"><span class="crown"><svg class="aura-svg aura-svg-crown" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#crown"></use></svg></span>Aura</a>
        @if($headerIndicator['linkable'])
            <a href="{{ route('aura.subscriptions') }}" class="premium aura-header-indicator aura-header-indicator--{{ $headerIndicator['tone'] }}" data-pwa-header-status title="{{ $headerIndicator['label'] }}">{{ $headerIndicator['label'] }}</a>
        @else
            <span class="premium aura-header-indicator aura-header-indicator--{{ $headerIndicator['tone'] }}" data-pwa-header-status>{{ $headerIndicator['label'] }}</span>
        @endif
        @if(config('pwa.enabled') && $headerIndicator['installable'])
            <button class="btn premium pwa-header-install" type="button" data-pwa-header-install hidden>Install App</button>
        @endif
    </div>
    <nav aria-label="Navigare principală">
        <a href="{{ route('aura.dashboard') }}" class="{{ request()->routeIs('aura.dashboard', 'aura.dashboard.*') ? 'active' : '' }}"><svg class="aura-svg aura-svg-sparkle" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#sparkle"></use></svg> Aura</a>
        <a href="{{ route('aura.projects') }}" class="{{ request()->routeIs('aura.projects', 'aura.projects.*') ? 'active' : '' }}"><svg class="aura-svg aura-svg-checklist" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#checklist"></use></svg> Proiecte</a>
        <a href="{{ route('aura.missions') }}" class="{{ request()->routeIs('aura.missions', 'aura.missions.*') ? 'active' : '' }}"><svg class="aura-svg aura-svg-folder" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#folder"></use></svg> Misiuni</a>
        <a href="{{ route('aura.wallet') }}" class="{{ request()->routeIs('aura.wallet', 'aura.wallet.*') ? 'active' : '' }}"><svg class="aura-svg aura-svg-wallet" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#wallet"></use></svg> Finanțe</a>
    </nav>
    <div class="aura-user-actions">
        <button type="button" aria-label="Notificări" @if($notifications === 'missions') onclick="document.getElementById('mission-alerts').showModal()" @else onclick="info('Notificările tale','Misiunea Bonus bancar are termen pe 30 noiembrie. Ai o insignă nouă: Primul pas. Notificări demonstrative.')" @endif><svg class="aura-svg aura-svg-bell" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#bell"></use></svg></button>
        <a href="{{ route('aura.settings') }}" class="avatar" aria-label="Setări cont">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</a>
    </div>
</header>
<nav class="mobile-nav aura-user-mobile-nav" aria-label="Navigare mobilă">
    <a href="{{ route('aura.dashboard') }}" class="{{ request()->routeIs('aura.dashboard', 'aura.dashboard.*') ? 'active' : '' }}"><span><svg class="aura-svg aura-svg-sparkle" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#sparkle"></use></svg></span>Dashboard</a>
    <a href="{{ route('aura.opportunities') }}" class="{{ request()->routeIs('aura.opportunities', 'aura.opportunities.*') ? 'active' : '' }}"><span><svg class="aura-svg aura-svg-target" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#target"></use></svg></span>Oportunități</a>
    <a href="{{ route('aura.missions') }}" class="{{ request()->routeIs('aura.missions', 'aura.missions.*') ? 'active' : '' }}"><span><svg class="aura-svg aura-svg-checklist" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#checklist"></use></svg></span>Misiunile mele</a>
    <a href="{{ route('aura.wallet') }}" class="{{ request()->routeIs('aura.wallet', 'aura.wallet.*') ? 'active' : '' }}"><span><svg class="aura-svg aura-svg-wallet" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#wallet"></use></svg></span>Wallet</a>
    <a href="{{ route('aura.settings') }}" class="{{ request()->routeIs('aura.settings', 'aura.settings.*') ? 'active' : '' }}"><span><svg class="aura-svg aura-svg-user" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#user"></use></svg></span>Profil</a>
</nav>
