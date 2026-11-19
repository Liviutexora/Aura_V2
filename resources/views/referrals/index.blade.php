@extends('layouts.project')
@section('project-content')
<div class="heading">
    <div>
        <div class="eyebrow">SPAȚIUL TĂU DE OPORTUNITĂȚI</div>
        <h1>Centrul meu de recomandări</h1>
        <p>Invită persoane în AURA și urmărește înscrierile prin linkul tău.</p>
    </div>
</div>

<div class="mini-stats">
    <div class="card"><small>Recomandări directe</small><b>{{ $referrals->total() }}</b></div>
    <div class="card"><small>Conturi active</small><b>{{ $activeCount }}</b></div>
    <div class="card"><small>Conturi inactive</small><b>{{ $referrals->total() - $activeCount }}</b></div>
</div>

<div class="split">
    <article class="card">
        <span class="tile pink" aria-hidden="true">↗</span>
        <h2 id="referral-link-label">Linkul tău personal de recomandare</h2>
        @if($referralUrl)
            <input id="referral-link" type="url" readonly aria-labelledby="referral-link-label" value="{{ $referralUrl }}">
            <div class="actions">
                <button id="referral-copy" class="btn secondary" type="button">Copiază linkul ⧉</button>
                <button id="referral-share" type="button">Distribuie ↗</button>
            </div>
        @else
            <p>Linkul va fi disponibil după actualizarea codului de recomandare.</p>
        @endif
    </article>

    <section class="card">
        <h2>Persoane recomandate direct</h2>
        @forelse($referrals as $referral)
            <div class="row">
                <span class="tile" aria-hidden="true"><svg class="aura-svg aura-svg-user"><use href="{{ asset('assets/aura/icons.svg') }}#user"></use></svg></span>
                <div class="body">
                    <b>{{ $referral->referralDisplayName() }}</b>
                    <small>Înregistrare · {{ $referral->created_at?->format('d.m.Y') ?? '—' }}</small>
                </div>
                <span @class(['status', 'pending' => ! $referral->isActive()])>{{ $referral->isActive() ? 'Activ' : 'Inactiv' }}</span>
            </div>
        @empty
            <p>Nu ai încă recomandări directe.</p>
        @endforelse
        @include('projects.pagination', ['items' => $referrals])
    </section>
</div>
@endsection
