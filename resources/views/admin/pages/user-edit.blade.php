@extends('admin.layouts.app')

@section('content')
<div class="admin-heading">
    <div>
        <h1>Editează utilizator</h1>
        <p>Date reale ale contului și abonamentului.</p>
    </div>
    <a class="btn admin-btn secondary" href="{{ route('aura.admin.users') }}">Înapoi la utilizatori</a>
</div>

<section class="card admin-card">
    <form class="admin-form" onsubmit="event.preventDefault()">
        <div>
            <label for="user-name">Nume</label>
            <input id="user-name" name="name" type="text" value="{{ $user->name }}" readonly>
        </div>
        <div>
            <label for="user-email">Email</label>
            <input id="user-email" name="email" type="email" value="{{ $user->email }}" readonly>
        </div>
        <div>
            <label for="user-plan">Abonament</label>
            <input id="user-plan" name="plan" type="text" value="{{ $membership?->terms['name'] ?? 'Niciun abonament' }}" readonly>
        </div>
        <div>
            <label for="user-status">Stare</label>
            <input id="user-status" name="status" type="text" value="{{ $statusLabel }}" readonly>
        </div>
        <div class="full form-actions">
            @if($membership)
                <a class="btn admin-btn secondary" href="{{ route('aura.admin.subscriptions', ['q' => $user->email, 'status' => 'active']) }}">Verifică abonamentul</a>
            @else
                <button class="btn admin-btn" type="button" data-open-subscription-form @disabled($activePlans->isEmpty())>Alocă abonament</button>
            @endif
            <button class="btn admin-btn" type="submit" disabled>Salvează modificările</button>
            <a class="btn admin-btn secondary" href="{{ route('aura.admin.users') }}">Renunță</a>
        </div>
        <p class="helper full">Editarea datelor contului va fi activată într-o etapă ulterioară.</p>
    </form>
</section>

@unless($membership)
    <template id="subscription-form-template">
        <h2 id="subscription-dialog-title">Alocă abonament</h2>
        <p>{{ $user->name }} · {{ $user->email }}</p>
        <form class="admin-form" method="POST" action="{{ route('aura.admin.subscriptions.grant', $user) }}">
            @csrf
            <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="timing" value="period_end">
            <div>
                <label for="membership-plan">Abonament</label>
                <select id="membership-plan" name="plan_id" required>
                    <option value="">{{ $activePlans->isEmpty() ? 'Nu există planuri active' : 'Selectează planul' }}</option>
                    @foreach($activePlans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="membership-days">Durata în zile</label>
                <input id="membership-days" name="duration_days" type="number" min="1" max="3650" required>
            </div>
            <div class="full">
                <label for="membership-reason">Motivul alocării</label>
                <textarea id="membership-reason" name="reason" maxlength="1000" required></textarea>
            </div>
            <p class="helper full" data-form-error role="alert" hidden></p>
            <div class="form-actions full">
                <button class="btn admin-btn" @disabled($activePlans->isEmpty())>Confirmă alocarea</button>
                <button class="btn admin-btn secondary" type="button" data-close>Renunță</button>
            </div>
        </form>
    </template>
    <template id="saved-membership-template">
        <h2 id="subscription-dialog-title">Abonamentul a fost alocat</h2>
        <p>Informația utilizatorului va fi actualizată la închiderea ferestrei.</p>
        <div class="form-actions">
            <a class="btn admin-btn secondary" href="{{ route('aura.admin.subscriptions', ['q' => $user->email, 'status' => 'active']) }}">Verifică în abonamente</a>
            <button type="button" class="btn admin-btn" data-close>Închide</button>
        </div>
    </template>
    <script src="{{ asset('assets/admin/memberships.js') }}" defer></script>
@endunless

<section class="card admin-card" style="margin-top:24px">
    <h2>Resetează parola</h2>
    <p>Setează o parolă nouă pentru acest utilizator.</p>

    @if(session('password_reset_status'))
        <div class="admin-notice" role="status"><x-aura-admin::icon name="shield" />{{ session('password_reset_status') }}</div>
    @endif

    @if($errors->hasAny(['current_password', 'password']))
        <div class="admin-notice error-state" role="alert">
            <x-aura-admin::icon name="shield" />
            <div>
                @foreach($errors->get('current_password') as $message)<p>{{ $message }}</p>@endforeach
                @foreach($errors->get('password') as $message)<p>{{ $message }}</p>@endforeach
            </div>
        </div>
    @endif

    <form id="password-reset-form" class="admin-form" method="POST" action="{{ route('aura.admin.users.password.reset', $user) }}" onsubmit="preparePasswordReset(event)">
        @csrf
        <div>
            <label for="new-password">Parolă nouă</label>
            <input id="new-password" name="password" type="password" autocomplete="new-password">
        </div>
        <div>
            <label for="confirm-new-password">Confirmă parola nouă</label>
            <input id="confirm-new-password" name="password_confirmation" type="password" autocomplete="new-password">
        </div>
        <div class="full form-actions">
            <button class="btn admin-btn" type="submit">Resetează parola</button>
        </div>
        <p id="password-reset-error" class="helper full" role="alert" style="color:#ffc1d1"></p>
        <p class="helper full">Operațiune disponibilă exclusiv SUPER ADMIN. Confirmarea identității este obligatorie.</p>
    </form>
</section>
@endsection
