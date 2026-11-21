@extends('layouts.app')
@section('body')
<header><a class="brand" href="{{ route('aura.home') }}">Aura<span class="premium">Oportunități</span></a></header>
<main class="wrap"><div class="auth"><div style="text-align:center;margin-bottom:25px"><h1>Alege o parolă nouă</h1><p>Setează o parolă nouă pentru cont.</p></div><form class="card" method="POST" action="{{ route($authArea === 'admin' ? 'aura.admin.password.update' : 'aura.password.update') }}">@csrf
@include('auth.feedback')
<input type="hidden" name="token" value="{{ $token }}"><label>Email</label><input type="email" name="email" required value="{{ $email }}" autocomplete="email"><label>Parolă</label><input type="password" name="password" minlength="8" required autocomplete="new-password"><label>Confirmă parola</label><input type="password" name="password_confirmation" minlength="8" required autocomplete="new-password"><button class="btn" style="margin-top:22px">Salvează parola</button></form></div></main>
@endsection
