@extends('layouts.app')
@section('body')
<header><a class="brand" href="{{ route('aura.dashboard') }}"><span class="crown"><svg class="aura-svg aura-svg-crown" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#crown"></use></svg></span>Aura<span class="premium">Premium</span></a><nav><a href="{{ route('aura.dashboard') }}" class=""><svg class="aura-svg aura-svg-sparkle" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#sparkle"></use></svg> Aura</a><a href="{{ route('aura.projects') }}" class=""><svg class="aura-svg aura-svg-checklist" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#checklist"></use></svg> Proiecte</a><a href="{{ route('aura.missions') }}" class=""><svg class="aura-svg aura-svg-folder" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#folder"></use></svg> Misiuni</a><a href="{{ route('aura.wallet') }}" class=""><svg class="aura-svg aura-svg-wallet" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#wallet"></use></svg> Finanțe</a></nav><div><button aria-label="Notificări" onclick="info('Notificările tale','Misiunea Bonus bancar are termen pe 30 noiembrie. Ai o insignă nouă: Primul pas. Notificări demonstrative.')"><svg class="aura-svg aura-svg-bell" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#bell"></use></svg></button><a href="{{ route('aura.settings') }}" class="avatar">L</a></div></header><main class="wrap"><nav class="subnav" aria-label="Navigare utilizator"><a href="{{ route('aura.dashboard') }}" class="">Dashboard</a><a href="{{ route('aura.opportunities') }}" class="">Oportunități</a><a href="{{ route('aura.missions') }}" class="">Misiunile mele</a><a href="{{ route('aura.projects') }}" class="">Proiectele mele</a><a href="{{ route('aura.achievements') }}" class="">Realizări</a><a href="{{ route('aura.wallet') }}" class="">Wallet</a><a href="{{ route('aura.referrals') }}" class="">Recomandări</a><a href="{{ route('aura.integrations') }}" class="">Aplicațiile mele</a><a href="{{ route('aura.subscriptions') }}" class="active">Abonament</a></nav><div class="heading"><div><div class="eyebrow">SPAȚIUL TĂU DE OPORTUNITĂȚI</div><h1>Abonamentul tău</h1><p>Mai multă claritate, la fiecare pas.</p></div><span class="demo">ABONAMENTE</span></div><div class="card" style="margin-bottom:24px">
    <div class="section-head">
        <div>
            <h2>{{ $currentMembership?->terms['name'] ?? 'Niciun abonament activ' }}</h2>
            <p>
                @if($currentMembership)
                    Status: Activ · Activat la {{ $currentMembership->starts_at->format('d.m.Y H:i') }} · Expiră la {{ $currentMembership->ends_at->format('d.m.Y H:i') }} · {{ $remainingDays }} {{ $remainingDays === 1 ? 'zi rămasă' : 'zile rămase' }}.
                @elseif($trialPlan)
                    Trial disponibil: {{ $trialPlan->trial_days }} {{ $trialPlan->trial_days === 1 ? 'zi' : 'zile' }} pentru planul {{ $trialPlan->name }}. Poate fi utilizat o singură dată.
                @else
                    Nu ai un abonament activ. Trialul nu este disponibil pentru acest cont.
                @endif
                @if($scheduledMembership)
                    <br>Plan programat: {{ $scheduledMembership->terms['name'] }} · activare la {{ $scheduledMembership->starts_at->format('d.m.Y H:i') }}.
                @endif
            </p>
        </div>
        <span class="status {{ $currentMembership ? '' : 'pending' }}">{{ $currentMembership ? 'Activ' : 'Inactiv' }}</span>
    </div>
    @if(! $currentMembership && $trialPlan)
        <form method="POST" action="{{ route('aura.subscriptions.trial') }}" data-activation>
            @csrf
            <input type="hidden" name="plan_id" value="{{ $trialPlan->id }}">
            <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <button class="btn secondary">Testează gratuit</button>
            <p role="alert" hidden></p>
        </form>
    @endif
</div>
@include('subscriptions.catalog', ['publicCatalog' => false])
@include('subscriptions.activation-dialog')
<div class="card" style="margin-top:24px"><h2>Istoric facturare</h2><p>Activările prin cupon și trial sunt gratuite.</p></div>
<footer class="footer"><span><svg class="aura-svg aura-svg-crown" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#crown"></use></svg> Aura · Descoperă oportunități. Câștigă mai mult.</span><span>Date demonstrative · Câștigurile sunt condiționate și nu sunt garantate.</span></footer></main><nav class="mobile-nav"><a href="{{ route('aura.dashboard') }}" class=""><span><svg class="aura-svg aura-svg-sparkle" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#sparkle"></use></svg></span>Dashboard</a><a href="{{ route('aura.opportunities') }}" class=""><span><svg class="aura-svg aura-svg-target" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#target"></use></svg></span>Oportunități</a><a href="{{ route('aura.missions') }}" class=""><span><svg class="aura-svg aura-svg-checklist" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#checklist"></use></svg></span>Misiunile mele</a><a href="{{ route('aura.wallet') }}" class=""><span><svg class="aura-svg aura-svg-wallet" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#wallet"></use></svg></span>Wallet</a></nav>
@endsection
