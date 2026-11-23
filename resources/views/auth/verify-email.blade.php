@extends('layouts.app')
@section('body')
<header><a class="brand" href="{{ route('aura.home') }}"><span class="crown"><svg class="aura-svg aura-svg-crown" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#crown"></use></svg></span>Aura<span class="premium">Oportunități</span></a><nav><a href="{{ route('aura.home') }}" class="">Descoperă Aura</a><a href="{{ route('aura.opportunities') }}" class="">Oportunități</a><a href="{{ route('aura.pricing') }}" class="">Abonamente</a></nav><div><a class="btn secondary" href="{{ route('aura.login') }}">Intră în cont</a></div></header><main class="wrap"><div class="auth"><div style="text-align:center;margin-bottom:25px"><div class="crown"><svg class="aura-svg aura-svg-crown" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#crown"></use></svg></div><h1>Verifică adresa de email</h1></div>
<section class="card">
    @include('auth.feedback')
    @if ($user->hasVerifiedEmail())
        <a class="btn" href="{{ route($user->isSuperAdmin() ? 'aura.admin.index' : 'aura.dashboard') }}">Continuă în cont</a>
    @else
        <p>Verifică adresa <strong>{{ $user->email }}</strong> pentru a accesa contul.</p>
        <form method="POST" action="{{ route('aura.verify-email.check') }}">
            @csrf
            <label for="code">Cod de verificare (6 cifre)</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required>
            <p id="code-expiry" data-seconds="{{ $expiresIn }}">Codul este valabil 10 minute de la emitere.</p>
            <button class="btn">Verifică emailul</button>
        </form>
        <form method="POST" action="{{ route('aura.verify-email.resend') }}" style="margin-top:20px">
            @csrf
            <button class="btn secondary" id="resend-code" data-seconds="{{ $resendIn }}">Retrimite codul</button>
        </form>
    @endif
    <form method="POST" action="{{ route('aura.logout') }}" class="links">@csrf<button class="btn secondary">Deconectare</button></form>
</section>
</div><footer class="footer"><span><svg class="aura-svg aura-svg-crown" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#crown"></use></svg> Aura · Descoperă oportunități. Câștigă mai mult.</span></footer></main>
<script>
    const resend = document.getElementById('resend-code');
    const expiry = document.getElementById('code-expiry');
    if (resend && expiry) {
        const started = Date.now();
        function updateTimers() {
            const elapsed = Math.floor((Date.now() - started) / 1000);
            const wait = Math.max(0, Number(resend.dataset.seconds) - elapsed);
            const left = Math.max(0, Number(expiry.dataset.seconds) - elapsed);
            resend.disabled = wait > 0;
            resend.textContent = wait ? 'Retrimite codul peste ' + wait + ' s' : 'Retrimite codul';
            expiry.textContent = left
                ? 'Codul expiră în ' + Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0') + '.'
                : 'Solicită un cod nou pentru a continua.';
        }
        updateTimers();
        window.setInterval(updateTimers, 1000);
    }
</script>
@endsection
