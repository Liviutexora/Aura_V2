@extends('layouts.app')
@section('body')
<x-aura.user-header /><main class="wrap"><nav class="subnav" aria-label="Navigare utilizator"><a href="{{ route('aura.dashboard') }}" class="">Dashboard</a><a href="{{ route('aura.opportunities') }}" class="">Oportunități</a><a href="{{ route('aura.missions') }}" class="">Misiunile mele</a><a href="{{ route('aura.projects') }}" class="">Proiectele mele</a><a href="{{ route('aura.achievements') }}" class="">Realizări</a><a href="{{ route('aura.wallet') }}" class="">Wallet</a><a href="{{ route('aura.referrals') }}" class="">Recomandări</a><a href="{{ route('aura.integrations') }}" class="">Aplicațiile mele</a></nav><div class="heading"><div><div class="eyebrow">SPAȚIUL TĂU DE OPORTUNITĂȚI</div><h1>{{ $onboarding ? 'Alege abonamentul' : 'Abonamentul tău' }}</h1><p>Mai multă claritate, la fiecare pas.</p></div><span class="demo">ABONAMENTE</span></div>@if($onboarding)
@include('subscriptions.onboarding')
@endif
@unless($onboarding)
<div class="card" style="margin-bottom:24px">
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
    @if(! $onboarding && ! $currentMembership && $trialPlan)
        <form method="POST" action="{{ route('aura.subscriptions.trial') }}" data-activation>
            @csrf
            <input type="hidden" name="plan_id" value="{{ $trialPlan->id }}">
            <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <button class="btn secondary">Testează gratuit</button>
            <p role="alert" hidden></p>
        </form>
    @endif
</div>
@endunless
@include('subscriptions.catalog', ['publicCatalog' => false])
@include('subscriptions.activation-dialog')
<div class="card" style="margin-top:24px"><h2>Facturi</h2><p>Facturile vor fi disponibile ulterior, după integrarea Stripe. Activările prin cupon și trial sunt gratuite.</p></div>
<footer class="footer"><span><svg class="aura-svg aura-svg-crown" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#crown"></use></svg> Aura · Descoperă oportunități. Câștigă mai mult.</span><span>Date demonstrative · Câștigurile sunt condiționate și nu sunt garantate.</span></footer></main>
@endsection
