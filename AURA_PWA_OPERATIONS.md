# AURA PWA V1 — retragerea Service Worker

Pentru activare normală, `PWA_ENABLED=true` afișează instalarea PWA, iar Service Worker este înregistrat numai când setezi separat `PWA_WORKER_ENABLED=true`. Ambele flaguri sunt implicit `false`.

Procedura se aplică separat fiecărei origini unde PWA a fost activată, inclusiv DEV și LIVE. Menține HTTPS și endpointul public `/sw.js` pe aceeași origine și la același URL.

1. Configurează împreună `PWA_ENABLED=false`, `PWA_WORKER_ENABLED=false` și `PWA_WORKER_RETIREMENT=true`. Reîncarcă configurația aplicației prin mecanismul obișnuit al mediului, astfel încât noile răspunsuri HTML și `/sw.js` să folosească valorile actualizate.
2. Verifică răspunsurile publice. Paginile USER trebuie să includă `pwa.js` pentru cooperarea la retragere, dar să nu ofere controlul de instalare. `/sw.js` trebuie să rămână disponibil ca JavaScript valid, cu `Cache-Control: no-cache, no-store`, și să servească varianta cu `RETIRE=true`.
3. La următoarea pagină încărcată online, clientul caută o înregistrare existentă și solicită `registration.update()` fără a crea una nouă. Workerul de retragere se activează, șterge numai cache-urile AURA ale mediului curent, inclusiv versiunea activă, oprește fallbackul offline și execută `unregister()`. Documentele deja deschise pot rămâne temporar controlate. Nu forța reîncărcarea unui formular nesalvat.
4. Confirmă pe un dispozitiv cu PWA instalată anterior că înregistrarea dispare, cache-urile `aura-pwa-{mediu}-*` dispar, alte cache-uri rămân, iar navigarea online funcționează. Verifică și un dispozitiv revenit după o perioadă offline.

Păstrează `PWA_WORKER_RETIREMENT=true` și `/sw.js` disponibil cât timp există posibilitatea ca un client vechi să revină online. Nu există un interval fix care să dovedească retragerea tuturor dispozitivelor. Oprește modul de retragere doar după confirmarea că toate instalările vizate au primit workerul de retragere sau în cadrul unei decizii explicite de reactivare care tratează clienții încă vechi. Oprirea prematură poate lăsa un worker vechi instalat.
