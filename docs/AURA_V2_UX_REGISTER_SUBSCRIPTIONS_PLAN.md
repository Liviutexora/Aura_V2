# AURA V2 — plan UX pentru înregistrare și abonamente

## 1. Situația actuală

Analiza este limitată la codul local și la schema MySQL citită fără modificări. Nu s-au executat teste sau migrări.

| Zonă | Comportament observat |
| --- | --- |
| Înregistrare | `routes/aura-demo.php` expune `/register`; `AuthController::register()` validează numele, emailul, parola, confirmarea și termenii, creează contul și profilul, autentifică utilizatorul și îl trimite la verificarea emailului. `resources/views/auth/register.blade.php` nu cere telefon și nu indică pașii 1/2. |
| Verificare email | Rutele `auth`, `active`, `verified` protejează dashboardul și abonamentele. `EmailVerificationController::verify()` trimite la `/subscriptions` numai dacă sesiunea conține `subscriptions.selected_plan`; altfel revine la pagina de verificare, iar apoi utilizatorul ajunge în dashboard. Pe LOCAL, `AUTH_EMAIL_VERIFICATION=false` marchează contul nou ca verificat și `AuthController` îl trimite direct în dashboard. Acest bypass nu se aplică în DEV/LIVE. |
| Selecție de plan | `SubscriptionController::select()` salvează ID-ul planului activ în sesiune. Landing Page folosește `Plan::activeCatalog()` și `subscriptions.catalog`, cu carusel din `subscriptions.js`. `/subscriptions` consumă selecția prin `session()->pull()` și deschide automat dialogul planului la prima afișare. La reîncărcare, preselectarea dispare. |
| Activare | `/subscriptions` afișează planurile active, prețurile și beneficiile. Trialul este un formular separat pentru primul plan eligibil, nu neapărat cel ales. Cuponul folosește dialogul existent. `MembershipService` verifică eligibilitatea, planul activ și cuponul; `SubscriptionAccess` verifică abonamentul la înscrierea într-o oportunitate. După răspunsul 201, `subscriptions.js` reîncarcă pagina `/subscriptions`, nu merge în dashboard. Metoda card este deja afișată dezactivată, cu mesaj despre Stripe. |
| Meniu și Landing | Meniul avatarului din `resources/views/layouts/app.blade.php` are doar Setări cont și Deconectare. Linkuri directe spre Abonament apar în navigația USER. Linkul „Abonamente” din headerul Landing Page merge la `/pricing`, pagină DEMO cu prețuri fixe, deși Landing Page include deja catalogul real MySQL. |
| Date | Schema MySQL `users` nu are telefon. `user_profiles.phone` există, este nullable și nu este completat la înregistrare. În MySQL local, Standard este activ cu `trial_days=30`; Premium și Business sunt active cu `trial_days=0`; planul Test este inactiv. Valorile rămân date configurabile, nu reguli de cod. |

## 2. Probleme identificate

1. Fără plan ales pe Landing Page, utilizatorul nu ajunge automat la alegerea abonamentului după verificare. Pe LOCAL, fluxul fără verificare sare direct în dashboard chiar și când există plan selectat.
2. Pasul de abonament nu este prezentat ca pasul 2/2; planul ales se pierde la reafișarea paginii. Trialul este separat de selecția curentă și poate părea disponibil pentru un alt plan. Activarea reușită rămâne pe pagina de abonamente.
3. Formularul nu colectează telefonul; nu există o coloană `users.phone`. Coloana din `user_profiles` nu satisface explicit cerința de stocare în `users`.
4. Headerul Landing trimite spre oferta DEMO din `/pricing`, deși pe aceeași pagină este deja catalogul real. În cont, abonamentele au acces direct în navigație, dar lipsesc din meniul avatarului.
5. Un utilizator care abandonează activarea poate ajunge prin URL direct în dashboard; aceasta nu echivalează cu acces plătit, deoarece `SubscriptionAccess::authorizeEnrollment()` cere abonament efectiv. Este nevoie de o revenire clară la pasul 2, fără acordarea automată a unui plan.

## 3. Arhitectura UX recomandată

```text
Landing → plan real selectat (opțional) → Pasul 1/2: cont + telefon
       → verificare email (obligatorie în DEV/LIVE; bypass local deja configurat)
       → Pasul 2/2: /subscriptions, cu planul ales preselectat
       → trial eligibil SAU cupon valid → Dashboard
```

- Refolosește `/subscriptions` ca ecran pentru pasul 2; nu crea un al doilea catalog sau o infrastructură de abonamente. În contextul înregistrării, afișează indicatorul 2/2, un selector compact cu **toate planurile active** și rezumatul planului curent: preț lunar/trimestrial, perioadă și beneficii. Pe mobil, selectorul și acțiunea principală trebuie să fie vizibile fără căutarea unui alt meniu. Dacă nu există plan preselectat ori planul a devenit inactiv, cere selecție explicită și explică situația.
- Păstrează selecția în sesiune până la schimbare explicită sau activare reușită; nu o consuma la primul GET. Schimbarea planului trebuie să actualizeze selecția fără reintroducerea datelor de cont. Refolosește catalogul, cardurile și logica de activare existente; o ajustare punctuală a selecției în sesiune este suficientă.
- Arată trialul doar pentru planul **selectat**, dacă `trial_days > 0` și utilizatorul este eligibil conform `MembershipService`; etichetează durata din MySQL. În prezent doar Standard are 30 de zile. Nu sugera trial de 30 de zile pentru Premium sau Business și nu schimba automat planul ales. Cuponul se validează exclusiv pe server. Cardul rămâne dezactivat: „Disponibil în curând”, fără câmpuri sau stocare de date bancare.
- Pentru utilizatorul care întrerupe procesul, păstrează contul și dreptul de a relua `/subscriptions` din meniul profilului. Arată un îndemn discret de continuare dacă nu are abonament. Nu acorda trial ori plan plătit implicit; păstrează verificările existente la înscrierea în oportunități. Evită un redirect global obligatoriu din toate paginile, care ar putea bloca utilizatorii existenți și accesul de informare.
- În meniul avatarului: Setări cont, Abonamente (`/subscriptions`), Securitate · DEMO, Preferințe · DEMO, Deconectare. Elementele DEMO sunt text vizibil, dezactivat și accesibil, fără rute fictive. Mută accesul principal la abonamente în acest meniu și elimină intrările redundante din navigația USER relevantă; păstrează ruta `/subscriptions` și linkurile de activare existente. În `/subscriptions`, înlocuiește eticheta „Istoric facturare” cu un text onest despre activările gratuite; nu implementa facturi.
- În headerul Landing, „Abonamente” duce la ancora catalogului real de pe aceeași pagină. Păstrează `/pricing` pentru compatibilitate, fără a-l prezenta ca ofertă reală în noul flux. Nu duplica cardurile.

**Soluția UX mai simplă:** folosirea aceleiași pagini `/subscriptions` pentru onboarding și administrarea ulterioară evită un wizard separat. Diferența este un indicator 2/2 și o acțiune clară când utilizatorul vine din înregistrare; regulile comerciale și catalogul rămân aceleași.

## 4. Plan de implementare — două calupuri

### Calupul 1 — Înregistrare → abonament → Dashboard

**Obiectiv:** încheierea fluxului funcțional în doi pași, inclusiv telefonul și cazul LOCAL fără verificare email.

**Fișiere vizate:** o migrare nouă pentru `users.phone`; `app/Http/Controllers/AuthController.php`, `EmailVerificationController.php`, `SubscriptionController.php`; `app/Models/User.php` numai dacă este necesar pentru convențiile atributului; `resources/views/auth/register.blade.php`, `resources/views/subscriptions/index.blade.php`, `catalog.blade.php`, `plan-card.blade.php`, `activation-dialog.blade.php`; `public/assets/aura/subscriptions.js` și CSS-ul relevant; teste feature noi sau cele de înregistrare/activare existente. Nu se schimbă `MembershipService` și `SubscriptionAccess` decât dacă o probă țintită arată o incompatibilitate reală.

**Schimbări:**

1. Adaugă `users.phone` nullable, fără backfill sau schimbarea conturilor existente; telefonul este obligatoriu numai la crearea conturilor noi. `user_profiles.phone` rămâne neatins. Normalizează la E.164 (`+` și maximum 15 cifre): acceptă numere internaționale cu `+`, prefix `00` convertit la `+` și, dacă se aprobă această convenție, numere românești `07xxxxxxxx` convertite la `+407xxxxxxxx`. Respinge valori ambigue, lungimi invalide și caractere nepermise. Nu presupune unicitatea telefonului. Folosește `type="tel"`, `autocomplete="tel"` și mesaje clare.
2. Adaugă „Pasul 1 din 2” în Register. După crearea contului, marchează în sesiune intenția de finalizare a abonamentului. În DEV/LIVE păstrează verificarea emailului și redirecționează la pasul 2 după cod valid, indiferent dacă exista plan ales. Pe LOCAL cu `AUTH_EMAIL_VERIFICATION=false`, sari peste cod direct la pasul 2. Loginul conturilor existente și schimbarea emailului nu trebuie să intre accidental în onboarding.
3. Pe `/subscriptions`, păstrează planul din Landing preselectat, afișează selectorul tuturor planurilor active și sumarul ales, cu indicator „Pasul 2 din 2” numai în contextul onboarding. Dacă planul devine inactiv, cere altul. Persistă schimbarea selecției în sesiune; nu trata `session()->pull()` ca sursă durabilă. Reutilizează activarea prin cupon și trialul existent, dar leagă afișarea trialului de planul selectat și verifică eligibilitatea la server. Cardul Stripe rămâne doar un mesaj dezactivat.
4. La succesul trialului sau cuponului, curăță intenția de onboarding și du utilizatorul în Dashboard; pentru administrarea obișnuită a abonamentului, păstrează comportamentul potrivit contextului. Nu acorda abonament în registrare, verificare sau simpla selecție.

**Dependențe:** migrarea nullable înainte de codul care scrie `users.phone`; date active în `plans`; sesiunile existente; verificarea emailului; endpointurile `/subscriptions/trial` și `/subscriptions/redeem`. Fără Stripe.

**Teste țintite:** validare și normalizare telefon, persistență `users.phone`, cont existent fără telefon; înregistrare cu/fără plan pe Landing, verificare email și bypass LOCAL, reluare după login; plan dezactivat între selecție și pasul 2; selector cu planuri active MySQL; trial doar pentru plan/utilizator eligibil, cupon valid/invalid, absența acordării implicite; redirect la Dashboard doar după activare, plus CSRF și middleware `verified` păstrate. Folosește exclusiv DB de test izolată.

**Acceptare:** un utilizator nou ajunge la pasul 2 fără căutarea abonamentelor; planul ales rămâne selectat și poate fi schimbat; niciun cont nou nu este salvat fără telefon valid; conturile vechi rămân utilizabile; numai o activare validă creează membership; succesul duce în Dashboard.

### Calupul 2 — Meniul profilului și linkul din Landing

**Obiectiv:** eliminarea navigației redundante după stabilizarea fluxului principal.

**Fișiere vizate:** `resources/views/layouts/app.blade.php`, `resources/views/public/landing.blade.php`, `resources/views/subscriptions/index.blade.php` și numai template-urile USER care conțin intrări redundante spre abonamente; CSS/JS existent pentru dropdown, dacă este necesar; teste HTTP punctuale pentru linkuri și starea meniului. Nu se creează pagini Securitate, Preferințe sau Facturi.

**Schimbări:** adaugă cele cinci elemente în meniul avatarului, cu două elemente DEMO neinteractive și accesibile; păstrează Deconectare ca POST cu CSRF. Elimină doar accesul duplicat din navigația USER, fără schimbarea rutei. Direcționează linkul din headerul Landing la o ancoră stabilă pe catalogul real existent și păstrează caruselul. Clarifică textul despre facturare fără a simula facturi.

**Dependențe:** calupul 1 și catalogul MySQL deja reutilizat pe Landing.

**Teste țintite:** linkul Landing indică ancora reală, catalogul conține doar planuri active, meniul avatarului conține `/subscriptions` și elementele DEMO fără destinații fictive, logoutul rămâne POST/CSRF, ruta `/subscriptions` rămâne accesibilă. Verificare manuală mobil/desktop a meniului și scrollului; fără Playwright/E2E.

**Acceptare:** din Landing se ajunge la catalogul real prin scroll; din cont, abonamentele au o intrare clară în meniul profilului; nu există copii ale catalogului ori pagini fictive.

## 5. Prompturi complete pentru Codex

### Prompt 1 — Fluxul de înregistrare și activare

> Lucrezi în `E:\Projects\Aura\Aura_V2`, Laravel 13. Implementează numai calupul 1 din `docs/AURA_V2_UX_REGISTER_SUBSCRIPTIONS_PLAN.md`. Citește `AGENTS.md` și regulile aplicabile. Creează un flux continuu „Pasul 1 din 2: Creează contul” → verificare email acolo unde este obligatorie → „Pasul 2 din 2: Alege abonamentul” pe pagina existentă `/subscriptions` → Dashboard numai după activare reușită. Adaugă telefon obligatoriu în formularul de înregistrare și în validarea serverului; stochează-l în coloana nouă nullable `users.phone` prin migrare Laravel, fără backfill și fără a bloca utilizatorii vechi. Normalizează și validează formatul internațional E.164, cu mesaje explicite; nu introduce unicitate fără cerință. Păstrează verificarea emailului în DEV/LIVE, inclusiv middleware-ul, și respectă bypassul existent exclusiv pe LOCAL când `AUTH_EMAIL_VERIFICATION=false`: în acest caz, continuă direct la pasul 2 fără cod. Păstrează planul activ selectat pe Landing în sesiune până la schimbare sau activare; dacă lipsește ori devine inactiv, cere alegerea unui plan activ. Afișează un selector cu toate planurile active din MySQL, prețul lunar/trimestrial, perioada și beneficiile planului ales. Refolosește `Plan::activeCatalog()`, componentele `subscriptions.*`, `SubscriptionController`, `MembershipService`, `SubscriptionAccess` și `subscriptions.js`; nu reconstrui infrastructura. Afișează trialul numai dacă planul ales are `trial_days > 0` și utilizatorul este eligibil conform regulilor existente; în datele locale, Standard are 30 de zile, celelalte planuri active nu au trial. Păstrează activarea prin cupon validat pe server. Cardul Stripe rămâne dezactivat cu „Disponibil în curând”; nu colecta/salva date de card. După 201 pentru trial/cupon, navighează în Dashboard pentru utilizatorul aflat în onboarding. Pentru abandon, păstrează posibilitatea reluării din `/subscriptions`, fără membership implicit și fără acces neautorizat la înscrierea în oportunități. Păstrează designul general și identitatea AURA, logica comercială aprobată, autentificarea, autorizarea și CSRF. Nu integra Stripe, nu modifica module independente, nu modifica `ProjectWorkflowTest.php`. Verifică schema și izolarea DB înainte de teste; execută numai teste țintite pentru telefon, continuarea după verificare/bypass LOCAL, selecția planului, trial, cupon și redirectul final. Fără Playwright/E2E, suită completă, commit, push sau modificări pe Hetzner. La final, raportează fișierele, testele și limitările.

### Prompt 2 — Navigația profilului și catalogul Landing

> Lucrezi în `E:\Projects\Aura\Aura_V2`, Laravel 13, după finalizarea calupului 1 din `docs/AURA_V2_UX_REGISTER_SUBSCRIPTIONS_PLAN.md`. Citește `AGENTS.md` și regulile aplicabile. Implementează numai calupul 2. În meniul avatarului din `resources/views/layouts/app.blade.php`, afișează în ordine: Setări cont (ruta existentă), Abonamente (`/subscriptions`), Securitate · DEMO, Preferințe · DEMO, Deconectare (POST existent cu CSRF). Elementele DEMO trebuie să fie vizibile, explicit neimplementate și neinteractive; nu crea pagini sau rute pentru ele. Mută intrarea principală Abonamente aici și elimină numai linkurile redundante din navigația USER relevantă, păstrând ruta `/subscriptions` și toate linkurile necesare activării. În `resources/views/public/landing.blade.php`, schimbă linkul „Abonamente” din header astfel încât să deruleze la catalogul real MySQL deja randat prin `subscriptions.catalog`; folosește o ancoră stabilă și nu duplica planurile. Nu șterge `/pricing`; păstreaz-o compatibilă, dar nu o promova ca ofertă reală în header. Pe pagina `/subscriptions`, clarifică faptul că facturile nu sunt încă disponibile, fără a construi funcționalitatea lor. Verifică mobil și desktop prin inspecția markup/CSS și verificare manuală dacă este disponibilă; fără Playwright/E2E. Adaugă doar teste HTTP punctuale pentru linkul Landing, planuri active și meniul profilului. Păstrează designul general, logica comercială, autentificarea, autorizarea și CSRF. Nu integra Stripe, nu modifica module independente, nu modifica `ProjectWorkflowTest.php`, nu rula suite complete, nu face commit/push și nu modifica Hetzner. Raportează fișierele, testele și limitările.

## 6. Decizii de confirmat înaintea implementării

1. **Abandonul pasului 2:** recomand păstrarea accesului de informare la dashboard și catalog, cu îndemn de revenire la abonamente, dar fără posibilitatea de înscriere în oportunități fără membership efectiv. Alternativa este blocarea dashboardului până la activare; aceasta ar afecta și cazurile fără trial/cupon. Confirmați politica dorită.
2. **Telefon românesc fără prefix internațional:** recomand acceptarea formei `07xxxxxxxx` și normalizarea la `+407xxxxxxxx`; pentru alte țări, cererea explicită a prefixului `+`. Confirmați dacă se dorește numai format internațional introdus de utilizator.

Celelalte decizii din plan păstrează cerințele aprobate și comportamentul comercial existent.
