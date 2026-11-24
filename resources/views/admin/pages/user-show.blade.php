@extends('admin.layouts.app')

@section('content')
<div class="admin-heading">
    <div>
        <h1>Profil utilizator</h1>
        <p>Date reale disponibile pentru acest cont.</p>
    </div>
    <a class="btn admin-btn secondary" href="{{ route('aura.admin.users') }}">Înapoi la utilizatori</a>
</div>

<section class="card admin-card">
    <div style="display:flex;align-items:center;gap:18px">
        <span class="admin-avatar" style="width:65px;height:65px;font-size:28px"><x-aura-admin::icon name="user" /></span>
        <div>
            <h2 style="margin:0 0 5px">{{ $user->name }}</h2>
            <p>{{ $user->email }} · <x-aura-admin::status-badge :status="$statusLabel" /></p>
        </div>
    </div>
    <div class="form-actions">
        <a class="btn admin-btn secondary" href="{{ route('aura.admin.subscriptions', ['manage_user' => $user->id]) }}">Schimbă planul</a>
        <button class="btn admin-btn secondary" type="button" disabled>{{ $user->status === 'suspended' ? 'Activează' : 'Suspendă' }}</button>
    </div>
</section>

<div class="tabs" style="margin-top:20px">
    <a @class(['active' => $subscriptionEvents === null]) href="{{ route('aura.admin.users.show', $user) }}">Profil</a>
    <a @class(['active' => $subscriptionEvents !== null]) href="{{ route('aura.admin.users.show', ['user' => $user, 'tab' => 'subscription']) }}">Abonament</a>
    <button type="button" disabled>Misiuni</button>
    <button type="button" disabled>XP</button>
    <button type="button" disabled>Recomandări</button>
    <button type="button" disabled>Wallet</button>
    <button type="button" disabled>Aplicații</button>
    <button type="button" disabled>Activitate</button>
    <button type="button" disabled>Note admin</button>
</div>

@if(session('membership_status'))
    <x-aura-admin::alert :message="session('membership_status')" />
@endif
<section class="card admin-card">
    @if($subscriptionEvents !== null)
        <p>Plan curent · {{ $membership?->terms['name'] ?? 'Fără abonament activ' }}
            @if($membership) · {{ $membership->sourceLabel() }} · {{ $membership->starts_at->format('d.m.Y H:i') }} – {{ $membership->ends_at->format('d.m.Y H:i') }} @endif
        </p>
        <x-aura-admin::subscription-history :events="$subscriptionEvents" />
    @else
    <div class="detail-grid">
        <div class="detail-item"><small>Email</small><b>{{ $user->email }}</b></div>
        <div class="detail-item"><small>Înregistrare</small><b>{{ $user->created_at?->format('d.m.Y H:i') ?? '—' }}</b></div>
        <div class="detail-item"><small>Abonament</small><b>{{ $membership?->terms['name'] ?? 'Fără abonament activ' }}</b></div>
        <div class="detail-item"><small>Stare</small><b><x-aura-admin::status-badge :status="$statusLabel" /></b></div>
        <div class="detail-item"><small>Câștiguri</small><b>—</b></div>
        <div class="detail-item"><small>XP</small><b>—</b></div>
        <div class="detail-item"><small>Rol</small><b>{{ $roleLabel ?: '—' }}</b></div>
        <div class="detail-item"><small>Email verificat</small><b>{{ $user->email_verified_at?->format('d.m.Y H:i') ?? '—' }}</b></div>
        <div class="detail-item"><small>Cod de recomandare</small><b>{{ $user->referral_code ?: '—' }}</b></div>
        <div class="detail-item"><small>Sponsor</small><b>{{ $user->sponsor?->name ?? '—' }}</b></div>
        <div class="detail-item"><small>Recomandări directe</small><b>{{ $user->sponsored_users_count }}</b></div>
        <div class="detail-item"><small>Telefon</small><b>{{ $user->profile?->phone ?: '—' }}</b></div>
        <div class="detail-item"><small>Limbă</small><b>{{ $user->profile?->locale ?: '—' }}</b></div>
        <div class="detail-item"><small>Fus orar</small><b>{{ $user->profile?->timezone ?: '—' }}</b></div>
    </div>
    @endif
</section>

<x-aura-admin::alert message="Secțiunile fără surse de date reale sunt afișate cu „—”." />
@endsection
