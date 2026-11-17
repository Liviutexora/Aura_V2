@extends('admin.layouts.app')

@section('content')
<div class="admin-heading">
    <div>
        <h1>Editează utilizator</h1>
        <p>Date reale afișate în mod exclusiv vizual.</p>
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
            <input id="user-plan" name="plan" type="text" value="—" readonly>
        </div>
        <div>
            <label for="user-status">Stare</label>
            <input id="user-status" name="status" type="text" value="{{ $statusLabel }}" readonly>
        </div>
        <div class="full form-actions">
            <button class="btn admin-btn" type="submit" disabled>Salvează modificările</button>
            <a class="btn admin-btn secondary" href="{{ route('aura.admin.users') }}">Renunță</a>
        </div>
        <p class="helper full">Salvarea modificărilor va fi activată într-o etapă ulterioară.</p>
    </form>
</section>

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
