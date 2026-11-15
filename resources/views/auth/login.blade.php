@extends('layouts.app')
@section('body')
<header><a class="brand" href="{{ route('aura.home') }}">Aura<span class="premium">Oportunități</span></a></header>
<main class="wrap"><div class="auth"><div style="text-align:center;margin-bottom:25px"><h1>{{ ($authArea ?? 'user') === 'admin' ? 'Acces Super Admin' : 'Bine ai revenit' }}</h1><p>Oportunitățile tale te așteaptă.</p></div>
<form class="card" id="auth-form" method="POST" action="{{ ($authArea ?? 'user') === 'admin' ? route('aura.admin.login.store') : route('aura.login.store') }}">
@csrf
<span class="demo">AUTENTIFICARE REALĂ</span>
<label>Email</label><input type="email" name="email" required autocomplete="email" value="{{ old('email') }}">
<label>Parolă</label><input type="password" name="password" minlength="8" required autocomplete="current-password">
<label><input type="checkbox" name="remember" value="1"> Ține-mă minte</label>
<button class="btn" style="margin-top:22px">Intră în cont</button>
<div class="links"><a href="{{ route('aura.register') }}">Creează un cont</a><a href="{{ ($authArea ?? 'user') === 'admin' ? route('aura.admin.password.request') : route('aura.forgot-password') }}">Ai uitat parola?</a></div>
</form></div></main>
@endsection
