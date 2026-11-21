@extends('layouts.app')
@section('body')
<header><a class="brand" href="{{ route('aura.home') }}">Aura<span class="premium">Oportunități</span></a></header>
<main class="wrap"><div class="auth"><div style="text-align:center;margin-bottom:25px"><h1>Ai uitat parola?</h1><p>Introdu adresa pentru instrucțiuni.</p></div><form class="card" method="POST" action="{{ ($authArea ?? 'user') === 'admin' ? route('aura.admin.password.email') : route('aura.password.email') }}">@csrf
@include('auth.feedback')
<label>Email</label><input type="email" name="email" required autocomplete="email"><button class="btn" style="margin-top:22px">Trimite instrucțiunile</button><div class="links"><a href="{{ ($authArea ?? 'user') === 'admin' ? route('aura.admin.login') : route('aura.login') }}">Înapoi la autentificare</a></div></form></div></main>
@endsection
