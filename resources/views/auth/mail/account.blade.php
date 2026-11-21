<!doctype html>
<html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#100d25;color:#ffffff;font-family:Arial,sans-serif">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="100%" style="max-width:520px;background:#201935;border-radius:16px;color:#ffffff" cellpadding="24" cellspacing="0"><tr><td>
<strong style="color:#ffd268;font-size:22px">AURA</strong>
@isset($code)
    <h1 style="font-size:22px">{{ $forLogin ? 'Confirmă încercarea de autentificare' : 'Verifică adresa de email' }}</h1>
    <p>{{ $forLogin ? 'Introdu dovada de mai jos în formularul de autentificare AURA. Este valabilă 10 minute.' : 'Introdu acest cod în pagina de verificare AURA. Codul este valabil 10 minute.' }}</p>
    <p style="font-size:{{ $forLogin ? '20px' : '32px' }};letter-spacing:2px;color:#ffd268"><strong>{{ $code }}</strong></p>
    <p>{{ $forLogin ? 'Dacă nu încerci să intri în cont, ignoră acest mesaj și păstrează dovada privată.' : 'Dacă nu ai solicitat înregistrarea sau verificarea acestei adrese, poți ignora mesajul.' }}</p>
@else
    <h1 style="font-size:22px">Resetarea parolei</h1>
    <p>Am primit o solicitare de resetare a parolei tale. Linkul poate fi folosit o singură dată, timp de 60 de minute.</p>
    <p><a style="display:inline-block;background:#ffd268;color:#201935;padding:12px 18px;border-radius:8px" href="{{ $resetUrl }}">Alege o parolă nouă</a></p>
    <p>Dacă nu ai solicitat resetarea, poți ignora acest mesaj.</p>
@endisset
</td></tr></table></td></tr></table></body></html>
