@extends('admin.layouts.app')
@section('content')
<div class="admin-heading">
    <div><h1>Coduri promoționale</h1><p>Caută după plan, numele beneficiarului sau email.</p></div>
    <button type="button" class="btn admin-btn" data-open-subscription-form><x-aura-admin::icon name="plus" /> Generează cupoane</button>
</div>
@if(session('promo_status'))
    <x-aura-admin::alert :message="session('promo_status')" />
@endif
@foreach($errors->all() as $error)
    <x-aura-admin::alert :message="$error" />
@endforeach
<nav class="tabs" aria-label="Inventar cupoane">
    @foreach(['disponibile' => 'Disponibile', 'arhiva' => 'Arhivă'] as $key => $label)
        <a @class(['active' => $tab === $key])
            @if($tab === $key) aria-current="page" @endif
            href="{{ route('aura.admin.promo-codes', ['tab' => $key, 'q' => $filters['q'] ?? null]) }}">{{ $label }}</a>
    @endforeach
</nav>
<x-aura-admin::card class="admin-card">
    <form class="toolbar" method="GET" action="{{ route('aura.admin.promo-codes') }}">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input name="q" type="search" maxlength="255" aria-label="Caută coduri după plan sau beneficiar" placeholder="Caută după plan sau beneficiar…" value="{{ $filters['q'] ?? '' }}">
        <select name="status" aria-label="Filtrează starea" onchange="this.form.requestSubmit()">
            <option value="">Toate stările</option>
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <button class="icon-button" aria-label="Caută"><x-aura-admin::icon name="search" /></button>
    </form>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th scope="col">Cod promoțional</th><th scope="col">Abonament</th><th scope="col">Durată acordată</th><th scope="col">Expiră la (UTC)</th><th scope="col">Stare</th><th scope="col">Beneficiar</th><th scope="col">Acțiuni</th></tr></thead>
        <tbody>
            @foreach($codes as $promo)
                @php($beneficiary = $promo->membership?->user ?? $promo->reservedUser)
                <tr>
                    <td>@if($promo->encrypted_code)<b>{{ $promo->encrypted_code }}</b>@else <small>Indisponibil (cod vechi)</small> @endif</td>
                    <td><b>{{ $promo->plan->name }}</b></td>
                    <td>{{ $promo->duration_days }} {{ $promo->duration_days === 1 ? 'zi' : 'zile' }}</td>
                    <td>{{ $promo->expires_at?->utc()->format('d.m.Y H:i') ?? 'Fără expirare' }}</td>
                    <td><span @class(['status', 'bad' => in_array($promo->status, ['expirat', 'revocat'], true)])>{{ ucfirst($promo->status) }}</span></td>
                    <td>@if($beneficiary){{ $beneficiary->name }}<br><small>{{ $beneficiary->email }}</small>@else — @endif</td>
                    <td>
                        <div class="form-actions">
                            <button type="button" class="btn admin-btn secondary" data-copy-promo="{{ $promo->encrypted_code }}" @disabled(! $promo->encrypted_code)>Copiază</button>
                            @if($tab === 'disponibile')
                                <form method="POST" action="{{ route('aura.admin.subscriptions.codes.revoke', $promo) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                    <button class="btn admin-btn secondary">Revocă</button>
                                </form>
                            @endif
                            @if($promo->redeemed_at === null && $promo->membership_id === null)
                                <form method="POST" action="{{ route('aura.admin.subscriptions.codes.destroy', $promo) }}" data-delete-promo>
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                    <input type="hidden" name="confirm_delete" value="0">
                                    <button class="btn admin-btn secondary">Șterge</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table></div>
    @if($codes->isEmpty())
        <x-aura-admin::empty-state title="Niciun cod promoțional" description="Nu există coduri pentru selecția curentă." />
    @endif
    <x-aura-admin::subscription-pagination :items="$codes" />
</x-aura-admin::card>

<template id="subscription-form-template">
    <h2 id="subscription-dialog-title">Generează cupoane</h2>
    <form class="admin-form" method="POST" action="{{ route('aura.admin.subscriptions.codes.store') }}" data-issue-code>
        @csrf
        <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <div><label for="promo-plan">Abonament</label>
            <select name="plan_id" id="promo-plan" required>
                <option value="">{{ $activePlans->isEmpty() ? 'Nu există planuri active' : 'Selectează planul' }}</option>
                @foreach($activePlans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach
            </select>
        </div>
        <div><label for="promo-days">Durata abonamentului acordat (zile)</label>
            <input type="number" id="promo-days" name="duration_days" min="1" max="3650" required>
        </div>
        <div><label for="promo-expiry">Valabil până la (data și ora UTC)</label>
            <input type="datetime-local" id="promo-expiry" name="expires_at" required>
        </div>
        <div><label for="promo-quantity">Număr cupoane</label>
            <input type="number" id="promo-quantity" name="quantity" min="1" max="100" value="1" required>
        </div>
        <div class="full"><label for="promo-reason">Motiv / descriere internă (opțional)</label>
            <textarea id="promo-reason" name="reason" maxlength="1000"></textarea>
        </div>
        <p class="helper full" data-form-error role="alert" hidden></p>
        <div class="form-actions full"><button class="btn admin-btn" @disabled($activePlans->isEmpty())>Generează cupoanele</button>
            <button class="btn admin-btn secondary" type="button" data-close>Renunță</button></div>
    </form>
</template>
<template id="issued-code-template">
    <h2 id="subscription-dialog-title">Cupoane generate</h2>
    <p>Codurile rămân disponibile în tabel și după închiderea ferestrei.</p>
    <label for="issued-promo-code">Coduri promoționale</label>
    <textarea id="issued-promo-code" rows="8" readonly autocomplete="off" spellcheck="false"></textarea>
    <p class="helper" data-copy-status role="status"></p>
    <div class="form-actions"><button type="button" class="btn admin-btn" data-copy-code>Copiază toate</button>
        <button type="button" class="btn admin-btn secondary" data-close>Închide</button></div>
</template>
<script src="{{ asset('assets/admin/memberships.js') }}" defer></script>
@endsection
