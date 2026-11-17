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
        <button class="btn admin-btn secondary" type="button" disabled>Schimbă planul</button>
        <button class="btn admin-btn secondary" type="button" disabled>{{ $user->status === 'suspended' ? 'Activează' : 'Suspendă' }}</button>
    </div>
</section>

<div class="tabs" style="margin-top:20px">
    <button class="active" type="button">Profil</button>
    <button type="button" disabled>Abonament</button>
    <button type="button" disabled>Misiuni</button>
    <button type="button" disabled>XP</button>
    <button type="button" disabled>Recomandări</button>
    <button type="button" disabled>Wallet</button>
    <button type="button" disabled>Aplicații</button>
    <button type="button" disabled>Activitate</button>
    <button type="button" disabled>Note admin</button>
</div>

<section class="card admin-card">
    <div class="detail-grid">
        <div class="detail-item"><small>Email</small><b>{{ $user->email }}</b></div>
        <div class="detail-item"><small>Înregistrare</small><b>{{ $user->created_at?->format('d.m.Y H:i') ?? '—' }}</b></div>
        <div class="detail-item"><small>Abonament</small><b>—</b></div>
        <div class="detail-item"><small>Stare</small><b><x-aura-admin::status-badge :status="$statusLabel" /></b></div>
        <div class="detail-item"><small>Câștiguri</small><b>—</b></div>
        <div class="detail-item"><small>XP</small><b>—</b></div>
        <div class="detail-item"><small>Rol</small><b>{{ $roleLabel ?: '—' }}</b></div>
        <div class="detail-item"><small>Email verificat</small><b>{{ $user->email_verified_at?->format('d.m.Y H:i') ?? '—' }}</b></div>
        <div class="detail-item"><small>Cod de recomandare</small><b>{{ $user->referral_code ?: '—' }}</b></div>
        <div class="detail-item"><small>Sponsor</small><b>{{ $user->sponsor?->name ?? '—' }}</b></div>
        <div class="detail-item"><small>Telefon</small><b>{{ $user->profile?->phone ?: '—' }}</b></div>
        <div class="detail-item"><small>Limbă</small><b>{{ $user->profile?->locale ?: '—' }}</b></div>
        <div class="detail-item"><small>Fus orar</small><b>{{ $user->profile?->timezone ?: '—' }}</b></div>
    </div>
</section>

<x-aura-admin::alert message="Secțiunile fără surse de date reale sunt afișate cu „—”. Acțiunile de modificare rămân inactive." />
@endsection
