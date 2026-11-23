@extends('layouts.app')
@section('body')
<header><a class="brand" href="{{ route('aura.dashboard') }}"><span class="crown"><svg class="aura-svg aura-svg-crown" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#crown"></use></svg></span>Aura<span class="premium">Premium</span></a><nav><a href="{{ route('aura.dashboard') }}" class=""><svg class="aura-svg aura-svg-sparkle" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#sparkle"></use></svg> Aura</a><a href="{{ route('aura.projects') }}" class=""><svg class="aura-svg aura-svg-checklist" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#checklist"></use></svg> Proiecte</a><a href="{{ route('aura.missions') }}" class=""><svg class="aura-svg aura-svg-folder" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#folder"></use></svg> Misiuni</a><a href="{{ route('aura.wallet') }}" class=""><svg class="aura-svg aura-svg-wallet" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#wallet"></use></svg> Finanțe</a></nav><div><button aria-label="Notificări" onclick="info('Notificările tale','Misiunea Bonus bancar are termen pe 30 noiembrie. Ai o insignă nouă: Primul pas. Notificări demonstrative.')"><svg class="aura-svg aura-svg-bell" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#bell"></use></svg></button><a href="{{ route('aura.settings') }}" class="avatar" aria-label="Setări cont">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</a></div></header><main class="wrap"><nav class="subnav" aria-label="Navigare utilizator"><a href="{{ route('aura.dashboard') }}" class="">Dashboard</a><a href="{{ route('aura.opportunities') }}" class="">Oportunități</a><a href="{{ route('aura.missions') }}" class="">Misiunile mele</a><a href="{{ route('aura.projects') }}" class="">Proiectele mele</a><a href="{{ route('aura.achievements') }}" class="">Realizări</a><a href="{{ route('aura.wallet') }}" class="">Wallet</a><a href="{{ route('aura.referrals') }}" class="">Recomandări</a><a href="{{ route('aura.integrations') }}" class="">Aplicațiile mele</a><a href="{{ route('aura.subscriptions') }}" class="">Abonament</a></nav><div class="detail"><div class="heading"><div><div class="eyebrow">SPAȚIUL TĂU DE OPORTUNITĂȚI</div><h1>Setările contului</h1><p>Datele tale și preferințele Aura.</p></div></div><form class="card settings-form" id="profile-form" method="POST" action="{{ route('aura.settings.profile.update') }}">@csrf<h2>Preferințe</h2>@if($errors->has('name') || $errors->has('email'))
<div class="notice" role="alert" style="color:#ff9fb1">
    @foreach(array_merge($errors->get('name'), $errors->get('email')) as $message)
        {{ $message }}<br>
    @endforeach
</div>
@elseif(session('profile_status'))
<div class="notice" role="status" style="color:#9cf3a8">{{ session('profile_status') }}</div>
@endif
<label for="profile-name">Nume complet</label>
<input id="profile-name" name="name" value="{{ old('name', auth()->user()->name) }}" required autocomplete="name">
@error('name', 'profileUpdate')
<small role="alert" style="display:block;color:#ff9fb1;margin-top:6px">{{ $message }}</small>
@enderror
<label for="profile-email">Email</label>
<input id="profile-email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" required autocomplete="email">
<small>Stare email: {{ auth()->user()->hasVerifiedEmail() ? 'Verificat' : 'Neverificat' }}</small>
@error('email', 'profileUpdate')
<small role="alert" style="display:block;color:#ff9fb1;margin-top:6px">{{ $message }}</small>
@enderror
<label>Limba interfeței</label><select><option>Română</option></select><label><input type="checkbox" checked style="width:auto"> Mementouri despre misiuni (preferință demo)</label><label><input type="checkbox" style="width:auto"> Noutăți și oportunități recomandate (preferință demo)</label><button class="btn" type="submit" data-profile-save style="margin-top:20px">Salvează modificările</button></form>
<template id="profile-email-confirmation-template" data-current-email="{{ auth()->user()->email }}" data-reopen="{{ $errors->profileUpdate->has('current_password') ? '1' : '0' }}">
<button class="dialog-close" type="button" data-profile-email-cancel aria-label="Închide dialogul">✕</button>
<h2>Confirmă identitatea</h2>
<p>Pentru a modifica adresa de email, introdu parola curentă a contului tău.</p>
<label for="profile-email-current-password">Parola curentă</label>
<input id="profile-email-current-password" name="current_password" type="password" form="profile-form" required autocomplete="off">
@error('current_password', 'profileUpdate')
<small class="dialog-error" role="alert">{{ $message }}</small>
@enderror
<div class="dialog-actions">
<button class="btn" type="button" data-profile-email-confirm>Confirmă schimbarea</button>
<button class="btn secondary" type="button" data-profile-email-cancel>Anulează</button>
</div>
<p class="dialog-helper">Parola este verificată securizat de Laravel și nu este stocată în browser.</p>
</template>
<form class="card settings-form" id="password-form" method="POST" action="{{ route('aura.settings.password.update') }}" style="margin-top:24px">
@csrf
<h2>Schimbă parola</h2>
<label>Parola actuală</label>
<input type="password" name="current_password" required autocomplete="current-password">
@error('current_password')
<small role="alert" style="display:block;color:#ff9fb1;margin-top:6px">{{ $message }}</small>
@enderror
<label>Parola nouă</label>
<input type="password" name="password" minlength="8" required autocomplete="new-password">
@error('password')
<small role="alert" style="display:block;color:#ff9fb1;margin-top:6px">{{ $message }}</small>
@enderror
<label>Confirmă parola nouă</label>
<input type="password" name="password_confirmation" minlength="8" required autocomplete="new-password">
@error('password_confirmation')
<small role="alert" style="display:block;color:#ff9fb1;margin-top:6px">{{ $message }}</small>
@enderror
@if(session('password_status'))
<div role="status" style="color:#9cf3a8;font-size:12px;margin-bottom:14px">Parola a fost schimbată cu succes.</div>
@endif
<button class="btn" style="margin-top:20px">Schimbă parola</button>
</form></div><footer class="footer"><span><svg class="aura-svg aura-svg-crown" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#crown"></use></svg> Aura · Descoperă oportunități. Câștigă mai mult.</span><span>Date demonstrative · Câștigurile sunt condiționate și nu sunt garantate.</span></footer></main><nav class="mobile-nav"><a href="{{ route('aura.dashboard') }}" class=""><span><svg class="aura-svg aura-svg-sparkle" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#sparkle"></use></svg></span>Dashboard</a><a href="{{ route('aura.opportunities') }}" class=""><span><svg class="aura-svg aura-svg-target" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#target"></use></svg></span>Oportunități</a><a href="{{ route('aura.missions') }}" class=""><span><svg class="aura-svg aura-svg-checklist" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#checklist"></use></svg></span>Misiunile mele</a><a href="{{ route('aura.wallet') }}" class=""><span><svg class="aura-svg aura-svg-wallet" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#wallet"></use></svg></span>Wallet</a></nav>
<script src="{{ asset('assets/aura/settings-security.js') }}" defer></script>
@endsection
