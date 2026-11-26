# AURA V2 — Audit tehnic și arhitectural PWA

**26 noiembrie 2026 · Audit local de cod · Versiunea 1**

## 1. Rezumat executiv

**AURA poate primi incremental instalare PWA, notificări interne și Web Push fără rescrierea aplicației. În prezent nu are o infrastructură PWA implementată.** Laravel, autentificarea pe sesiune, componenta `Notifiable` a utilizatorului, mementourile și jurnalele de evenimente oferă puncte utile de integrare. Ecranele demonstrative de notificări nu reprezintă un motor de livrare.

Recomandarea este o singură aplicație instalabilă pentru interfața USER, același backend și aceleași sesiuni, un singur centru de notificări bazat pe mecanismul Laravel și un transport Web Push adăugat ulterior. ADMIN este în prezent interfața SUPER ADMIN; nu există un al treilea sistem de autentificare sau un rol operațional `admin` implementat.

| Etapă | Pregătire constatată | Verdict |
| --- | --- | --- |
| PWA V1 — instalare | Layout USER comun, CSS responsive și rute reale; lipsesc manifestul, pictogramele de instalare, controlul instalării și offline | Pregătire bună pentru o livrare mică, fără migrări și fără dependențe noi |
| PWA V2 — notificări și badge | `User::Notifiable`, mementouri reale, evenimente persistente, scheduler și configurație queue; UI administrativ demonstrativ | Pregătire parțială; trebuie construit motorul, persistența, preferințele și autorizarea resurselor noi |
| PWA V3 — Web Push | PHP local are extensiile principale; există fundația queue | Pregătire redusă pentru livrare imediată; depinde de V2, Service Worker, transport, abonamente pe dispozitive și operare |

**Decizii esențiale:**

- V1 poate oferi instalare fără Service Worker în browserele moderne vizate. Pentru livrarea și a paginii offline AURA recomand un Service Worker minimal, care păstrează numai o pagină publică offline, fără cache pentru pagini sau date autentificate.
- V2 trebuie să înlocuiască atât dialogul de mementouri, cât și clopoțeii demonstrativi cu aceeași sursă de adevăr. Emailurile de verificare și resetare rămân pe fluxurile actuale.
- Badge-ul numeric exact nu este o promisiune multiplatformă realizabilă. Contorul din interfața USER poate fi exact; reprezentarea pe iconița sistemului și actualizările în fundal depind de browser și OS.
- V3 poate folosi standardele Web Push fără furnizor SaaS comercial obligatoriu. Serviciile Push ale browserelor/OS rămân intermediari necesari; nu înseamnă infrastructură complet autonomă sau livrare garantată.
- Worker cu domeniu `/` ar putea controla și documentele `/admin`. Excluderea lor din strategia `fetch` reduce riscul operațional, dar nu este o frontieră de securitate între aplicații pe aceeași origine.

Riscurile dominante sunt cache-ul datelor altui utilizator pe dispozitive comune, asocierea greșită a abonamentelor Push, expunerea informațiilor pe ecranul blocat, trimiterea duplicată la retry, destinațiile neautorizate și persistența unui Service Worker defect după rollback.

## 2. Metodă, dovezi și limite

### 2.1 Ce a fost verificat

Auditul folosește fișierele aplicației, migrările, testele, implementarea instalată a canalului Laravel de notificări, `composer show --direct`, `php -v`, `php -m` și inventarul `php artisan route:list --except-vendor --no-interaction` — 141 rute în rezultatul local. S-au consultat strict în citire starea Git și setări de mediu neconfidențiale dintr-o listă explicită. Nu s-a afișat conținutul integral al `.env`.

`AGENTS.md` a fost citit. Directorul `.ai/rules` nu există în această copie; nu există un index de reguli suplimentar de aplicat. Au fost citite skillurile proiectului `laravel-best-practices` și `testing-best-practices`, inclusiv regulile relevante pentru securitate, evenimente, queue și evaluarea testelor.

**Legendă:**

- **[COD]**: existență/comportament dedus direct din sursa inspectată; nu echivalează cu un test executat cu succes.
- **[LOCAL]**: rezultat al unei comenzi locale de inventariere/configurație.
- **[DOC]**: comportament documentat de platformă/framework, cu sursă externă.
- **[PROPUNERE]**: arhitectură sau regulă propusă; nu există încă în aplicație.
- **[NEVERIFICAT]**: necesită infrastructură, date sau dispozitive neinspectate.

Referințele `fișier:linie` indică sursa existentă la audit. Câteva template-uri și scripturi sunt scrise pe linii foarte lungi; o singură linie poate conține un întreg ecran. Numele de fișiere noi din plan sunt propuneri, nu dovezi ale implementării.

### 2.2 Ce nu a fost executat

Nu s-a conectat auditul la baza de date, nici pentru interogări sau introspecție. Schema descrisă este cea declarată în migrări; aplicarea lor efectivă în LOCAL, DEV și LIVE nu este confirmată. La implementare, înaintea oricărei migrări/model nou, se va inspecta schema reală prin Boost `database-schema`.

Nu s-au rulat teste, build, formatter, scheduler, queue worker, tinker, migrări, seedere, instalări, operațiuni de cont sau navigări autentificate. Testele existente pot executa migrări chiar dacă folosesc SQLite în memorie. Nu s-au accesat Hetzner, DEV sau LIVE. Nu s-au făcut commit, push ori deploy. Nu există rezultate Lighthouse, măsurători de încărcare, audit de penetrare sau validare fizică Android/iOS/desktop.

Motorul sandbox local nu a putut porni procese; comenzile de citire au fost executate cu aprobarea instrumentului în afara sandboxului. Aceasta nu schimbă limitele auditului.

## 3. Inventarul arhitecturii existente

### 3.1 Runtime și dependențe

| Componentă | Dovezi | Concluzie |
| --- | --- | --- |
| PHP | [LOCAL] `php -v`: 8.4.27; `composer.json:9` declară `^8.3` | Runtime local 8.4, fără a presupune runtime-ul LIVE |
| Laravel | [LOCAL] `composer show --direct`: framework 13.42.0; `composer.json:10` cere `^13.17` | APIs verificate pentru Laravel 13, nu exemple din versiuni vechi |
| Instrumente PHP | Boost 2.10.4, PHPUnit 12.5.44, Pint 1.32.3, PAO 1.1.5 | Reutilizabile pentru implementare și verificare ulterioară |
| Frontend | `package.json`: Vite `^8.0.0`, plugin Laravel `^3.1`, Tailwind și plugin `^4.0.0`, concurrently `^10.0.3`, multiplex opțional `^0.4.1` | Acestea sunt constrângeri declarate, nu versiuni instalate confirmate |
| Reproductibilitate JS | [LOCAL] lipsesc `package-lock.json` și pachetele căutate în `node_modules` | Nu se poate confirma build-ul sau o versiune JS exactă; auditul nu instalează nimic |
| PWA/Web Push | Inventarul Composer direct și `package.json`; căutare în app/config/routes/resources/public/tests | Niciun pachet PWA, transport Web Push sau client Push identificat |
| SPA/realtime | Rute Blade, JS simplu, fără dependențe directe Livewire/Inertia/Echo/Reverb | PWA nu necesită SPA, Sanctum, JWT, websocket sau rescriere frontend |

### 3.2 Aplicația Laravel și frontend

**[COD]** `bootstrap/app.php:10` înregistrează rutele web, consola și health `/up`; `routes/web.php:5` include `aura-demo.php` și `admin.php`. Numele `aura-demo.php` și comentariul de la linia 11 sunt istorice: fișierul conține acum și fluxuri reale protejate, nu doar preview.

| Zonă | Existent și funcțional în cod | DEMO / parțial / lipsă | Reutilizare și extindere |
| --- | --- | --- | --- |
| Layout USER | `resources/views/layouts/app.blade.php:2` are limbă, viewport și `theme-color`; layout de bază comun | Nu include manifest, touch icon, instalare sau înregistrare SW | Un singur punct pentru metadate și bootstrap PWA |
| Parcurs real USER | `layouts/project.blade.php:1`, Dashboard, Proiecte, Misiuni, Oportunități, Recomandări | Clopoțelul vede numai mementouri livrate, fără read/unread | Înlocuire cu componentă comună de notificări în V2 |
| Alte ecrane USER | `profile/settings.blade.php:1`, `subscriptions/index.blade.php:1`, Wallet, Realizări, Integrări extind direct `layouts.app` | Header-e duplicate; mai mulți clopoței folosesc `info(...)` cu mesaje demonstrative | Integrarea V2 trebuie să acopere fiecare header, fără rescrierea modulelor |
| ADMIN / SUPER ADMIN | `admin/layouts/app.blade.php:9` include topbar/sidebar; controllers reale pentru utilizatori/proiecte/abonamente | Notificări, anunțuri, șabloane și jurnal sunt încă fixtures | Reutilizare layout și componente; înlocuire limitată la modulul notificări |
| JS USER | `layouts/app.blade.php:8`–`14`: premium-icons, referrals, project-ui sau app.js, în funcție de rută | `resources/js/app.js:1` este gol; nu este punctul efectiv al funcțiilor USER existente | Un fișier PWA separat, încărcat o singură dată, independent de ramura JS |
| JS ADMIN | `admin/layouts/app.blade.php:53`: projects.js pentru `projectAdmin`, altfel data.js + admin.js | Simulările sunt globale și pot suprascrie DOM/date; nu trebuie reutilizate ca transport real | Ecranele reale noi se marchează ca reale și se protejează de `render()` demo |
| Vite | `vite.config.js:9`: numai `resources/css/app.css`, `resources/js/app.js` | Layout ADMIN cere și entrypoints admin la `admin/layouts/app.blade.php:4`, neincluse în inputul Vite | Divergență existentă; nu migra întreg frontend-ul la Vite pentru PWA |
| CSS | `public/assets/aura/app.css:2` are breakpointuri și navigare mobilă; CSS admin separat | CSS USER importă Google Fonts la linia 1; standalone/safe areas/tastatură nevalidate fizic | Offline trebuie să folosească fonturi sistem și stiluri locale minimale |

**Risc de integrare:** cod adăugat numai în `resources/js/app.js` nu va ajunge la majoritatea paginilor în configurația actuală. Cod adăugat în interiorul ramurii demo poate lipsi pe login ori pe paginile reale. Bootstrapul PWA trebuie pus explicit în layout, în afara acestor condiții, cu propriile feature flags și protecții.

### 3.3 Rute, autentificare, autorizare și sesiuni

- **USER real:** `routes/aura-demo.php:15` și `:18` folosesc `auth`, `active`, `verified:aura.verify-email`; acoperă catalog, proiecte, misiuni, dovezi, setări și abonamente. Verificarea emailului are grupul `auth` + `active` la linia 56.
- **Administrare:** grupul `routes/admin.php:65` adaugă `super_admin`; loginul admin este separat ca pagină la `:57`, dar folosește același `AuthController` și același guard.
- **Roluri reale:** `User::isSuperAdmin()` la `app/Models/User.php:78` cere `role === 'super_admin'` și status activ. Migrările users, liniile 20–33, declară `user`/`super_admin`; constrângerile CHECK sunt aplicate condiționat pentru MySQL. Matricea Admin/Suport din `admin/pages/roles.blade.php:3` este demonstrativă și nu acordă permisiuni reale.
- **Middleware:** `EnsureActiveUser::handle()` la `:11` și `EnsureSuperAdmin::handle()` la `:11` opresc cu 403; `bootstrap/app.php:17` activează autentificarea sesiunilor. Redirectarea oaspeților este diferențiată după calea `/admin` la `:23`.
- **Guard:** `config/auth.php:40` definește numai `web`, driver `session`, provider `users`. PWA trebuie să păstreze acest model; instalarea nu autentifică utilizatorul și nu transferă garantat sesiunea browserului în containerul instalat.
- **Login:** `AuthController::login()` la `:52` selectează rolul după zonă; `LoginAttempts` gestionează autentificarea și protecțiile suplimentare. Înregistrarea regenerează sesiunea la `AuthController.php:127`.
- **Logout:** `AuthController::logout()` la `:192` apelează logout, invalidarea sesiunii și regenerarea tokenului CSRF. Ruta este POST cu `auth`, la `routes/aura-demo.php:47`. Nu trebuie convertită în GET.
- **Sesiune:** `config/session.php:21` default database, `:35` 120 minute, `:50` encrypt false, `:146` path `/`, `:172` secure din env, `:185` HttpOnly true, `:202` SameSite lax. Sunt configurări, nu o probă a cookie-ului emis în LIVE.
- **Izolare resurse:** `MissionController::owner()` la `:40` verifică proprietarul și răspunde 404; dovezile permit proprietar sau super admin la `:79`. Acest tipar trebuie păstrat pentru notificări și abonamente; cunoașterea unui UUID nu acordă acces.

**[COD]** Layouturile au tratament BFCache și indicator local de logout (`layouts/app.blade.php:18`, `admin/layouts/app.blade.php:51`). `aura-logged-out` este un indiciu pentru UI, nu autentificare. Un Service Worker sau un token salvat în localStorage nu trebuie să îl transforme în mecanism de securitate.

### 3.4 PWA, imagini și identitatea aplicației

**[COD]** Căutarea în sursele aplicației nu a identificat manifest web, `serviceWorker`, `beforeinstallprompt`, `pushManager`, `notificationclick`, VAPID sau Badging API. Nici lista de rute nu conține endpointuri PWA/Push. `public/index.php`, `robots.txt` și resursele statice existente nu furnizează aceste funcții.

**Resurse disponibile:** coroana și alte simboluri în `public/assets/aura/icons.svg:2`, sprite echivalent admin, elemente de brand în markup, `mountain.svg`, `mountain.png`, `mountain-premium.png`. Acestea oferă direcție vizuală, dar nu sunt un set oficial de iconițe instalabile. `public/favicon.ico` are **0 octeți** în copia inspectată. Imaginile mountain sunt decorative; varianta premium are aproximativ 1,7 MB și nu este potrivită pentru offline minimal.

**Lipsesc:** iconițe PNG 192/512, variante maskable cu margine sigură, touch icon Apple, identitate de manifest stabilă și aprobare de brand. Coroana existentă poate fi baza unei propuneri grafice, dar nu trebuie declarată automat logo oficial. Nu este necesară generare AI sau o dependență grafică pentru implementarea tehnică.

## 4. Notificările, datele și execuția asincronă existente

### 4.1 Ce este real

| Componentă | Dovadă exactă | Comportament și limită |
| --- | --- | --- |
| Utilizator notificabil | `app/Models/User.php:24` folosește `Notifiable` | Poate reutiliza relațiile Laravel `notifications`/`unreadNotifications` după crearea tabelei; traitul nu creează tabela |
| Verificare email | `app/Notifications/VerifyEmailCode.php:14` — `via()` | Numai canal `mail`; nu inbox și nu Push |
| Resetare parolă | `app/Notifications/ResetAccountPassword.php:16`, `:21` | Email cu link construit pe baza URL-ului aplicației; nu include în notificări generale |
| Expediere cont | `app/Services/AccountMail.php:15` — `send()` | Verifică transport SMTP sau array în testing, apoi `notifyNow()`; returnează succes/eșec fără a loga mesajul complet al excepției |
| Email după tranzacție | `AuthController.php:212` | `DB::afterCommit` + `defer` pentru resetare; diferit de queue persistentă, nu dovadă de worker activ |
| Programare memento | `MissionController::reminder()` la `:87` | Verifică proprietar, timezone și dată; salvează/cancel/revizuiește în tranzacție |
| Livrare memento intern | `routes/console.php:14`–`30` | Scheduler la minut; lock, verificare revision/status, creează `reminder_sent`, marchează `sent` |
| Afișare memento | `AppServiceProvider.php:30`–`33`, `layouts/project.blade.php:7` | Ultimele 10 `ProjectEvent` de tip `reminder_sent`, filtrate prin proiectul utilizatorului; fără citit/necitit |
| Evenimente proiect | `ProjectEnrollment::event()` la `:127` | Rânduri persistente, UUID `event_key`, tip/stări/metadate; actualizează și termene dependente, deci nu se înlocuiește metoda cu un nou motor |
| Progres/review | `MissionProgress.php:88`, `:122`, `:157`, `:160` | Produce `mission_submitted`, `submission_reviewed`, `mission_completed`, `project_completed` |
| Evenimente abonament | `SubscriptionEvent::record()` la `:74`; `MembershipService.php:288`, `:310` | Istoric comercial și expirări; nu notificări și nu evenimente Laravel dispatchate |

`sent` pentru un memento înseamnă în prezent **înregistrat în jurnal pentru afișare internă**. Nu dovedește email, Push, vizualizare sau citire. Noul jurnal trebuie să evite reutilizarea acestei etichete cu un sens mai larg.

### 4.2 Ce este demonstrativ

- `routes/admin.php:24` încarcă `resources/data/admin-demo.json`, fixtures și maparea view-urilor. `/admin/notifications`, `/admin/announcements`, `/admin/messageTemplates`, `/admin/delivery-log` folosesc acest mecanism la liniile 125–127 și 148.
- `resources/views/admin/pages/notifications.blade.php:3`, `announcements.blade.php:3`, `messageTemplates.blade.php:3` oferă liste/formulare demonstrative. `delivery-log.blade.php:3` spune explicit că nu există mesaje reale.
- `resources/js/admin.js:60` și `public/assets/admin/admin.js:32` au `previewMessage`/`simulateSend`; simularea modifică starea locală și jurnalul demo. Nu trebuie apelate de acțiunea reală „Trimite”.
- `resources/js/admin.js:75`–`77` gestionează permisiuni și setări demo; `public/assets/admin/admin.js:5`–`6` persistă `aura-admin-demo-v1` în localStorage.
- `resources/views/admin/components/topbar.blade.php:1` apelează `alerts()`; mesajele acestuia sunt demonstrative, nu starea queue-ului.
- Header-ele USER din Setări, Wallet, Integrări și Realizări afișează mesaje de exemplu. Bula din DOM sau un clopoțel nu constituie badge OS.

**De reutilizat:** layoutul admin, cardurile, tabelele, empty states, tabs și iconografia. **De înlocuit:** sursele fixtures, formularele generice de record demo și acțiunile locale pentru ecranele transformate în reale. Anunțurile/șabloanele pot rămâne demo în V2 dacă nu sunt aprobate separat; nu se implementează patru motoare pentru patru taburi.

### 4.3 Modele, tabele și relații

**[COD — migrări, nu schemă live]**

| Date existente | Locație | Utilitate PWA și extindere |
| --- | --- | --- |
| users, sessions, password_reset_tokens | `0001_01_01_000000_create_users_table.php:15`, `:36`, `:42` | Identitate și sesiune existente; nu stoca Push endpoints în `sessions` |
| user_profiles | `2026_11_15_000001_create_user_profiles_table.php:11` | Relație 1:1, avatar/telefon/locale/timezone; fără preferințe notificări |
| project_events | `2026_11_19_075847_create_project_module_tables.php:239` | Proiect, misiune/pas, actor, tip, stări, `event_key` unic, metadate, index proiect/timp/id |
| mission_reminders | aceeași migrare `:256` | Un memento per misiune, timezone, status, revision și index status/remind_at; se păstrează |
| project_missions și submissions | aceeași migrare `:146`, `:216` | Proprietarul se rezolvă prin project; dovezile și notele pot fi confidențiale |
| user_memberships și subscription_events | `2026_11_24_081623_create_subscription_infrastructure.php:28`, `:53` | Fapte comerciale reutilizabile ca surse, fără schimbarea entitlementurilor |
| jobs, job_batches, failed_jobs | `0001_01_01_000002_create_jobs_table.php:14`, `:24`, `:37` | Backend queue declarat, nu confirmare că tabelele sunt aplicate sau workerii porniți |

Nu există migrări pentru inboxul Laravel `notifications`, preferințe persistente, campanii reale, outbox de livrare, abonamente Push sau încercări Push. `ProjectEvent` și `SubscriptionEvent` sunt modele Eloquent de jurnal, nu clase de eveniment Laravel cu listeners de livrare.

### 4.4 Queue, scheduler, setări și medii

**[COD]** `config/queue.php:16` folosește implicit `database`; conexiunea database are `retry_after=90` și `after_commit=false` la `:43`–`44`. Redis, sync și alți drivere sunt configurări disponibile, nu infrastructură operațională verificată. Nu au fost identificate joburi proprii `ShouldQueue`, listeners sau infrastructură de broadcast în aplicație pentru notificări generale. Evenimentele framework `Verified` și `PasswordReset` există, dar nu furnizează motorul cerut.

**[COD]** `routes/console.php:30` programează mementouri la minut; `:32` curăță resetările; `:37` închide perioadele expirate, cu `withoutOverlapping`. Cron-ul/schedulerul și workerii LIVE nu sunt confirmați. Nu modifica driverul global de queue sau comportamentul emailurilor pentru a activa noul modul.

**[COD]** `profile/settings.blade.php:23` are checkboxuri etichetate explicit „preferință demo”, fără persistență pe server. `AuthController::updateProfile()` validează numai nume/email/parola curentă, iar `UserProfile` nu are câmpuri de notificări. Se adaugă un endpoint separat de preferințe pentru a păstra protecțiile profilului.

| Mediu | Constatat | Necesar înaintea implementării/lansării |
| --- | --- | --- |
| LOCAL | `.env`: local/debug true, URL localhost HTTP, MySQL, sesiuni database/120 min, queue database, cache file, SMTP; numai setări neconfidențiale consultate | Localhost este excepție pentru secure context; un telefon pe un IP LAN HTTP nu beneficiază automat de ea |
| Șablon env | `.env.example`: SQLite, cache database, restul fundației similar | Nu este configurație DEV/LIVE și nu se copiază peste secrete |
| Testing | `phpunit.xml:21`–`31`: testing, SQLite memory, cache/session array, queue sync, mail array | Izolare obligatorie înainte de teste; verificarea concurenței MySQL necesită mediu separat |
| DEV | Nicio configurație dedicată de infrastructură confirmată | Origine HTTPS separată, DB/queue/VAPID proprii, campanii limitate la testeri, etichetă AURA DEV |
| LIVE | Nu a fost accesat | Confirmare HTTPS/proxy, URL canonic, cookie Secure, debug false, scheduler/worker, backup, egress Push și antete statice |

`public/.htaccess` gestionează rewrite și transmiterea headerelor Authorization/X-XSRF-Token; nu declară politică PWA/cache/CSP/HSTS. Nu se poate deduce configurația Nginx/CDN/proxy din el. `APP_URL` local a fost confirmat și prin Boost `get-absolute-url`; nu este domeniul de producție.

## 5. Acoperirea existentă prin teste

**[COD]** Există teste valoroase pentru fundația de securitate. Nu au fost executate în acest audit și nu se declară „toate trec” sau un procent de coverage.

| Suită / dovadă | Acoperire relevantă declarată în cod | Ce rămâne necesar pentru PWA |
| --- | --- | --- |
| `Security/AuthorizationAccessMatrixTest.php:21`–`184` | Acces setări, profil, parole, administrare și intrare admin veche | Aceeași matrice pentru notificări, campanii, preferințe și Push |
| `Security/UserIsolationTest.php:18`, `:62` | Resursele contului și câmpuri sensibile nu pot fi schimbate prin ID în payload | Izolare inbox/mark-read/abonamente/dispozitive |
| `Security/AdminPasswordResetSecurityTest.php:149`, `:167` | CSRF real și limitarea încercărilor pentru reset admin | CSRF activ explicit în testele mutațiilor PWA, nu presupus din testele standard |
| `Auth/PasswordSessionInvalidationTest.php:22`, `:61`, `:80`, `:126` | Păstrare sesiune curentă, invalidare sesiuni secundare după schimbări de parolă | Logout și schimbarea parolei să revoce autorizarea Push conform contractului V3 |
| `Auth/ProfileUpdateSecurityTest.php:38`, `:87`, `:109`, `:172` | Confirmare parolă pentru email, resetarea verificării, protecția câmpurilor | Preferințele separate să nu modifice profilul/rolul/statusul |
| `Auth/AccountEmailTest.php` | OTP/resetare/email și simularea eșecurilor | Regresie: niciun OTP/token copiat în inbox/Push/cozi noi |
| `ProjectWorkflowTest.php:125`–`135` | Programare/revision memento și scheduler apelat de două ori, un singur `reminder_sent` | Un singur inbox/outbox pentru eveniment; fără trimiteri duplicate la retry |
| `SubscriptionInfrastructureTest.php` și teste cupoane | Acces, cote, perioade, concurență și istoricul comercial | PWA să nu schimbe drepturile, perioadele sau acceptarea proiectelor |
| `CouponConcurrencyTest.php:21`–`28` | Test separat, protejat de numele bazei MySQL; rulează migrări dacă este activat | Nu se folosește DB locală/LIVE pentru verificările de concurență |

Lipsesc teste pentru manifest, asseturi PWA, Service Worker/offline/update/rollback, instalare fizică, Badging API, inbox persistent, campanii, transport Push și asocierea dispozitivelor. PHPUnit poate verifica serverul și contractele; nu demonstrează instalarea iOS, launcher Android, suspendarea procesului sau afișarea pe lock screen. Nu adăuga framework browser/test dependency automat: folosește testele existente plus verificare manuală pe dispozitive și un harness existent numai dacă este disponibil la implementare.

## 6. Fezabilitate și limite ale platformelor

### 6.1 Instalare și Service Worker

**[DOC]** Manifestul și HTTPS/localhost sunt fundația instalării promovate de browserele compatibile. Un Service Worker nu este obligatoriu pentru instalabilitatea modernă; fără el nu putem intercepta navigarea eșuată pentru a afișa offline-ul AURA. Interfața proprie de instalare folosește `beforeinstallprompt` unde există și instrucțiuni manuale în rest; pe iOS nu poate declanșa programatic dialogul. Chromium desktop, Safari Add to Dock și instalarea mobilă au comportamente diferite; nu se promite același flux pe toate browserele. [MDN — instalabilitate](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable).

**[PROPUNERE]** Contractul de lansare V1: Android Chrome, Safari pe iPhone/iPad și desktop Chrome/Edge; Safari macOS prin Add to Dock ca traseu manual. Alte combinații primesc site funcțional și instrucțiuni în limitele browserului, fără promisiune de instalare standalone universală. Se înregistrează OS și versiunea browserului în verificarea de acceptare; compatibilitatea se reverifică înainte de livrare.

Detectarea folosește `matchMedia('(display-mode: standalone)')` și, ca fallback iOS, `navigator.standalone`. Aceste semnale descriu fereastra curentă; un tab obișnuit nu poate confirma întotdeauna existența altei instalări. Absența `beforeinstallprompt` nu dovedește că AURA este deja instalată. `appinstalled` este un semnal oportunist, nu registru de instalări.

**Alegerea recomandată:** V1 cu SW minimal pentru offline public. O variantă V1 strict fără SW rămâne posibilă dacă proprietarul acceptă explicit că pagina offline personalizată se amână. Nu se adaugă un worker doar pentru o regulă de instalabilitate învechită.

### 6.2 Badge și Push

| Platformă | Instalare | Badge OS | Web Push și limitări |
| --- | --- | --- | --- |
| Android Chrome | Instalație promovată unde criteriile sunt îndeplinite; distribuția/launcherul contează | În documentația Chrome, Badging API nu controlează un număr arbitrar pe Android; launcherul poate arăta un punct pentru notificările de sistem | Permisiune, SW și serviciul Push al browserului; notificarea din sistem nu este echivalentul numărului inboxului |
| iOS/iPadOS | Adăugare pe Home Screen și standalone | API disponibil pentru Home Screen; vizibilitatea depinde de permisiunile de notificări/badge ale sistemului | Baza documentată: 16.4+ și aplicație pe Home Screen; cererea de permisiune trebuie să urmeze o acțiune explicită |
| Chrome/Edge desktop Windows/macOS | Instalare în browserele compatibile | Badging API unde este disponibil; OS poate modifica reprezentarea | Browserul/OS și configurarea fundalului decid dacă poate primi în momentul respectiv |
| Safari macOS | Add to Dock pe combinațiile suportate | Verificare pe OS/browser țintă, fără presupunerea comportamentului Chromium | Web Push standard pe Safari compatibil; test separat de instalare |
| Alte browsere / mod privat / restricții enterprise | Capabilități variabile | Fallback la contorul USER | Fără Push sau fără instalare unde API/politica nu permite; aplicația rămâne utilizabilă online |

Surse: [WebKit — Home Screen Web Push și badging](https://webkit.org/blog/13878/web-push-for-web-apps-on-ios-and-ipados/), [Chrome — Badging API](https://developer.chrome.com/docs/capabilities/web-apis/badging-api), [MDN — Push API](https://developer.mozilla.org/en-US/docs/Web/API/Push_API).

**Consecințe pentru produs:**

- V2 sincronizează contorul din server la deschidere, revenire în foreground, acțiuni de citire și polling moderat cât timp pagina este vizibilă. La zero apelează `clearAppBadge()` dacă API există. Lipsa suportului nu produce eroare utilizatorului.
- V2 nu garantează actualizarea iconiței când nu rulează nicio pagină. Un SW fără evenimente nu este un proces permanent, iar periodic background sync nu este un ceas garantat.
- Pe iOS, fără permisiunea OS, nu se promite badge vizibil. UI poate explica separat activarea indicatorului; nu cere permisiune pe landing sau automat la login. Politica acestei cereri se aprobă înaintea implementării.
- V3 poate actualiza badge la Push și poate declanșa reconciliere. Payloadul poate fi întârziat, duplicat sau livrat în altă ordine; nu se face `+1` ca sursă de adevăr.
- Nu se vor trimite Push-uri invizibile numai pentru menținerea unui contor: livrarea trebuie să respecte cerința platformelor de notificare vizibilă. Când dispozitivul A marchează citit, dispozitivul B închis poate păstra temporar un badge vechi până la următoarea sincronizare permisă.
- „Aplicația închisă” înseamnă că nu are fereastră deschisă, în limitele browserului/OS. Oprirea forțată, restricțiile de baterie, lipsa rețelei sau blocarea notificărilor pot împiedica ori întârzia livrarea. Nu se promit alarme exacte sau execuție nelimitată.

Acestea sunt limitări de acceptare, nu defecte care se rezolvă prin schimbarea bazei de date sau achiziția unui furnizor comercial.

## 7. Audit de securitate și politică recomandată

### 7.1 Riscuri existente relevante

**[COD]** Protecții bune: guard pe sesiuni, verificarea emailului, status activ, grup super admin, controlul proprietarului misiunii și protecții dedicate profil/parolă. Resetarea parolei setează `no-store, private` și `no-referrer` la `AuthController.php:230`; dovezile folosesc download privat, `nosniff` și `no-store` la `MissionController.php:84`. Codurile promoționale au răspunsuri cu `no-store` în `Admin/SubscriptionAdminController.php:253`, `:258`, `:288`.

**[COD]** Nu a fost identificată în codul aplicației o politică generală `Cache-Control: private, no-store` pentru toate răspunsurile USER/ADMIN. `bootstrap/app.php` nu adaugă CSP/HSTS sau o politică pentru SW. Aceasta nu dovedește lipsa antetelor la proxy/LIVE. Înainte de PWA trebuie inspectate antetele reale, inclusiv redirectări și răspunsuri de eroare. `no-store` HTTP nu împiedică un SW scris greșit să folosească explicit Cache API; ambele politici sunt necesare.

**[COD]** UI folosește scripturi inline, `onclick`, `innerHTML` și motoare demo cu localStorage. Unele funcții au escaping (`esc`), dar nu este suficient pentru a declara întreg frontend-ul sigur împotriva XSS. Introducerea de HTML arbitrar în mesajele reale ar transforma o problemă de randare într-un vector persistent de phishing/XSS. Nu se impune brusc o CSP care blochează aplicația existentă; întărirea CSP este un proiect distinct, cu inventariere și teste.

### 7.2 Matrice de riscuri și controale

| Risc | Severitate dacă apare | Măsuri obligatorii în arhitectura propusă |
| --- | --- | --- |
| Cache de pagini/dovezi/date personale | Critică | Nicio pagină autentificată, API JSON, HTML de login/reset/OTP sau document privat în Cache API/IndexedDB/localStorage; numai offline public permis |
| Refolosirea dispozitivului A → B | Critică | Inbox citit exclusiv din sesiunea curentă; revocare server Push la logout, identitate de asociere rotită, curățare badge și notificări AURA vizibile unde API permite; fără conținut personal în Push |
| Acces la inboxul altui cont | Critică | Query prin `$request->user()->notifications()`; 404 pentru ID străin; preferințe/dispozitive fără `user_id` acceptat din client |
| Abonament Push al altui utilizator | Critică | Un endpoint unic nu se transferă prin simplu upsert; dovadă de posesie, confirmare explicită, refuzul conflictului și reabonare controlată |
| URL Push folosit pentru SSRF | Critică | Endpoint HTTPS, fără userinfo/port arbitrar; furnizori validați, blocare localhost/IP private/link-local/metadate inclusiv IPv6 și DNS rebinding; fără redirecturi; aceleași verificări la expediere |
| URL de click neautorizat/phishing | Ridicată | Tip de țintă + ID validate pe server; rute named din allowlist USER; reautorizare la deschidere; SW verifică origine/schema/cale și respinge `/admin`, auth și scheme active |
| CSRF la abonare/citire/trimitere | Ridicată | Rute în stack web cu CSRF, mutații POST/PATCH/DELETE, `Accept: application/json` și token CSRF corect; nicio excepție nouă de CSRF |
| XSS persistent prin mesaje | Ridicată | Titlu/corp plain text cu limite; Blade `{{ }}` / DOM `textContent`; fără `eval`, scripturi, HTML editor sau URL introdus liber |
| Expunere VAPID/endpoints/auth Push | Critică | Cheia privată doar în secret store/env server; endpoint și auth criptate/ascunse; redactare în logs, failed_jobs și admin; niciun secret în manifest, JS sau prompturi |
| Spam / campanie lansată accidental | Ridicată | Doar super admin activ/verificat, preview cu număr de destinatari, confirmare POST, limite configurate, idempotency, audit și posibilitate de oprire |
| Mesaj după rollback comercial | Ridicată | Outbox în aceeași tranzacție cu sursa; expediere externă numai după commit; fără SMTP/Push în tranzacția comercială |
| Dublare / pierdere la retry | Ridicată | Chei unice durabile, procesare tranzacțională inbox/outbox, retry cu backoff; Push rămâne at-least-once, cu tag stabil pentru reducerea duplicatelor |
| Lipsa worker/scheduler / coadă blocată | Ridicată | Health operațional pentru vechimea pending/failed, workers supravegheați, timeout sub retry_after, retry controlat, fără etichetă „livrat” la simpla creare a jobului |
| Confuzie LOCAL/DEV/LIVE | Ridicată | Origini, DB, queue și VAPID separate, nume/icon DEV distinct, flags dezactivate implicit în afara mediului aprobat; fără endpoints de producție în fixture-uri |
| Root SW defect sau compromis | Critică | Cod mic, fără import extern, `/admin` pass-through, URL SW stabil, update controlat, kill switch și worker de retragere servit la același URL |
| Dependențe/fonturi cross-origin în offline | Medie | Offline autonom, font sistem, fără fetch generic de terți, fără cache opaque; core online funcționează când fontul extern e blocat |
| Contor stale sau atribuit altei identități | Medie/Ridicată | Număr și versiune autoritative din server, reset la schimbarea asocierii, niciun +1 local permanent, reconciliere la foreground |

### 7.3 Contractul Service Worker și cache

**[PROPUNERE]**

1. Un singur worker cu URL stabil `/sw.js`, pe aceeași origine. Se înregistrează din USER/public, niciodată din layoutul admin. Manifestul are `scope: '/'` deoarece rutele USER sunt deja la rădăcină; nu se mută rutele pentru PWA.
2. Acest scope nu poate exclude tehnic `/admin`. Handlerul de fetch lasă neatinse toate cererile admin, autentificare, reset, verificare, dovezi și metodele diferite de GET. Nu face cache și nu oferă offline în locul răspunsurilor lor.
3. Pentru navigări GET USER aprobate, încearcă rețeaua; numai la **eroare de transport** returnează pagina offline publică deja păstrată. Un răspuns real 401/403/404/419/500 sau redirect de login nu este înlocuit cu fallback offline și nu este stocat.
4. Allowlist inițială de navigare offline: `/`, `/dashboard`, `/opportunities` și detaliile sale numerice, `/projects` și detalii numerice, `/missions` și detalii numerice, `/referrals`, `/achievements`, `/wallet`, `/integrations`, `/subscriptions`, `/settings`. Se evită potriviri largi care includ dovezi sau acțiuni. Query stringul nu este niciodată cheie de cache pentru paginile online.
5. Singurul conținut Cache API din V1 este pagina offline cu stil inline și eventual iconițe publice declarate explicit. Fără shell HTML autentificat, precache al rutelor, stale-while-revalidate pentru navigare, replay de formulare sau background sync de tranzacții.
6. Prefix de cache AURA PWA + mediu + versiune. Cleanup numai pentru acest prefix; nu `caches.keys().map(delete)` global și nu `Clear-Site-Data` global care poate distruge sesiuni/alte aplicații.
7. Worker și manifest sunt serviți cu MIME corect, `Cache-Control: no-cache` sau `no-store` pentru worker, fără cache CDN de durată. Iconițele pot avea fișiere versionate. Dacă sunt servite de Laravel, antetele se testează acolo; configurația proxy rămâne prerechizită de lansare.
8. `skipWaiting()` nu se execută automat la fiecare update. UI anunță versiunea disponibilă; activare/reload numai după acțiunea utilizatorului și protejarea formularelor nesalvate. Prima instalare nu trebuie să forțeze reîncărcare. Fără reload automat la `controllerchange` în mijlocul unui formular.
9. Kill switch: oprirea noilor înregistrări nu scoate workerii deja instalați. Endpointul `/sw.js` trebuie să poată servi un worker de retragere valid, care curăță numai cache-urile AURA PWA și face `unregister()`; pagina client cooperează la actualizare. Controlul unui document deja deschis poate dura până la navigare/închidere.

Protocolul lifecycle/cache trebuie verificat cu două taburi și cu o versiune veche deja instalată. [MDN — utilizarea Service Workers](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API/Using_Service_Workers).

### 7.4 Dispozitive comune, logout și parole

PWA nu va conține parole, JWT, cookie de sesiune copiat ori token CSRF persistent în stocarea browserului. Nu cache-uiește răspunsul inboxului. În V2, logout încearcă eliminarea badge-ului și invalidează starea UI în toate taburile; serverul rămâne autoritatea chiar dacă JS nu rulează.

În V3, abonamentul se asociază atât contului, cât și unei instalări/asocieri active. Serverul păstrează o amprentă ireversibilă a sesiunii care a autorizat asocierea, fără sesiunea brută. Logout revocă toate asocierile acelei sesiuni **înainte** de invalidarea ei. Revocarea nu depinde de succesul `unsubscribe()` în browser și nu deloghează celelalte dispozitive.

Schimbarea parolei/resetarea și schimbarea emailului cu pierderea verificării trebuie să oprească autorizarea abonamentelor vechi. Pentru a evita modificarea tuturor fluxurilor comerciale, workerul verifică înainte de trimitere status/email verificat și o amprentă HMAC a stării de securitate salvată la asociere (parolă hash + email verificat/identitate; hashul parolei nu ajunge la client sau payload). Mismatch impune reactivare explicită. Nu se afirmă că simpla invalidare a sesiunii web anulează un abonament Push autonom.

O sesiune expirată nu autorizează citirea datelor, nici după un click Push. Propunerea conservatoare permite numai mesaje generice în fundal până la revocare/expirarea asocierii; termenul asocierii și politica pentru expirarea sesiunii trebuie aprobate. Dacă produsul cere stop la expirarea sesiunii, se adaugă verificarea validității sesiunii/lease-ului la trimitere, compatibilă cu driverul real; nu se inventează un driver nou.

Un mesaj deja predat serviciului Push sau afișat pe ecran nu poate fi rechemat garantat. De aceea payloadul implicit spune numai „Ai o notificare nouă în AURA”; nu nume, sold, sumă, proiect, document, motiv de respingere, OTP ori link de resetare. Se poate încerca închiderea notificărilor locale AURA la logout, fără a promite ștergerea istoricului OS pe toate platformele.

## 8. Arhitectura recomandată

### 8.1 Separarea responsabilităților

```text
Fluxuri existente (memento / alocare / review / finalizare)
  -> ProjectEvent persistat în tranzacția existentă
     -> observer mic: NotificationOutbox, aceeași conexiune/tranzacție

SUPER ADMIN: draft -> preview destinatari -> confirmare publicare
  -> campanie + outbox cu destinatari individuali fixați

Scheduler / queue, recuperare outbox pending
  -> motor unic, verificare eligibilitate + preferințe
     -> Laravel database notification (inbox USER)
        -> contor read/unread și badge oportunist (V2)
        -> livrări pe abonamente/dispozitive prin transport Web Push (V3)
           -> serviciu Push browser -> SW -> notificare OS
              -> click -> rută USER -> autentificare/autorizare normală
```

Outboxul nu este un al doilea inbox. El este evidența durabilă de procesare/recuperare, iar `notifications` este singura sursă a listei USER și a stării de citire. `project_events` și `subscription_events` își păstrează rolul de audit comercial.

### 8.2 Motorul intern V2

**[PROPUNERE]** Se folosesc mecanismele Laravel existente: `Notifiable`, canalul `database`, modelul framework `DatabaseNotification`, jobs/scheduler și controller-ele Blade. Nu se creează model propriu concurent de inbox, broker de mesaje, event bus extern ori motor generic de workflow.

- `UserInboxNotification` extinde `Notification`, `via()` întoarce numai `database`, `toDatabase()` livrează un payload versionat. Nu implementează `ShouldQueue`: jobul exterior gestionează tranzacția unică de materializare în inbox.
- `NotificationService` procesează un outbox blocat tranzacțional: verifică destinatarul/preferințele, construiește mesajul, scrie notificarea și marchează outboxul `recorded` în aceeași tranzacție. Are un `notification_id` UUID stabil rezervat la crearea outboxului. Codul instalat la `vendor/.../NotificationSender.php:155` păstrează ID-ul setat, iar `Channels/DatabaseChannel.php:31` îl scrie în payload; această posibilitate a fost verificată, nu presupusă. Un retry nu generează un ID nou.
- Outboxul are cheie unică `(source_key, user_id)` și stări durabile `pending`, `recorded`, `skipped`, `failed`; motivele skipped/failed sunt coduri fără secrete. Înaintea unui retry se verifică aceeași sursă/destinatar, nu se ignoră generic excepțiile de integritate.
- `ProjectEventObserver::created` înscrie numai tipurile aprobate în outbox, pe aceeași conexiune și în tranzacțiile deja prezente. Nicio cerere HTTP externă și niciun email în observer. După commit se poate programa jobul pentru latență mică; un scanner recurent al outboxului recuperează cazul crash după commit înainte de dispatch. Nu se folosește un cursor global peste ID-urile evenimentelor pentru a presupune ordinea commiturilor concurente.
- Tipuri inițiale propuse: `reminder_sent`, `mission_assigned`, `submission_reviewed`, `project_completed`. Destinatar: proprietarul proiectului, nu `actor_user_id`. Nu se dublează `mission_completed` și `project_completed` într-un singur traseu doar pentru că ambele există.
- Evenimente de abonament, marketing și modificări de termene fără tip de eveniment adecvat sunt extensii aprobate separat. Nu se inventează cron de expirare nou și nu se schimbă mecanismul de `MembershipService::closeExpired()`.
- Nicio importare automată a istoricului. Notificările încep de la activarea motorului; backfill-ul ultimelor mementouri este o decizie explicită. La migrarea dialogului se explică lipsa istoricului vechi sau se aprobă importul cu chei idempotente.

Documentația Laravel confirmă canalul database, relațiile read/unread, generarea tabelei și dispatch după commit. [Laravel 13 — Notifications](https://github.com/laravel/docs/blob/13.x/notifications.md), [Laravel 13 — Queues](https://github.com/laravel/docs/blob/13.x/queues.md).

### 8.3 Date noi propuse pentru V2

| Tabel | Câmpuri și constrângeri propuse | Motiv |
| --- | --- | --- |
| `notifications` | Schema Laravel: UUID PK, type, notifiable_type/id, data, read_at, timestamps; index compus pe notifiable_type/id/read_at/created_at | Inbox unic, căutări paginate și count fără încărcarea istoricului |
| `notification_preferences` | user_id FK unic, `mission_updates`, `announcements`, `badge_enabled`, `state_version` bigint, timestamps | Preferințe server și versiune monotonă a stării; în V3 se adaugă opțiuni Push |
| `notification_campaigns` | UUID, creator_user_id, titlu max 160, corp max 1000 plain text, tip țintă/id opționale, selector audiență validat, state, request_key unic, preparation_cursor/upper_user_id, preview_version, număr destinatari, momente creare/confirmare, timestamps | Draft, preview, confirmare, audit și reluare controlată; fără șabloane HTML sau editor de reguli |
| `notification_outbox` | id, user_id FK, source_key, source_type/id, campaign_id nullable, notification_id UUID unic, payload versionat, status, attempt_count, available_at, recorded_at, error_code, timestamps; unique source_key/user_id; index status/available_at/id și campaign/status | Destinatarii individuali ai campaniei + procesare durabilă a evenimentelor; înlocuiește nevoia unei tabele separate de campaign recipients |

Tabelul standard polimorf `notifications` nu are automat FK către users. Se documentează curățarea pentru ștergerea conturilor, dacă apare un asemenea flux. Migrarea trebuie să folosească tipul real al PK users, nu să presupună UUID. FK-urile noilor tabele se aleg pentru a permite retenția/auditul fără ștergerea evenimentelor comerciale.

Payloadul inboxului: `schema_version`, `category`, `title`, `body`, `target_type`, `target_id`, `source_key`, `campaign_id` opțional. Fără SQL, clase serializate ori HTML. `target_type` se mapează server-side la `aura.missions.show`, `aura.projects.show` sau `aura.dashboard`; nu se acceptă route name ori URL arbitrar din formular. La click se revalidează existența și dreptul de acces, chiar dacă mesajul era legitim la creare.

### 8.4 Campanii, preferințe și contor

**[PROPUNERE]** Audiențe V2 limitate: un utilizator explicit, listă explicită de ID-uri sau toți utilizatorii cu rol `user`, activi și verificați. Fără filtrare liberă SQL, segmente de venit, sponsorizare sau abonament. Pentru evenimente sistem, proprietarul activ/verificat primește mesajul și dacă utilizează zona USER cu rol super admin; această regulă se confirmă de produs.

Preview-ul materializează rândurile individuale din outbox într-o stare neexpediabilă sub campania draft. Pentru grupuri, procesul folosește loturi și o limită de audiență configurată; nu încarcă toți destinatarii în request. Selectarea se raportează ca snapshot la finalizarea pregătirii, nu ca o fotografie perfectă la începutul unui job lung. Preview-ul afișează numărul fixat. Orice editare invalidează preview-ul; confirmarea publică numai acel snapshot. Workerul revalidează statusul și preferințele la expediere; nu adaugă utilizatori înregistrați ulterior.

Stări de campanie: `draft` → `preparing` → `ready` → `published`, cu `cancelled` și `failed` ca rezultate explicite. Un job `PrepareNotificationCampaign` rezervă pe rând campania, procesează câte un lot și salvează cursorul în aceeași tranzacție cu rândurile outbox. `upper_user_id` se fixează la începutul pregătirii; eligibilitatea se evaluează la citirea fiecărui lot, iar lista devine imuabilă în `ready`. Confirmarea include `preview_version`, astfel încât o pagină veche de confirmare nu publică o audiență refăcută. Scannerul reia și pregătirile întrerupte. Editarea este permisă numai în draft/ready, invalidează versiunea și rândurile nepublicate; după published mesajul/audiența nu se rescriu. Aceste reguli fac reluarea deterministă fără a pretinde un snapshot tranzacțional al tuturor utilizatorilor pe durata unui job lung.

Pentru a evita două stări `pending` cu sensuri diferite, rândurile campaniei au `available_at = null` până la confirmare; scannerul procesează numai `pending` cu `available_at <= now()` și campanie confirmată. Anularea oprește rândurile încă neprocesate; nu șterge inboxurile deja create.

Mesajele sistem despre misiuni și anunțurile au opțiuni separate. Propunere de default pentru implementarea aprobabilă: misiuni interne active, anunțuri opționale dezactivate, badge intern activ; Push este mereu opt-in și nu este necesar pentru inbox. Retenția se aprobă; outboxurile procesate trebuie păstrate ca dovadă idempotentă cel puțin cât este posibil replay-ul sursei. Purjarea inboxului nu trebuie să permită recrearea mesajelor din sursele vechi.

Contorul se calculează din `unreadNotifications()->count()`, nu din `missionAlerts`, fixtures sau notificările OS. Fiecare creare/marcare/purjare relevantă incrementează `state_version` în aceeași tranzacție, serializată pe rândul de preferințe al utilizatorului. Endpointul count returnează `{unread_count, state_version}` dintr-o citire consistentă; clientul ignoră răspunsuri cu versiune mai veche pentru aceeași asociere de cont.

Polling propus: cel mult o cerere la 60 secunde cât pagina este vizibilă, plus foreground și mutații; backoff la erori/429. `BroadcastChannel`/storage event transmit numai invalidare între taburi, fără conținut privat. Nu se salvează răspunsul count în cache persistent. Marcarea tuturor ca citite este o mutație tranzacțională cu semantica „toate existente la procesarea acțiunii”, fără a înghiți notificări create ulterior.

### 8.5 Transportul V3 și noile date

**[PROPUNERE]** Un adaptor `WebPushTransport` izolează biblioteca `minishlink/web-push`, fără SDK comercial și fără al doilea model de notificări. Alegerea reduce dependența de integrarea unui pachet Laravel cu o versiune majoră nouă a frameworkului. Biblioteca gestionează protocolul/criptarea/VAPID; nu se implementează criptografie proprie. Instalarea ei necesită aprobare explicită și reverificarea versiunii/dependerilor la acel moment.

Documentația bibliotecii cere PHP 8.2+, curl, mbstring și OpenSSL cu EC; bcmath/gmp ajută performanța. [Web Push PHP — sursa proiectului](https://github.com/web-push-libs/web-push-php). PHP local 8.4 are curl/mbstring/openssl/bcmath, dar suportul efectiv EC, versiunile dependințelor și compatibilitatea LIVE nu au fost probate prin expediere. Nu se declară pachetul instalabil doar din lista de extensii.

| Tabel V3 | Câmpuri / reguli propuse |
| --- | --- |
| `push_subscriptions` | UUID, user_id FK, hash SHA-256 endpoint unic, endpoint/p256dh/auth criptate și hidden, encoding, VAPID key_id, binding_id aleator opac, amprentă sesiune HMAC, amprentă de securitate, status pending/active/revoked/expired, challenge_digest/expiry, expiration_time și last_seen/activated/revoked, timestamps; index user/status |
| `push_deliveries` | id, notification UUID, subscription UUID, binding snapshot, state pending/processing/accepted/failed/expired/skipped, attempt_count, next_attempt_at, lease_expires_at, response_code/error_code, accepted_at, timestamps; unique notification/subscription; index state/next_attempt_at/id |
| Preferințe V2 extinse | `push_enabled`, opțiuni Push pe categorii aprobate, eventual termenul asocierii; fără schimbarea preferințelor de email pentru securitatea contului |

`push_deliveries` reprezintă transmiterea către un endpoint. `accepted` înseamnă acceptat de serviciul Push, **nu afișat, citit sau primit garantat**. `read_at` rămâne exclusiv în inbox. Nu se trimit payloaduri personalizate din fixtures/admin.js.

Joburile serializează numai ID-uri de livrare, nu abonamente decriptate, chei VAPID sau obiecte de client HTTP. Adaptorul de transport transformă erorile bibliotecii în coduri/excepții redactate înainte ca ele să ajungă la logger ori `failed_jobs`, inclusiv fără un lanț `previous` care păstrează endpointul sau header-ele secrete. Criptarea coloanelor nu protejează un secret copiat ulterior în payloadul jobului sau în textul excepției.

La crearea inboxului, rândurile pending de livrare se creează durabil în aceeași tranzacție sau sunt recuperate de un scanner care caută inboxuri eligibile fără livrări; se alege prima variantă ca implementare inițială. Dispatchul jobului este după commit, scannerul recuperează întreruperile. Jobul revalidează statusul contului, preferința, asocierea, amprenta de securitate și endpointul înainte de fiecare încercare. Rebind/revocare între creare și execuție invalidează binding snapshot; nu se livrează către noul cont cu payloadul vechi.

Un job per livrare cu lease și retry controlat este suficient la început. Nu ține o tranzacție DB deschisă în timpul cererii HTTP Push. Crash după acceptarea Push dar înainte de salvarea rezultatului poate produce duplicat la retry; un `tag` stabil per notificare reduce afișările repetate, fără garanție exactly-once. Un worker cu timeout 60 secunde, retry_after 90 secunde și timeout HTTP mai mic este o configurație inițială de verificat, nu o schimbare globală obligatorie.

### 8.6 Abonare, dezabonare, chei și răspunsuri Push

1. Utilizator activ/verificat apasă explicit „Activează notificările pe acest dispozitiv”. Se verifică secure context, SW/PushManager/Notifications și modul Home Screen unde este necesar. Refuzul nu blochează AURA și nu este urmat de prompturi repetate.
2. Browserul creează abonamentul cu cheia **publică** VAPID. Serverul primește endpoint/chei prin POST cu CSRF, le validează, le salvează pending și le asociază exclusiv utilizatorului din sesiune. Nici user_id, nici status active nu vin autoritativ din payload.
3. Activarea necesită dovadă de posesie: challenge scurt aleator trimis prin Push criptat, cu notificare de activare vizibilă și generică, apoi confirmat din pagina autorizată de pe dispozitiv. Serverul stochează digestul și expirarea, verifică sesiunea/contul/challenge și activează o singură dată. SW poate furniza challenge-ul în memorie paginii prin `postMessage` verificat; dacă pagina s-a închis, utilizatorul reia activarea. Nu se păstrează tokenul de challenge persistent în browser.
4. Un endpoint activ al altui cont nu se reasignează. Se cere încheierea asocierii vechi și unsubscribe/resubscribe explicit pe dispozitiv; simpla posesie a unui string endpoint în cererea HTTP nu dovedește controlul browserului. Challenge-ul are limitare strictă pentru a nu deveni vector de spam.
5. Contul poate vedea și revoca dispozitivele proprii cu etichetă generică, fără endpoint/chei în răspuns. Dezactivarea locală revocă pe server și încearcă unsubscribe; „dezactivează pe toate dispozitivele” este o acțiune separată și confirmată.
6. La deschidere se compară abonamentul browserului cu asocierea serverului, permisiunea și key_id. Se reconciliază doar prin acțiuni autorizate. `pushsubscriptionchange` este un ajutor, nu singurul mecanism de recuperare.
7. 404/410 permanente dezactivează abonamentul; 429 respectă `Retry-After`; 5xx și erorile de rețea au backoff/jitter și limită de încercări/TTL; 400/401/403 se clasifică și alertează pentru configurație/VAPID, fără a șterge în masă abonamentele sănătoase. Challenge și testele admin au limite distincte.
8. VAPID: pereche separată per mediu, contact public valid, cheia privată numai server-side, key_id în abonament. Rotația normală menține vechea cheie pentru abonamentele vechi pe perioada de tranziție și cere reabonare controlată; incidentul de compromitere poate necesita revocare imediată. Nici rollback-ul, nici deployul nu generează automat altă cheie.

Payloadul Push conține o notificare generică, un ID opac de notificare, binding_id și `state_version`/count numai dacă validarea asocierii permite. Binding-ul din stocarea workerului este opac, fără user_id, nume sau token de autentificare. Dacă asocierea nu poate fi confirmată ori payloadul este vechi, workerul nu afișează conținut personal și nu aplică un count unei identități noi; sincronizează la următoarea deschidere autorizată.

`notificationclick` închide notificarea și deschide/focalizează numai o țintă USER sigură. Folosește un endpoint al inboxului care rezolvă ținta după autentificare; la sesiune expirată se păstrează numai destinația internă sigură pentru după login. Nu marchează automat mesajul citit doar pentru că OS l-a afișat. Închiderea lui din centrul OS nu este read receipt. [MDN — afișarea notificărilor prin Service Worker](https://developer.mozilla.org/en-US/docs/Web/API/ServiceWorkerRegistration/showNotification).

## 9. Plan PWA V1 — aplicație instalabilă

### 9.1 Obiectiv, limite și prerechizite

Instalare AURA USER cu pictogramă aprobată, standalone, buton „Instalează AURA”, fallback manual, detectare contextuală și actualizare fără reinstalare. Varianta recomandată include offline public minimal și update controlat. Nu are inbox nou, Push, migrări, pachete sau schimbări comerciale.

Prerechizite pentru lansare: origine canonică HTTPS, brand/iconițe aprobate, matricea minimă de browsere acceptată, verificarea antetelor și posibilitatea de a servi `/sw.js` la același URL după rollback. Implementarea locală nu autorizează acces/deploy pe Hetzner.

### 9.2 Fișiere și interfețe propuse

| Tip | Fișiere / modificare permisă |
| --- | --- |
| Existente | `resources/views/layouts/app.blade.php`: include head și bootstrap independente de Vite/demo; `routes/aura-demo.php`: numai rute publice PWA; `resources/views/profile/settings.blade.php`: control instalare/instrucțiuni; `bootstrap/app.php`: exclusiv atașarea middleware-ului de antete, fără schimbarea autentificării |
| Noi | `config/pwa.php`; `app/Http/Controllers/PwaController.php`; `app/Http/Middleware/PreventPrivateResponseCaching.php`; `resources/views/components/aura/pwa-head.blade.php`; `resources/views/components/aura/pwa-install.blade.php`; `public/assets/aura/pwa.js`; `resources/pwa/sw.js`; `public/offline.html`; `public/assets/aura/pwa/icon-192.png`, `icon-512.png`, `icon-maskable-192.png`, `icon-maskable-512.png`, `apple-touch-icon.png`; `tests/Feature/PwaInstallationTest.php` |
| Rutare nouă | GET `/manifest.webmanifest` → `aura.pwa.manifest`; GET `/sw.js` → `aura.pwa.worker`; workerul este servit din resources prin controller cu MIME/antete explicite, fără dependență de setările unui fișier static la proxy |
| Bază de date | Nicio migrare și nicio tabelă nouă |
| Dependențe | Niciuna; browser APIs și framework existent |
| În afara etapei | Layout admin fără instalație separată; niciun refactor Vite/CSS global, nicio schimbare de route names comerciale, auth, guards, sesiuni, emailuri sau cote |

Middleware-ul nou setează `private, no-store` pentru răspunsuri autentificate și pentru căi sensibile auth/admin/dovezi, inclusiv redirecturi/erori relevante; nu modifică cererea, sesiunea, CSRF sau ordinea middleware-urilor de autentificare. Se testează că nu strică descărcările ori JSON. Este o completare a antetelor, nu un nou sistem de acces.

`config/pwa.php` propus: enabled, worker_enabled, worker_retirement, nume/culori aprobate, environment_label, cache_version. Flags dezactivate implicit până la configurarea mediului. Manifestul are `id: '/'` stabil pe fiecare origine, `name`, `short_name`, `lang: 'ro'`, `display: 'standalone'`, `start_url: '/dashboard'`, `scope: '/'`, background/theme colors și iconițele aprobate. Nu conține ID utilizator, referral, cupon sau query personalizat. Numele DEV diferă, dar nu se schimbă identitatea aplicației la fiecare release.

Metadatele/bootstrapul pot fi pe paginile USER/public, iar CTA-ul de instalare este accesibil în Setări; pe browser fără eveniment se deschid instrucțiunile. Pagina offline are doar explicație și „Reîncearcă”, fără nume, sume, liste sau stare falsă de succes. Fără coadă offline pentru formulare.

### 9.3 Ordinea exactă

1. Reverifică regulile, versiunile și fișierele din inventar; verifică dacă între timp există manifest/SW. Confirmă configurația de mediu/brand aprobată; fără aceasta păstrează flags off.
2. Creează configurația și răspunsurile manifest/SW; construiește iconițele din sursa grafică aprobată. Testează MIME, dimensiuni și lipsa datelor personale.
3. Adaugă metadatele comune și scriptul independent de ramurile Vite/demo. Adaugă controlul din Setări și fallbackurile de instalare. Nu solicita notificări.
4. Adaugă middleware-ul de antete, fără a modifica middleware-urile de acces; verifică protecția răspunsurilor auth/admin/download.
5. Implementează offline-ul și SW conform contractului din §7.3; precache numai fișierul public aprobat, verificând status/MIME înainte de stocare.
6. Implementează update waiting + acceptare explicită, protecția formularelor și mecanismul de retragere. Testează mai întâi fără date de cont.
7. Rulează testele înguste și regresiile de autentificare pe infrastructură de test izolată, apoi verificările manuale pe dispozitivele țintă.
8. Predă schimbarea locală, rezultatele, limitele și planul de lansare. Niciun deploy sau server accesat fără instrucțiune distinctă.

### 9.4 Acceptare și teste

**Funcționale:** manifest valid și identitate stabilă; iconițe fără crop neintenționat; instalare/relaunch standalone pe Android Chrome, Safari iOS/iPadOS și desktop Chrome/Edge; fallback manual Safari; anularea instalării nu blochează pagina; modul instalat ascunde CTA unde poate fi detectat; update fără reinstalare; offline generic după cel puțin o inițializare online reușită; revenirea la online permite retry normal. Prima vizită complet offline nu poate instala aplicația sau descărca fallbackul și nu este criteriu de succes.

**Securitate:** inspectează Cache Storage/IndexedDB/localStorage după login, proiect, dovadă, logout și login B: niciun conținut nou privat; cache numai offline/iconițe permise; `/admin`, auth, dovezi și mutații bypass; 403/419/500 nu devin răspuns offline 200; workerul/manifestul nu expun secrete; reset/dovezi păstrează `no-store`; root scope explicat și verificat.

**Regresie:** login USER/admin, OTP, logout, sesiune expirată, profil/parolă, acceptare/start proiect, reminder, dovadă/upload, trial/cupon. Folosește suitele Auth/Security și `ProjectWorkflowTest` pe DB de test, fără operațiuni comerciale reale. Verifică ambele ramuri Vite numai dacă build-ul și pachetele există; nu declara testată o ramură care nu poate fi construită. Verifică viewport, tastatură, safe areas și navigare externă pe dispozitive reale.

**Update/rollback:** două ferestre, formular nesalvat, offline în timpul update-ului, worker vechi, worker nou și retragere. Acceptarea update-ului nu pierde formularul fără avertizare. Iconița existentă continuă să deschidă online AURA după retragerea workerului.

### 9.5 Rollback, complexitate și aprobări

Oprește CTA/noile înregistrări; servește workerul de retragere la **același** `/sw.js`; actualizează înregistrarea clienților disponibili; curăță doar cache-urile cu prefixul AURA PWA; unregister și reîncărcare controlată. Păstrează endpointul valid pentru clienții vechi și manifestul compatibil. Nu rezolva rollback-ul prin ștergerea `/sw.js`, golirea tuturor cache-urilor, `migrate:rollback` sau reinstalarea forțată. Clienții offline se retrag când revin și verifică update; intervalul nu este garantat.

**Complexitate estimată: mică spre medie.** Manifestul/CTA sunt mici; verificările pe dispozitive și lifecycle SW reprezintă cea mai mare parte a riscului. Nu este o estimare în zile fără acces la matricea țintă.

**De aprobat:** setul grafic și numele oficial, browserele/OS minime, includerea offline/SW în V1, poziția CTA, originea finală și operarea antetelor/rollbackului. V1 fără SW elimină fișierul worker/ruta/lifecycle, dar amână offline-ul; nu este echivalent cu varianta completă de mai sus.

## 10. Plan PWA V2 — notificări interne și badge

### 10.1 Obiectiv și independență

Inbox persistent, mesaj individual/grup, evenimente automate aprobate, read/unread, preferințe, istoric, administrare exclusiv SUPER ADMIN și badge oportunist. Funcționează în browser obișnuit fără instalare, fără VAPID și fără transport Push. Instalarea V1 este necesară numai pentru experiența de iconiță instalată, nu pentru inbox.

Prerechizite: aprobarea regulilor de audiență/categorii/retentie, schema reală inspectată prin Boost, DB de test separată și decizie de operare scheduler/worker. Dacă V1 există, se reverifică faptul că noile răspunsuri inbox nu intră în cache-ul SW.

### 10.2 Fișiere și rute

| Tip | Fișiere / modificări |
| --- | --- |
| Existente USER | `layouts/project.blade.php` și header-ele din `profile/settings`, `subscriptions/index`, `wallet/index`, `achievements/index`, `integrations/index`; `profile/index` numai dacă este încă folosit (ruta actuală `/profile` redirecționează); `layouts/app.blade.php` pentru bootstrap; toate sunt `.blade.php` în `resources/views` |
| Existente server | `AppServiceProvider.php`: înlocuiește composerul missionAlerts doar la activare și înregistrează observer/rate limiters/componente; `routes/aura-demo.php`: rute USER noi în grupul existent; `routes/admin.php`: înlocuiește rutele demo numai pentru notifications și delivery-log, eliminând coliziunea cu record demo; `routes/console.php`: scanner outbox |
| Existente admin | `admin/pages/notifications.blade.php`, `admin/pages/delivery-log.blade.php`, `admin/layouts/app.blade.php`: real route mapping și guard pentru DOM demo; celelalte taburi păstrează eticheta demo dacă nu se implementează |
| Noi server | `config/notifications.php`; `app/Notifications/UserInboxNotification.php`; `app/Services/NotificationService.php`; `app/Services/NotificationCampaignService.php`; `app/Observers/ProjectEventObserver.php`; modele `NotificationPreference`, `NotificationCampaign`, `NotificationOutbox`; jobs `ProcessNotificationOutbox`, `PrepareNotificationCampaign`; comandă `ProcessNotificationOutboxCommand`; controllers `NotificationController`, `NotificationPreferenceController`, `Admin/NotificationCampaignController`; Form Requests pentru preferințe și draft/preview/publish |
| Noi frontend | `resources/views/notifications/index.blade.php`; `resources/views/components/aura/notification-bell.blade.php`; `resources/views/admin/pages/notification-form.blade.php`; `public/assets/aura/notifications.js`; `public/assets/admin/notifications.js`; CSS strict local dacă necesar |
| Date/teste | Migrări numai pentru cele patru tabele §8.3; factories utile pentru modelele proprii; `tests/Feature/NotificationCenterTest.php`, `NotificationCampaignTest.php`, `NotificationEventIntegrationTest.php`, `NotificationSecurityTest.php` |

Nu se schimbă `MissionController::reminder`, schema mementourilor, `ProjectEnrollment::event`, regulile MissionProgress sau MembershipService. Integrarea automată este prin observer la jurnalul existent; se testează că tranzacțiile curente înscriu sursa și outboxul atomic.

**Contract de endpointuri propus:**

| Metodă/cale | Nume / responsabilitate |
| --- | --- |
| GET `/notifications` | `aura.notifications` — inbox paginat, 20/rânduri per pagină, filtre citite/necitite validate |
| GET `/notifications/count` | `aura.notifications.count` — număr/versiune; înaintea rutei UUID |
| PATCH `/notifications/{notification}/read` | `aura.notifications.read` — mark-read idempotent, numai proprietar |
| PATCH `/notifications/read-all` | `aura.notifications.read-all` — înaintea rutei dinamice, semantică tranzacțională |
| GET `/notifications/{notification}/open` | `aura.notifications.open` — rezolvă ținta și autorizează, fără mutație de citire prin GET |
| PATCH `/settings/notifications` | `aura.notifications.preferences` — numai preferințele contului curent |
| GET `/admin/notifications` | `aura.admin.notifications` — campanii reale, înlocuiește demo existent |
| GET `/admin/notifications/create`, POST `/admin/notifications` | `aura.admin.notifications.create` / `aura.admin.notifications.store` — formular și creare draft |
| PATCH `/admin/notifications/{campaign}` | `aura.admin.notifications.update` — editare numai draft |
| POST `/admin/notifications/{campaign}/preview` | `aura.admin.notifications.preview` — fixează audiența, fără livrare |
| POST `/admin/notifications/{campaign}/publish` | `aura.admin.notifications.publish` — confirmare snapshot, idempotentă |
| POST `/admin/notifications/{campaign}/cancel` | `aura.admin.notifications.cancel` — oprește numai pending |
| GET `/admin/delivery-log` | `aura.admin.delivery-log` — stări interne reale, fără a pretinde Push livrat |

Pentru formularul de creare se folosește GET `/admin/notifications/create`; POST de creare merge exclusiv la `/admin/notifications`. Nu este necesar un POST suplimentar pe `/create`. Toate rutele admin noi stau în grupul super_admin existent; nicio excepție guest și niciun guard separat. Mutațiile au CSRF și limitare per cont/IP; count are limitare compatibilă cu polling-ul.

### 10.3 Ordinea exactă

1. Confirmă contractele de produs și schema/versionarea curente; identifică orice implementare nouă apărută de la audit. Nu instala dependențe și nu aplica migrări pe DB utilizată de proiect.
2. Generează migrările și modelele proprii cu Artisan, după convențiile proiectului; adaugă indexuri, chei unice și factories. Nu modifica migrările vechi.
3. Implementează `UserInboxNotification`, outboxul și procesarea tranzacțională idempotentă. Configurează scannerul și queue dedicată logic pentru notificări pe driverul existent; flags off până la validare.
4. Adaugă observerul numai pentru tipurile aprobate și testează commit/rollback/retry. Nu prelua automat istoricul și nu interfera cu actualizarea termenelor.
5. Implementează controller-ele USER, read/count/open/preferences și matricea de acces. Serializează version/count și validează țintele intern.
6. Integrează componenta clopoțel/inbox în toate header-ele active; la flag off rămâne vechiul comportament. La flag on se elimină doar sursa paralelă missionAlerts și mesajele demo de notificări.
7. Implementează campania draft/preview/publish/cancel, rânduri destinatar în outbox, snapshot și reluarea loturilor. Înlocuiește rutele demo selectate și protejează ecranele noi de engine-ul JS demo. Etichetează celelalte taburi corect.
8. Adaugă polling/backoff, invalidare între taburi și badge feature-detected cu tratarea erorilor. Nu cere abonare Push. Implementează politica de permisiune badge iOS doar după aprobare.
9. Implementează jurnalul operațional și retenția aprobată, păstrând dovada idempotentă. Rulează testele/regresiile izolate, apoi verificarea pe dispozitive.
10. Predă planul operatorului pentru migrări/worker/scheduler, activare graduală și dezactivare; auditul nu autorizează executarea lui pe LIVE.

### 10.4 Acceptare și teste

**Funcționale:** notificare individuală vizibilă numai destinatarului; grupul primește un singur mesaj per persoană eligibilă; conturile noi după snapshot nu sunt adăugate; preview/edit/confirmare/cancel au stări clare; eveniment de memento/review produce exact un inbox; preferințele persistă pe mai multe sesiuni; read/unread și read-all sunt idempotente; lista paginată nu are mesaje demonstrative; count=0 golește indicatorul; UI explică limitările badge-ului fără a bloca inboxul.

**Securitate:** guest, neverificat, suspendat/disabled, user și super_admin în matrice; ID străin în URL/payload refuzat; CSRF verificat cu protecția activată; titlu/body cu HTML afișate ca text; sort/filter injectabil respins; route/target extern ori admin respins; preferințele nu pot schimba user_id/status/role; rate limit preview/publish/count; logs/job payload fără tokenuri de cont.

**Fiabilitate:** două joburi pentru același outbox → un singur inbox; rollbackul evenimentului → fără outbox/inbox; crash între commit și dispatch → scannerul recuperează; crash înainte de commit inbox → retry sigur; utilizator suspendat sau opt-out între preview și livrare → skipped; edit după preview → reconfirmare; retry/purge nu readuce mesaje vechi; răspunsuri count inversate nu restaurează un badge mai vechi; două taburi/acțiuni concurente mențin count corect. Test de concurență separat pe motorul țintă când SQLite nu dovedește lockurile reale.

**Regresie:** `ProjectWorkflowTest` pentru reminder/review/termene, toate suitele Security/Auth relevante, testele infrastructurii de abonament și cupoanelor pe date de test. Nicio diferență a cotelor, accesului, periodicității mementourilor sau rezultatului comercial. Verifică logout A → login B și `/notifications/open` după expirarea sesiunii.

### 10.5 Operare, rollback și aprobări

Queue inițială poate folosi tabelele database deja declarate; nu se cere Redis/Horizon obligatoriu. Workerul trebuie să consume explicit queue-urile noi; configurația existentă nu dovedește acest lucru. Scannerul procesează loturi mici și poate rula și fără worker ca recuperare limitată, cu contract clar de latență. Monitorizare: vechime pending, failed/skipped per cod, durată materializare, count și latență query; fără conținutul mesajelor sau endpointuri în loguri.

Rollback: oprește publicarea și observerul prin flags, oprește/izolează joburile acestui modul, lasă schema nouă pe loc și păstrează datele pentru reluare. Dezactivează UI/count/badge și curăță badge-ul oportunist; revino la dialogul de mementouri numai când flag-ul este off. Nu afișa simultan două inboxuri. Preferă rollback de cod compatibil cu schema aditivă; nu executa `migrate:rollback` general care poate șterge tabele comerciale. Dropul numai al tabelelor noi necesită backup și o decizie explicită după oprirea tuturor cititorilor/workerilor.

**Complexitate: medie spre ridicată.** Partea grea este contractul de livrare, snapshotul audienței și eliminarea surselor demo paralele, nu clopoțelul.

**De aprobat:** tipuri automate, destinatari super admin în zona USER, categoriile/defaulturile, audiențele de grup, limite volum, SLA de latență, retenție (de exemplu 90 zile este doar o opțiune), import istoric, semantica anulării și cererea de permisiune iOS pentru badge. Până la aprobare motorul se livrează dezactivat; agentul nu alege politici comerciale implicit.

## 11. Plan PWA V3 — Web Push

### 11.1 Obiectiv, prerechizite și domeniu

Extinderea notificărilor interne prin livrare Web Push opt-in pe mai multe dispozitive, inclusiv fără fereastră deschisă în limitele platformei. V3 nu creează alt inbox și nu înlocuiește emailurile de securitate.

Prerechizite: V2 validat cu outbox/read/unread/preferințe și V1 cu worker activ sau adăugarea aprobată a workerului minimal dacă V1 fusese fără SW; HTTPS; mediu de test cu VAPID separat; aprobare pentru noua dependență; worker/scheduler/egress monitorizate. Se reverifică schema, library APIs, stările V2, logoutul efectiv, antetele SW și limitările browserelor la momentul implementării.

### 11.2 Fișiere, endpointuri și dependențe

| Tip | Fișiere / modificări |
| --- | --- |
| Existente după V1/V2 | `resources/pwa/sw.js`: push/click/subscriptionchange fără lărgirea cache-ului; `public/assets/aura/pwa.js` și `notifications.js`: reconciliere; `profile/settings.blade.php`: consimțământ și dispozitive; `routes/aura-demo.php`, `routes/console.php`, `AppServiceProvider.php`; `NotificationService`/preferințe pentru fan-out durabil; jurnalul admin pentru stări Push reale |
| Hook limitat existent | `AuthController::logout`: revocă asocierea sesiunii înainte de logout, fără schimbarea invalidării/regenerării CSRF; fără modificarea LoginAttempts sau a regulilor de parolă |
| Noi | `config/webpush.php`; `app/Models/PushSubscription.php`, `PushDelivery.php`; `app/Contracts/WebPushTransport.php`; `app/Services/StandardWebPushTransport.php`, `PushSubscriptionService.php`; `app/Http/Controllers/PushSubscriptionController.php`; Form Requests de register/confirm/revoke; `app/Jobs/SendWebPushDelivery.php`; `app/Console/Commands/ProcessPushDeliveriesCommand.php`; factories și teste de abonare/livrare/izolare |
| Migrări noi | `push_subscriptions`, `push_deliveries`, câmpurile Push din preferințe; fără modificări în tabele comerciale |
| Dependențe | `minishlink/web-push`, versiune compatibilă confirmată și aprobată; schimbări Composer/lock doar după aprobare; fără SDK NPM obligatoriu |
| Secrete/config | VAPID public/private/subject/key_id, flags și limitări; numai nume de variabile în `.env.example` dacă modificarea este autorizată ulterior; niciun secret real în fișiere versionate |

Endpointuri propuse în grupul USER existent: GET `/settings/push/devices` pentru lista proprie; GET `/push/public-key` pentru cheia publică/key_id; POST `/push/subscriptions` register pending; POST `/push/subscriptions/{subscription}/confirm`; DELETE `/push/subscriptions/{subscription}` revoke; POST `/push/subscriptions/revoke-all` cu confirmare. Nume `aura.push.*`, UUID validat, CSRF la toate mutațiile, rate limit separat pentru register/challenge/confirm/revoke. Nu există endpoint public „trimite push către endpoint” și nici opțiune USER de a ținti alt cont.

### 11.3 Ordinea exactă

1. Reinspectează rezultatul V1/V2, contractul logout și schema reală. Confirmă deciziile de privacy, perioada asocierii și politica expirării sesiunii. Verifică biblioteca propusă și aprobarea dependenței înainte de instalare.
2. Adaugă config/secrets interface și feature flags off. Pregătește rotația key_id și verifică EC/TLS/egress în mediul de test, fără trimitere către utilizatori reali.
3. Creează migrări aditive, modele cu encryption/hidden și validatoare endpoint/SSRF. Testează chiar validatorul utilizat la expediere, inclusiv redirect/DNS/IP private și IPv6.
4. Implementează register pending → challenge vizibil → confirm; conflict între conturi, listă și revocare. Niciun endpoint activ fără consimțământ și posesie confirmate.
5. Adaugă UI de activare/dezactivare/dispozitive; verifică refuz/revocare în OS și reconciliere la foreground. Nu cere permisiune automat.
6. Implementează revocarea server la logout și amprenta stării de securitate. Testează A → logout → B, schimbare parolă/reset/email și suspendare înainte de orice transport real.
7. Creează livrările durabile din inbox și jobul de transport, lease/retry, expirarea TTL și tratarea diferențiată a răspunsurilor; fără tranzacție DB deschisă în HTTP. Integrează scannerul de recuperare.
8. Extinde SW cu push/click și asociere opacă; notificări generice, ținte allowlist, tag stabil, version/count fără increment arbitrar. Update-ul nu forțează închiderea formularului.
9. Extinde jurnalul real cu accepted/failed/expired/skipped și metrici redactate; adaugă kill switch pentru trimitere și revocare.
10. Rulează teste cu transport fake, apoi probe controlate pe dispozitive de test pentru foreground/closed/offline/permisiuni. Predă runbookul de lansare/rollback pentru aprobare distinctă; fără acces automat la LIVE.

### 11.4 Acceptare și teste

**Funcționale:** un utilizator cu două dispozitive primește pe ambele dacă sunt active; dezactivarea unuia nu îl oprește pe celălalt; permisiune default/denied/granted tratată corect; iOS Home Screen verificat; click din closed deschide ținta după login dacă este necesar; refuzul sau Push indisponibil nu afectează inboxul; read în UI actualizează count și badge oportunist; 404/410 scot abonamentul din trimitere; notificări generice pentru procesare în foreground și closed, fără dublarea necontrolată a toastului și notificării OS.

**Securitate:** IDOR și `user_id` fals; endpoint al altui cont fără transfer; challenge greșit/expirat/reutilizat; CSRF absent; SSRF în register și la send, localhost/127.0.0.1/IPv6/metadate/DNS privat/redirect; chei invalide/supradimensionate; payload cu route extern/admin/javascript; logout și rebind în timp ce există job pending; mesaj deja acceptat după logout rămâne generic; VAPID private/endpoint/auth nu apar în HTML, JSON, logs sau failed jobs. Testele nu trimit către servicii reale decât în proba explicită cu dispozitiv de test.

**Fiabilitate:** dublu dispatch, retry după acceptare, job concurent/lease expirat, răspunsuri Push în altă ordine, 429 cu Retry-After, 5xx, timeout, cheie VAPID greșită, queue oprită și recuperare; eventualele duplicate nu dublează inboxul; un mesaj citit sau expirat înainte de send poate fi skipped conform politicii aprobate; count vechi nu suprascrie o versiune mai nouă; rotația cheilor nu distruge toate abonamentele.

**Regresie:** toate criteriile V1 de cache/update, V2 de inbox/preferințe/snapshot și suitele Auth/Security/ProjectWorkflow/SubscriptionInfrastructure relevante. Special: resetările de parolă și emailurile de cont nu migrează în queue-ul Push și nu își pierd protecțiile.

Pentru unitatea de transport se injectează un fake al `WebPushTransport`. `Http::fake()` singur nu interceptează automat clientul HTTP intern al bibliotecii PHP; testele trebuie să demonstreze că nu scapă cereri reale. Testele manuale consemnează OS/browser, permisiuni, stare instalată, rețea, fereastră și rezultat observat.

### 11.5 Operare, rollback, complexitate și aprobări

Monitorizează vechimea livrărilor pending, rata accepted/expired/failed, răspunsurile 401/403/429, abonamentele inactive, costul/volumul per campanie și expirarea joburilor. Logs numai ID-uri opace și coduri, fără endpoint complet sau payload. Queue database este suficientă pentru un volum inițial măsurat; Redis/Horizon se consideră numai dacă metricile justifică și proprietarul aprobă.

Rollback: `webpush.enabled=false` oprește producerii și **workerii verifică flag-ul înainte de fiecare send**; oprește/reține numai joburile modulului, fără queue:clear global. V2 rămâne funcțional. Servește o versiune compatibilă de SW fără efectele Push dacă se retrage această funcție; dacă se retrage întreg PWA, aplică procedura V1. Revocă pe server abonamentele dacă incidentul o cere; păstrează tabelele și VAPID în siguranță pentru diagnostic/revenire. Nu roti cheile din reflex și nu aplica down migrations pe date active. Dacă sunt necesare down migrations, backup/oprire completă și aprobare distinctă pentru pierderea datelor.

**Complexitate: ridicată.** Protocolul este standard, dar asocierea cont/dispozitiv, retrierile, privacy și acceptarea multiplatformă necesită verificare atentă.

**De aprobat:** biblioteca/dependințele, praguri/volume/TTL, conținut lock screen (generic recomandat), perioada asocierii și expirarea sesiunii, păstrarea livrărilor/subscriptions, politica skip pentru mesaje citite, browserele minime, consimțământul și UI de challenge, administrarea VAPID și responsabilul de operare. Fără aprobări, se pregătesc codul și testele cu flags off; nu se inventează valori de produs și nu se expediază către conturi reale.

## 12. Decizii deschise și porți de lansare

Toate valorile următoare sunt **recomandări de aprobat**, nu politici AURA deja stabilite. Codul ulterior poate fi pregătit cu configurare și flags off, dar acceptarea/lansarea nu se declară completă dacă decizia esențială lipsește.

| ID | Decizie | Recomandare de pornire | Etapă |
| --- | --- | --- | --- |
| D1 | Aplicație USER sau instalație separată ADMIN | O singură instalație USER; ADMIN online, fără invitație de instalare și fără cache | V1 |
| D2 | Offline în prima livrare | Da, numai pagină publică; SW mic, fără cache de date | V1 |
| D3 | Brand, iconițe, URL și suport minim | Aprobarea logo-ului și PNG-urilor; origine HTTPS separată per mediu; matricea §6.1 | V1 |
| D4 | Amplasarea instalării/update-ului | Control în Setări, mesaj discret update cu acceptare explicită | V1 |
| D5 | Categorii automate și istoric | Patru tipuri ProjectEvent din §8.2, fără backfill; nu notificare pentru fiecare pas și fiecare eveniment comercial | V2 |
| D6 | Preferințe și audiențe | Misiuni interne on, anunțuri off, grupuri limitate la user activ/verificat sau listă explicită; fără filtre comerciale | V2 |
| D7 | Retenție, volum, latență | Pragurile se dimensionează după volume reale; polling 60 secunde vizibil este propunere; retenția nu este aleasă în audit | V2/V3 |
| D8 | Badge pe iOS și exactitatea în fundal | Feature detection și consimțământ explicat; acceptare a diferențelor OS și a sincronizării oportuniste | V2/V3 |
| D9 | Furnizor/transport Push | `minishlink/web-push`, standarde; fără SaaS obligatoriu; dependență numai cu aprobare | V3 |
| D10 | Confidențialitate lock screen | Mesaj generic; detalii numai după autorizare în inbox | V3 |
| D11 | Durata asocierii, logout, expirarea sesiunii | Revocare la logout explicit și schimbări de securitate; politică de lease/expirare aprobată înainte de activare | V3 |
| D12 | Responsabil operațional | Persoană/echipă pentru worker, scheduler, backup, VAPID, incident și rollback SW | Toate |

**Ordine de livrare:** V1 poate fi lansată fără V2/V3. V2 se poate livra fără instalare și fără Push; badge-ul OS rămâne condițional. V3 consumă contractul V2 și un SW verificat. V1 nu trebuie să includă „pregătiri” de DB pentru etapele următoare.

**Go/no-go de securitate:** niciun cache privat; nicio citire între conturi; control server pentru SUPER ADMIN; nicio reatribuire Push neconfirmată; zero secrete în frontend/loguri; retry idempotent intern; kill switch și rollback SW probate; teste autentificare/comercial fără regresie. O demonstrație UI cu notificare simulată nu îndeplinește aceste porți.

## 13. Prompturi de implementare

Blocurile de mai jos sunt pentru execuții viitoare, independente. **Nu reprezintă autorizare pentru implementare în timpul acestui audit.** Fiecare delimitează modificările, prerechizitele și verificările. Dacă rezultatul etapelor precedente diferă de numele propuse, agentul trebuie să mapeze explicit implementarea existentă la contract, fără a crea un sistem paralel.

### 13.1 Prompt complet — implementare PWA V1

```text
Lucrezi în proiectul Laravel AURA V2. Implementează numai PWA V1: instalare USER, standalone, control „Instalează AURA”, instrucțiuni alternative, detectare contextuală, offline public și actualizare controlată. Nu implementa notificări interne, Push sau badge.

Fundație verificată la audit: PHP local 8.4.27, Laravel 13.42.0; USER este Blade multipage. Layout comun resources/views/layouts/app.blade.php cu ramuri Vite/statice; resources/js/app.js este gol și nu este bootstrapul funcțional al paginilor curente. Vite input are numai resources/css/app.css și resources/js/app.js. ADMIN folosește alt layout. Nu există manifest/SW în sursele auditate. public/favicon.ico este gol. Autentificare prin guard web/session, USER auth+active+verified, ADMIN în plus super_admin; nu schimba aceste contracte.

Înainte de editare:
1. Citește AGENTS.md, .ai/rules/index.md și regulile aplicabile dacă directorul există; caută și după pwa/cache/session/security. Activează skillurile relevante, inclusiv Laravel și testing, iar pentru UI/stilizare skillul frontend aplicabil.
2. Reconfirmă versiunile Composer și package.json, starea Git și existența oricărei implementări PWA noi. Folosește Boost search-docs înainte de API-uri Laravel. Verifică documentația oficială actuală pentru browser APIs.
3. Consultă AURA_PWA_AUDIT_V1.md, în special §7.3 și §9. Verifică deciziile aprobate pentru brand/iconițe, origine, browsere minime și offline/SW. Dacă lipsesc, pregătește cod/teste cu flags off și menționează exact ce împiedică acceptarea; nu declara logo oficial o imagine aleasă de tine.

Permis:
- Editează layouts/app.blade.php numai pentru metadate/bootstrap PWA, profile/settings.blade.php numai pentru control/instrucțiuni instalare, routes/aura-demo.php numai pentru GET manifest/worker și bootstrap/app.php numai pentru atașarea middleware-ului de antete, fără schimbarea autentificării sau a ordinii middleware-urilor existente.
- Creează config/pwa.php; PwaController; PreventPrivateResponseCaching; componente aura/pwa-head și aura/pwa-install; public/assets/aura/pwa.js; resources/pwa/sw.js; public/offline.html; iconițele aprobate în public/assets/aura/pwa; tests/Feature/PwaInstallationTest.php. Folosește Artisan make pentru clase/teste și --no-interaction. Nu crea alte documente sau foldere de bază.
- Nu modifica dependențe, DB, migrări, module comerciale, layout/admin UI, autentificare, sesiuni, CSRF, emailuri sau route names existente. Nu instala nimic și nu migra frontend-ul la SPA/Vite. Nu commit/push/deploy, nu accesa Hetzner și nu afișa secrete.

Contract de implementare:
- GET /manifest.webmanifest, name aura.pwa.manifest: JSON cu MIME manifest; id „/” stabil pe origine, start_url „/dashboard”, scope „/”, display standalone, lang ro, nume/culori aprobate, iconițe PNG 192/512 any+maskable și touch icon 180. Fără date de utilizator/referral/cupoane în manifest.
- GET /sw.js, name aura.pwa.worker: worker din resources, MIME JavaScript, Cache-Control no-cache/no-store; URL stabil inclusiv după rollback. Nu îl servi cu cache lung sau redirect de login.
- Config flags enabled/worker_enabled/worker_retirement și versiune cache; implicit off până la configurarea aprobată. Include bootstrapul o singură dată în afara condițiilor Vite/demo. Nu îl înregistra din layoutul admin.
- beforeinstallprompt este folosit numai dacă există evenimentul; prompt() numai la click. Fallback manual iOS/macOS/alte browsere, anulare fără blocaj. Detectează fereastra standalone prin display-mode și navigator.standalone unde există; nu pretinde detecție globală sigură a instalărilor.
- SW scope / atinge potențial și /admin: nu afirmi izolare de securitate. Nu interceptezi admin, login/register/forgot/reset/verify, dovezi, cereri non-GET sau cross-origin. Niciun cache al paginilor USER/API/HTML auth, nici replay de mutații.
- Doar navigările USER GET aprobate în audit pot primi offline generic la eroare de rețea. Răspunsuri HTTP 401/403/404/419/500 și redirecturi autentice nu sunt transformate în offline. Nu stoca răspunsul rețelei.
- Cache API numai offline.html public autonom și iconițe explicit permise; prefix AURA PWA+mediu+versiune, cleanup numai acel prefix. Offline fără date personale, imagini mari sau fonturi externe.
- Middleware-ul nou setează private,no-store pentru răspunsuri autentificate și căi sensibile, fără a modifica logica de acces/sesiune/CSRF. Păstrează antetele pentru download/reset/coduri.
- Fără skipWaiting automat. Arată update waiting, activează doar după confirmare și protejează formularele nesalvate. Fără reload automat care pierde date la controllerchange.
- Implementează retragerea: același /sw.js servește worker valid care curăță numai cache-urile proprii și unregister; oprirea registration nu este rollback suficient. Păstrează manifestul/URL-urile compatibile.

Ordine: config/manifest/iconițe -> head/bootstrap/instalare -> antete -> offline/SW -> update/retragere -> teste -> verificare dispozitive -> predare locală. Dacă proprietarul a ales explicit V1 fără SW, omite worker/offline/lifecycle și raportează amânarea offline-ului, fără a declara varianta completă livrată.

Acceptare: instalare/relaunch standalone pe matricea aprobată, fallback manual, iconițe corecte, update fără reinstalare, offline generic după inițializare online, site normal când APIs lipsesc. Nu promite toate browserele și nu cere permisiuni de notificări.

Verificare: PHPUnit pentru manifest/MIME/antete/flags și accesul existent; test manual pe Android/iOS/desktop pentru instalare, permisiuni browser, safe areas/tastatură și offline; două taburi+formular nesalvat pentru update; cache inspectat după A login/logout/B login și download privat; retragere cu worker vechi deja instalat. Nu declara test manual executat dacă nu ai dispozitivul. Folosește numai DB de test izolată pentru regresiile Auth/Security/ProjectWorkflow; dacă nu există autorizare pentru migrări de test, raportează blocajul, nu folosi DB locală. Rulează Pint --dirty --format agent dacă modifici PHP. Nu rula comenzi care rescriu fișiere fără legătură.

Predă lista fișierelor, rezultatele testelor, limitările observate, configurația de activat și rollbackul; fără publicare sau operațiuni pe server.
```

### 13.2 Prompt complet — implementare PWA V2

```text
Implementează exclusiv motorul intern de notificări AURA V2, centrul USER, administrarea SUPER ADMIN, read/unread, preferințe, istoric și badge oportunist. Fără Web Push, VAPID, transport extern, schimbări comerciale sau rescriere frontend. Inboxul trebuie să funcționeze fără instalare/SW. V1 este opțională pentru inbox și necesară doar experienței native de iconiță.

Context existent verificat: Laravel 13.42.0 la audit, User folosește Notifiable. VerifyEmailCode și ResetAccountPassword au doar mail, prin AccountMail. MissionController::reminder programează un MissionReminder; routes/console.php produce ProjectEvent reminder_sent și status sent. AppServiceProvider compune ultimele 10 mementouri în layouts.project, fără read/unread. ProjectEnrollment::event și MissionProgress produc jurnale tranzacționale de proiect. SubscriptionEvent este jurnal comercial separat. Admin notifications/announcements/messageTemplates/delivery-log și mai mulți clopoței USER sunt demo/localStorage. Nu există notifications/outbox/preferences/campaign tables în migrările auditate. Nu presupune că această stare a rămas identică.

Înainte de editare:
- Citește AGENTS.md și toate regulile aplicabile .ai/rules dacă există, apoi skillurile Laravel, testing și frontend când editezi UI. Reconfirmă versiunile Composer/NPM, structura și starea Git. Boost search-docs pentru APIs Laravel și database-schema pentru schema reală înainte de modele/migrări; nu afișa date/confidențiale.
- Citește AURA_PWA_AUDIT_V1.md §8.2–8.4 și §10. Verifică V1 dacă există: endpointuri no-store și SW care nu cache-uiește inboxul. Nu introduce al doilea worker.
- Confirmă deciziile de categorii, audiență, retenție/volume/latency, backfill și permisiune badge iOS. Pregătește cu flags off dacă politicile lipsesc; nu porni trimiteri sau migrații pe baza folosită de aplicație. Testele cu migrări sunt permise numai pe DB de test explicit izolată.

Permis: noi clase/config/migrări/factories/teste pentru notifications, notification_preferences, notification_campaigns, notification_outbox; controllers/requests/jobs/command/observer menționate mai jos; modificări punctuale în AppServiceProvider, routes/aura-demo.php, routes/admin.php, routes/console.php, layouts USER și paginile cu clopoțel, Setări, paginile admin notifications/delivery-log și real route mapping în admin/layouts/app. Noile fișiere rămân în structura existentă.
Interzis: schimbări în reguli de abonament/cupoane/cote/proiecte, MissionController::reminder, schema reminders, metoda comercială ProjectEnrollment::event sau algoritmul de termene/review; nu înlocui emailurile de cont; nu schimba guard/middleware auth/CSRF/sesiuni/roluri; nu transforma taburile anunțuri/șabloane în motoare noi; fără pachete, alte documente, commit/push/deploy/Hetzner.

Arhitectură obligatorie:
1. Un singur inbox: tabela standard Laravel notifications și modelul framework DatabaseNotification. UserInboxNotification, via database, payload plain text versionat, fără ShouldQueue. Nu crea model propriu paralel de inbox.
2. NotificationPreference: user_id unic, mission_updates, announcements, badge_enabled și state_version. Defaulturi propuse spre aprobare: misiuni on, anunțuri off; Push nu există în această etapă. state_version crește în aceeași tranzacție la creare/read/purge; lock pe rândul utilizatorului/preferinței pentru serializare.
3. NotificationCampaign: UUID, creator, titlu<=160/corp<=1000 text, target_type/id validate, selector audiență, state, request_key unic, recipient_count, preparation_cursor/upper_user_id, preview_version și momente de confirmare. Stări draft -> preparing -> ready -> published, plus cancelled/failed. PrepareNotificationCampaign procesează loturi, fixează upper_user_id la început și salvează cursor+outbox atomic; scannerul reia pregătirile întrerupte. Eligibilitatea se evaluează pe loturi, lista devine fixă la ready, iar publish cere preview_version curentă. Editarea numai draft/ready invalidează snapshotul; published nu poate fi rescris. Fără HTML, editor de reguli sau SQL din formular.
4. NotificationOutbox: user_id, source_key/type/id, campaign_id nullable, notification_id UUID stabil unic, payload versionat, status pending/recorded/skipped/failed, available_at, attempts, recorded_at, error_code și timestamps; unique source_key/user_id; index status/available_at/id. Este livrare/recuperare, nu inbox separat.
5. ProjectEventObserver::created înscrie outbox în aceeași tranzacție/conexiune, numai pentru tipurile aprobate: reminder_sent, mission_assigned, submission_reviewed, project_completed. Destinatar proprietarul proiectului, nu actor. Fără HTTP/email în observer. Nicio notificare după rollback. Nu dubla finalizarea cu mission_completed fără o decizie de produs. Fără backfill implicit sau notificări de abonament.
6. ProcessNotificationOutbox și scanner recurent recuperează pending după commit, inclusiv crash înainte de dispatch. NotificationService revalidează destinatar/preferințe și scrie database notification + status recorded atomic, cu UUID stabil rezervat în outbox. Retry/concurență nu dublează inboxul. Codul Laravel instalat trebuie reverificat dacă folosești notification->id. Nu folosi doar ShouldBeUnique sau un cursor peste ID-uri de evenimente drept garanție de consistență.
7. Campania: draft -> pregătire audiență în loturi -> preview cu snapshot fixat -> POST confirmat -> procesare. Audiențe numai ID unic/listă explicită sau rol user activ/verificat. Rândurile outbox draft au available_at null și nu pot fi expediate. Editarea invalidează preview. Publish idempotent; cancel oprește doar pending. Revalidează opt-out/status la send; nu adăuga utilizatori noi după snapshot.
8. În V2, datele USER provin exclusiv din notifications. La flag on înlocuiește missionAlerts și clopoțeii demo; la off păstrează fallbackul existent, fără dublare. Event logs comerciale rămân neschimbate.

Fișiere noi principale: config/notifications.php; UserInboxNotification; NotificationService și NotificationCampaignService; ProjectEventObserver; modele NotificationPreference/NotificationCampaign/NotificationOutbox; jobs ProcessNotificationOutbox și PrepareNotificationCampaign, plus command pentru scanner; NotificationController/NotificationPreferenceController/Admin/NotificationCampaignController și Form Requests; views notifications/index, aura/notification-bell, admin/pages/notification-form; JS separate public/assets/aura/notifications.js și public/assets/admin/notifications.js; patru migrări, factories utile și teste feature NotificationCenter/NotificationCampaign/NotificationEventIntegration/NotificationSecurity. Generează clase/teste cu Artisan --no-interaction, testele PHPUnit fără prefix Feature în numele generatorului.

Rute USER în grupul auth+active+verified existent: GET /notifications; GET /notifications/count înaintea rutei dinamice; PATCH /notifications/read-all; PATCH /notifications/{notification}/read; GET /notifications/{notification}/open fără mutație; PATCH /settings/notifications. Nume aura.notifications.* coerente. Queries prin request->user()->notifications(), 404 pentru ID străin; no-store; UUID/range/filter validate. Count returnează unread_count și state_version consistent. Clientul ignoră răspunsuri mai vechi pentru aceeași identitate.
Țintele sunt numai tip+ID mapate la aura.missions.show/aura.projects.show/aura.dashboard, reautorizate la deschidere. Nu accepta URL sau route arbitrar și nu marca citit prin GET.
Rute ADMIN în grupul auth+active+super_admin+verified: GET /admin/notifications (înlocuiește demo); GET /admin/notifications/create; POST /admin/notifications; PATCH /admin/notifications/{campaign}; POST preview/publish/cancel; GET /admin/delivery-log real. Elimini coliziunile cu record demo și nu lași admin.js să înlocuiască DOM-ul real. Celelalte taburi rămase demo trebuie etichetate corect. CSRF și rate limit pentru mutații; confirmare cu număr destinatari înainte de publish.

Badge: count server autoritativ, setAppBadge/clearAppBadge numai dacă APIs există, catch pentru refuz. Polling propus 60 secunde numai când pagina este vizibilă, foreground și după mutații; backoff/429, invalidare între taburi fără date private. Android nu garantează număr pe iconiță; iOS depinde de Home Screen/permisiune; V2 nu sincronizează continuu când aplicația e închisă. Nu cere permisiune automat și nu introduce Push pentru badge.

Ordine: schema/config -> motor/outbox/idempotency -> observer/scheduler -> endpointuri/securitate -> UI USER -> campanii/admin -> badge -> teste/regresii/rollback. Configurațiile de retenție/volum/SLA neaprobate rămân explicit neactivate.

Teste necesare: inbox individual/grup și snapshot, opt-out între preview și procesare, cont suspendat/neverificat, read/read-all concurente, count/version inversate, XSS text/ținte/filtre, matrice acces și IDOR, CSRF activ, publish repetat, rollback eveniment, crash commit-dispatch, retry/concurență un singur inbox, istoricul nu e recreat după purge. Regresii Auth/Security/ProjectWorkflow/SubscriptionInfrastructure pe DB de test; reminder_sent rămâne unic, termenele și cotele identice. Testele nu trimit mail real. Verifică manual badge pe platforme disponibile; declară ce nu ai putut testa. Rulează Pint --dirty --format agent pentru PHP modificat și doar testele relevante, apoi verificările cerute de proiect.

Rollback: flags off pentru produceri/publicare/UI, izolează joburile modulului, păstrează tabelele/aditivele și datele pentru reluare; fallback missionAlerts numai când motorul e off. Fără queue clear global, down migrations automate sau pierdere de date. Predă fișierele, teste, configurația worker/scheduler și limitele badge; nu aplica nimic pe server.
```

### 13.3 Prompt complet — implementare PWA V3

```text
Implementează exclusiv Web Push pentru AURA V2, conectat la inboxul intern existent. Notificări opt-in pe dispozitive multiple, primire în foreground sau fără fereastră în limitele OS, click către pagină autorizată, revocare/expirare și VAPID. Nu crea alt inbox sau alt motor comercial și nu migra emailurile OTP/resetare în Push.

Prerechizite obligatorii de reverificat:
- AGENTS.md, toate regulile .ai/rules aplicabile dacă există, skillurile Laravel/testing/frontend; versiunile Composer/NPM și documentația oficială prin Boost search-docs pentru Laravel.
- Rezultatul real V1: un singur /sw.js stabil, scope /, cache numai offline public, update și retragere; dacă V1 nu are SW, adăugarea lui și testele V1 necesită includere explicită în scop înainte de activare Push.
- Rezultatul real V2: notifications Laravel singurul inbox, outbox idempotent, preferințe/state_version, campanii, scanner/worker și autorizare USER/SUPER ADMIN. Mapează numele existente la contract; nu le recrea în paralel.
- Folosește Boost database-schema înainte de migrări. Confirmă HTTPS, origine separată de test, VAPID test separat, worker/scheduler/egress și politica de expirare a asocierilor/sesiunii. Fără acces la Hetzner sau LIVE.
- Citește AURA_PWA_AUDIT_V1.md §7, §8.5–8.6 și §11. Confirmă conținut lock screen, retenție/TTL/volume, sesiune expirată, politica skip pentru citit, challenge UX și responsabil VAPID. Dacă lipsesc decizii, pregătește cod/teste cu flags off, fără trimiteri reale.

Dependență propusă: minishlink/web-push, transport Web Push standard fără SaaS comercial obligatoriu. Necesită aprobare explicită pentru schimbarea dependențelor înainte de composer require; verifică versiunea compatibilă actuală și API-ul acelei versiuni. Auditul a găsit PHP local 8.4 cu curl/mbstring/openssl/bcmath, dar nu a probat EC/TLS/expediere și nici LIVE. Nu implementa criptografie proprie și nu adăuga SDK NPM/WebPush Laravel paralel automat.

Permis: noile config/webpush.php, PushSubscription/PushDelivery modele și migrări/factories, WebPushTransport contract și StandardWebPushTransport adaptor, PushSubscriptionService, PushSubscriptionController/Form Requests, SendWebPushDelivery job, ProcessPushDeliveriesCommand și teste. Extinde punctele reale V1/V2: SW, pwa/notifications JS, Setări/dispozitive, NotificationService/preferințe/fan-out, routes/aura-demo.php/routes/console.php/AppServiceProvider și jurnalul real admin. composer.json/lock numai pentru dependența aprobată. AuthController::logout poate primi numai hookul de revocare a asocierii înainte de invalidarea sesiunii; păstrează logica actuală logout/invalidate/regenerateToken.
Interzis: schimbări în login/guard/CSRF/middleware acces, reguli parole/abonamente/cupoane/proiecte, tabele comerciale, emailuri de cont, UI global, al doilea SW sau cache privat. Nu copia secrete în .env.example/prompturi/loguri. Nicio documentație nouă necerută, commit/push/deploy/Hetzner, migrare pe DB a aplicației sau expediere către conturi reale. Folosește DB de test izolată pentru teste cu migrări.

Contract de date:
1. push_subscriptions: UUID, user_id server-side, endpoint_hash SHA-256 unic, endpoint/p256dh/auth criptate+hidden, encoding, VAPID key_id, binding_id opac aleator, amprentă HMAC a sesiunii autorizante, amprentă HMAC a stării de securitate a contului, status pending/active/revoked/expired, challenge_digest/expirare, expiration_time, last_seen/activated/revoked, timestamps; index user/status. Nu serializa secrete în răspuns.
2. push_deliveries: notification UUID, subscription UUID, binding snapshot, pending/processing/accepted/failed/expired/skipped, attempts, next_attempt_at, lease_expires_at, response_code/error_code/accepted_at/timestamps; unique notification/subscription și index state/next_attempt_at. accepted înseamnă predat serviciului Push, nu citit/primit garantat.
3. Extinde preferințele V2 cu Push opt-in și categorii aprobate. Inbox intern rămâne utilizabil dacă Push e off/denied/unsupported.

Abonare și izolare:
- USER auth+active+verified; activare numai la click explicit. Feature detect secure context, ServiceWorker, PushManager, Notification și standalone unde este necesar. Pe iOS explică Home Screen/permisiunea; nu promitere de prompt programatic de instalare.
- GET /push/public-key, GET /settings/push/devices; POST /push/subscriptions; POST /push/subscriptions/{subscription}/confirm; DELETE /push/subscriptions/{subscription}; POST /push/subscriptions/revoke-all. Nume aura.push.*, UUID, CSRF și rate limits register/challenge/confirm/revoke; query numai pe contul curent.
- Nu activa un abonament prin simplu upsert. Salvează pending; trimite challenge aleator criptat cu notificare generică vizibilă, confirmat din pagina autorizată a dispozitivului; digest/expiry server, single use. Tokenul de challenge circulă doar în memorie cu postMessage validat, fără stocare persistentă; pagina închisă reia activarea. Rate limit pentru a evita spamul de activare.
- Endpoint activ al altui cont nu se reasignează. Cere revocarea vechii asocieri și unsubscribe/resubscribe explicit. user_id/status din payload nu sunt autoritative. Mai multe dispozitive ale aceluiași cont sunt rânduri separate.
- La logout, serverul revocă asocierile sesiunii înainte de invalidare, independent de JS/unsubscribe; celelalte dispozitive rămân. Browserul încearcă unsubscribe, clear badge și close numai pentru notificările AURA. Rebind rotește binding_id și invalidează joburile vechi.
- Înainte de send verifică status activ/email verificat/preferințe/amprenta de securitate și binding. Schimbare parolă/reset/email neconfirmat oprește asocierile vechi fără a rescrie fluxurile auth. Aplică politica de lease/expirare a sesiunii aprobată, fără să presupui că sesiunea web revocă singură Push. Clickul Push cere autentificare normală.

Securitate transport:
- Validează endpoint la register și send: HTTPS, furnizori Push verificați din documentația curentă, fără userinfo/port arbitrar, IP localhost/private/link-local/metadate blocate inclusiv IPv6/DNS rebinding; fără redirecturi și cu protecție DNS/egress la transport. Lista neconfigurată refuză expedierea, nu permite orice host. Nu accepta endpoint URL arbitrar pentru teste admin.
- VAPID private numai în config server din secret store/env, public în răspuns separat. Key_id per pereche și mediu. Rotație cu cheie veche disponibilă pentru subscriptions vechi și reabonare controlată; nu genera alte chei la deploy/rollback. Joburile conțin numai ID-uri, fără chei/abonamente decriptate. Adaptorul redactează excepțiile înainte de logger/failed_jobs, inclusiv lanțul previous; nu păstra endpoint/auth/payload în mesajele brute.
- 404/410 -> expired/revoked; 429 -> Retry-After; 5xx/network -> backoff/jitter, limită încercări și TTL; 400/401/403 -> clasificare/alertare configurație, fără ștergere în masă. Protejează limitele per cont/dispozitiv/campanie.

Livrare:
- Materializarea notificării interne creează durabil push_deliveries pending în aceeași tranzacție când eligibil. Dispatch după commit + scanner de recuperare. Nu este suficient un callback afterCommit fără evidență recuperabilă.
- Jobul revalidează înainte de send și folosește lease; nicio tranzacție DB lungă în timpul HTTP. Timeout worker sub retry_after și timeout HTTP mai mic. Reluările sunt at-least-once; tag stabil pe notification ID reduce duplicatele, fără promisiune exactly-once. Joburile nu dublează inboxul.
- Payload implicit „Ai o notificare nouă în AURA”, ID opac, binding_id și versiune/count autoritativ unde asocierea e validă. Niciun nume/sumă/proiect/OTP/reset token/evidence în lock screen. Mesajul deja predat nu poate fi rechemat garantat.
- SW push folosește showNotification și un tag stabil; nu trimite silent Push doar pentru badge. Binding-ul local e opac, fără date personale ori tokenuri auth. Count vechi/mismatched nu se aplică noii identități; reconciliere din server la foreground, fără +1 permanent.
- notificationclick închide notificarea și deschide/focalizează numai origine/rută USER allowlist către endpointul inbox open, care reautorizează ținta; fără admin/auth/open redirect/javascript. Sesiune expirată -> login și apoi numai destinația internă sigură. Nu marca read la simpla afișare sau închidere OS.
- Nu modifica strategia de cache V1 și nu forța skipWaiting/reload cu formulare nesalvate. Chrome/Edge/iOS/Android pot întârzia/lipsi; nu promite livrare când browserul/OS este oprit forțat ori dispozitivul offline.

Ordine: prerechizite/aprobare dependență -> config/date/SSRF -> register/challenge/revoke -> UI și lifecycle cont -> livrări/transport/retry -> SW/click/count -> jurnal/monitorizare -> teste -> probe cu dispozitive de test -> predare locală cu flags off până la activarea aprobată.

Teste: WebPushTransport fake injectat (Http::fake singur nu blochează automat HTTP intern al bibliotecii); posesie challenge, cross-account endpoint și UUID, CSRF activ, SSRF/DNS/redirect/IPv6, keys invalide, logout A->B, rebind cu job pending, parole/reset/email/status, două dispozitive, revocare local/all, 404/410/429/5xx/timeouts/VAPID greșit, crash după acceptare, lease/retry/concurență, payload out of order, TTL și flags. Niciun secret în frontend/logs/failed_jobs. Teste manuale foreground/closed/offline/permisiuni pe Android Chrome, iOS Home Screen și desktop, cu OS/browser și limite observate. Reexecută regresiile V1/V2 și Auth/Security/ProjectWorkflow/SubscriptionInfrastructure pe mediu de test; rulează Pint pentru PHP modificat. Nu declara probate platformele fără acces fizic/mediu real.

Rollback: dezactivează producerii și verifică flag-ul în fiecare worker înainte de send; izolează doar joburile Push, păstrează inboxul V2 și schema. SW compatibil pentru retragerea Push; dacă se retrage tot PWA, worker de retragere la același /sw.js. Păstrează VAPID/DB pentru diagnostic, revocă abonamentele la incident; fără queue clear global, key rotation automată sau down migrations pe date active. Predă fișierele, teste, limitările și pașii operaționali, fără deploy.
```

## 14. Concluzia auditului și integritatea livrării

Aplicația are o fundație adecvată pentru instalare și puncte reale de integrare pentru notificări; nu are încă o infrastructură PWA/Push completă. V1 este o extindere mică și independentă. V2 cere un motor persistent și eliminarea confuziei între componente reale și demonstrative. V3 cere controlul complet al asocierii dispozitivelor și operare verificată, cu acceptarea limitelor OS.

Raportul separă constatările de cod, documentația platformelor și propuneri. El nu certifică funcționarea LIVE, schema aplicată sau trecerea testelor. Nu este necesară schimbarea arhitecturii într-o SPA, introducerea unei autentificări noi ori modificarea regulilor comerciale pentru aceste etape.

**Singurul fișier creat pentru acest task este `AURA_PWA_AUDIT_V1.md`.** Nu au fost modificate fișierele aplicației, instalate dependențe sau executate operațiuni asupra bazei de date. Git a fost consultat numai în citire pentru controlul stării; nu au fost făcute modificări Git, commit, push ori deploy. Niciun server Hetzner/DEV/LIVE nu a fost accesat.
