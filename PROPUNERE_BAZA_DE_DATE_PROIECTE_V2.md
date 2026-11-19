# Aura V2 · Propunere de bază de date pentru modulul Proiecte

**Document de proiectare · 19 noiembrie 2026**  
**Baze locale verificate** — `aura` (V1) și `aura_v2` (V2), MySQL 8.4.3. Aplicația V2 folosește Laravel 13.42.0.

## 1. De la acceptarea oportunității la începerea proiectului

Când utilizatorul acceptă o oportunitate publicată, Aura îi creează proiectul personal în starea „Acceptat” și îi alocă misiunile configurate pentru înscriere, fiecare cu propria listă ordonată de pași. Proiectul poate rămâne neînceput. Când titularul alege „Începe proiectul”, misiunile devin disponibile pentru execuție. Titularul poate relua lucrul de pe alt dispozitiv, iar administratorul vede cui i-a fost alocată misiunea, ce pas a ajuns la verificare și cine a decis rezultatul.

**Unitatea de înscriere este proiectul. Unitatea de alocare este misiunea. Unitatea de progres este pasul.** „Proiectele mele” grupează munca pe oportunități. „Misiunile mele” afișează sarcinile concrete ale aceluiași utilizator, inclusiv când un proiect are mai multe misiuni. O editare ulterioară a șablonului se aplică alocărilor viitoare.

Propun **12 tabele noi**, în trei grupuri. Cinci descriu catalogul și șabloanele, cinci păstrează proiectele, progresul, verificările și istoricul, iar două susțin mementourile și afișarea recompenselor externe. Identitatea se reutilizează din V2. Secțiunile 4–6 dau coloanele, relațiile și contractele de scriere.

## 2. Ce există deja

### Baza de date V1

Am verificat tabelele efectiv instalate prin `information_schema`, `SHOW CREATE TABLE` și numărări de rânduri, apoi am comparat rezultatul cu migrațiile și serviciile Laravel. Baza locală `aura` are **48 de tabele**. Fiecare dintre cele opt tabele de mai jos avea **0 rânduri** la verificare. Modelul și comportamentul din cod sunt referințele disponibile pentru acest modul.

| Tabel V1 | Relații și reguli observate | Ce preluăm în V2 |
|---|---|---|
| `categories` | Nume unic, culoare și stare. `opportunities.category_id` permite NULL și devine NULL la ștergere. | Categoria devine sursa filtrului și a regulilor comune. Cerem categorie la publicare și păstrăm categoria folosită. |
| `opportunities` | Furnizor, tip, stare, `eligibility` JSON, estimare în unități monetare minime, monedă și `version`. | Păstrăm identitatea ofertei și extindem modelul cu perioada, conținutul, capacitatea și forma recompensei. |
| `opportunity_mission_templates` | Un rând pentru o sarcină ordonată dintr-o oportunitate. `UNIQUE(opportunity_id, sort_order)`, politică de confirmare și metadate. | În V2, administratorul editează o misiune care conține mai mulți pași. Introducem antet de șablon și tabel de pași. |
| `projects` | Un proprietar și o oportunitate obligatorie în schema instalată. Ștergerea oportunității este restricționată. Perechea utilizator–oportunitate are doar indexuri separate. | Păstrăm proiectul personal și impunem unicitatea înscrierii în baza de date. |
| `project_missions` | Copii sub proiect cu poziție, titlu, stare și politică de completare. Referința la șablon poate deveni NULL. | Păstrăm instanța personală și îi adăugăm pași individuali, termen, XP și proveniența alocării. |
| `mission_confirmations` | Misiune, utilizator, metodă, conținutul declarației, stare și momentul confirmării. | Introducem ținta exactă, încercările succesive, evaluatorul, motivul deciziei și dovada. |
| `external_earnings` | Câștig informativ legat de utilizator, oportunitate și opțional de proiect sau conversie. | Fiecare încasare are un rând propriu, cu sumă, monedă și sursă. Titularul se identifică prin proiect. |
| `opportunity_external_links` | Un link de utilizator per oportunitate, cu eligibilitate și perioadă proprie. | Punct de integrare pentru recomandări. Eligibilitatea unui link are altă semnificație decât dreptul unei persoane de a începe proiectul. |

În V1, `OpportunityService::start` folosește o tranzacție și `firstOrCreate`, apoi copiază șabloanele la prima pornire. Fără o cheie unică pe perechea de înscriere, două cereri simultane pot crea două proiecte. `completeMission` marchează direct misiunea finalizată și creează o confirmare cu starea `confirmed`. Revizuirea admin folosește `pending/approved/rejected`. `confirmExternalEarning` poate închide proiectul odată cu declararea banilor. În V2, primirea recompensei se confirmă în parcursul obligatoriu. Proiectul se închide când toate misiunile obligatorii au fost finalizate.

Rutele V1 acoperă pornirea unei oportunități, detaliul proiectului, finalizarea unei misiuni și revizuirea confirmărilor. `/projects` redirecționează spre dashboard, iar `/admin/eligibility` deschide categoriile. Tabelele alăturate `affiliate_clicks`, `external_conversions`, `xp_events` și `audit_events` sunt utile ca modele de integrare. De exemplu, V1 deduplică evenimentele XP după `idempotency_key` și conversiile după perechea furnizor–referință.

### Baza de date și interfața V2

Baza locală `aura_v2` are **10 tabele**. Are `users`, `user_profiles` și opt tabele tehnice Laravel. Tabelele pentru proiecte, misiuni, abonamente și XP lipsesc. `users.role` admite `user` și `super_admin`, iar `users.status` admite `active`, `suspended` și `disabled`. Profilul existent conține fusul orar și limba. Eligibilitatea după vârstă, rezidență, statut de client nou sau abonament cere o sursă suplimentară.

Am confruntat rutele înregistrate de Laravel cu paginile Blade, formularele admin, datele demonstrative și funcțiile JavaScript care execută butoanele.

| Ecran și rute GET existente | Ce face interfața astăzi | Date necesare în V2 |
|---|---|---|
| User · `/opportunities`, `/opportunities/demo` | Căutare după titlu/descriere. Filtre după categorie, dificultate, recompensă, durată și disponibilitate. Detaliul arată sursa, condițiile, pașii, termenii și butonul „Începe misiunea” sau „Deschide misiunea”. | Catalog publicat, reguli aplicabile, termene, pașii configurați și proiectul utilizatorului curent. |
| User · `/missions`, `/missions/demo` | „În progres”, „Finalizate”, „Istoric”, bifare/debifare, procent, stare recompensă, „Setează un memento”, „Vezi oportunitatea”. | Misiuni și pași proprii, încercări de confirmare, recompensă informativă și memento persistent. |
| User · `/projects` | Folosește aceeași funcție de listare ca misiunile. „Deschide proiectul” trimite la detaliul unei misiuni. | Listă de proiecte distincte, detaliu cu toate misiunile, progres agregat și următorul pas. Aceleași date alimentează cardurile din dashboard. |
| Admin · `/admin/opportunities` | Adaugă, detalii, editează, arhivează, caută, sortează și exportă. Formularul cere și tipul recompensei, plătitorul, geografia, limita de participări, imaginea, instrucțiunile și termenii. | Un singur catalog pentru admin și user. Numărul de participanți se calculează din înscrieri. |
| Admin · `/admin/categories`, `/admin/eligibility` | Două file. Categoria are descriere și vizibilitate. Regula are nume, categorie, condiții, geografie, vizibilitate și stare. Ambele au acțiune de arhivare. | Categorii și reguli administrabile separat, cu efect explicit la înscriere. |
| Admin · `/admin/missions`, `/admin/mission-templates` | Misiunea are oportunitate, utilizator, acțiune obligatorie, tip de verificare, termen și XP. „Creează șablon” și „Configurează pașii” folosesc acum formularul aceleiași misiuni demo. Editorul adaugă, șterge, configurează și reordonează pași. | Editor pentru definiții, flux separat de alocare și listă de instanțe cu proprietar real. |
| Admin · `/admin/verification` | „Revizuiește” afișează utilizatorul, referința/dovada și observația. Administratorul aprobă sau respinge. | Coadă de verificări, dovadă privată și decizie trasabilă. Acest ecran completează modulul cerut. |

Rutele user au deja `auth` și `active`. Grupul admin adaugă `super_admin`. În modulul analizat, serverul livrează pagini demo, iar scrierile sunt simulate în `localStorage`. Layout-ul user convertește legăturile către detalii în URL-uri `/demo` și pierde identificatorul în acele legături. Admin are o rută generică GET pentru detaliu/editare. La implementare, fiecare link trebuie să transporte ID-ul înregistrării reale.

## 3. Relațiile principale

~~~mermaid
erDiagram
    users o|--o{ users : sponsorizeaza
    users ||--o{ projects : detine
    categories ||--o{ opportunities : grupeaza
    categories ||--o{ eligibility_rules : defineste
    opportunities o|--o{ eligibility_rules : particularizeaza
    opportunities ||--o{ mission_templates : contine
    mission_templates ||--o{ mission_template_steps : ordoneaza
    opportunities ||--o{ projects : genereaza
    projects ||--o{ project_missions : contine
    mission_templates ||--o{ project_missions : genereaza
    project_missions ||--o{ project_mission_steps : contine
    project_missions ||--o{ mission_submissions : primeste
    project_mission_steps o|--o{ mission_submissions : precizeaza
    projects ||--o{ project_events : inregistreaza
    project_missions ||--o| mission_reminders : programeaza
    projects ||--o{ external_earnings : consemneaza
    external_earnings o|--o{ mission_submissions : documenteaza
~~~

**Sponsor.** `users.sponsor_id` este cheia externă opțională către `users.id`. Un sponsor poate avea mai mulți utilizatori recomandați. Pentru un proiect se citește sponsorul titularului din `projects.user_id`, iar pentru o misiune se urmează `project_missions.project_id` până la același titular.

**Exemplu.** Oferta „Bonus cont bancar” are două misiuni, „Deschidere cont” cu doi pași și „Condiții de plată” cu trei. Înscrierea Anei generează un proiect, două misiuni și cinci pași personali. După începerea proiectului, cu prima misiune aprobată și un pas acceptat în a doua, Ana vede **1 din 2 misiuni finalizate** și **60% progres în proiect**. Dacă adminul adaugă un al patrulea pas celui de-al doilea șablon, următoarea înscriere primește șase pași. Ana își păstrează cei cinci.

## 4. Dicționarul de date propus

Convenții pentru toate tabelele noi. `id` este cheia primară `BIGINT UNSIGNED`. Cheile externe au același tip. Tabelele editabile au `created_at` și `updated_at`. Evenimentele sunt doar adăugate. `?` marchează un câmp care admite NULL. Sumele folosesc întregi în unități minime și monede `CHAR(3)`. Procentele folosesc puncte de bază, astfel încât 10% se stochează ca 1000. Momentele se păstrează în UTC. Datele de calendar din formulare au o interpretare explicită în fluxurile de mai jos. Stările sunt coduri controlate, cu etichete în română la afișare.

### Catalog și configurare

| Tabel nou | Coloane de domeniu | Relații și constrângeri |
|---|---|---|
| `categories` | `name, slug, description?, color?, visibility, status` | Nume și slug unice. Vizibilitate `all/premium`. Stare `draft/active/paused/archived`. O categorie activă poate avea mai multe oferte. |
| `opportunities` | `category_id, title, summary, provider, source_label?, source_url?, geography?, eligibility_text?, instructions?, terms, difficulty, estimated_minutes, icon?, image_path?, is_featured, starts_at, ends_at?, project_due_on?, project_duration_days?, participation_limit?, status, version, created_by, updated_by`. Recompensă descrisă mai jos. | FK la categorie și la utilizatorii editori. `version` crește la fiecare editare. Stare `draft/published/paused/archived`. Un interval încheiat produce eticheta „Expirată”. `ends_at > starts_at`, limita definită este pozitivă. |
| `eligibility_rules` | `category_id, opportunity_id?, name, conditions_text, geography?, country_code?, visibility, check_method, status, version` | O regulă fără oportunitate se aplică tuturor ofertelor categoriei. O regulă specifică are și FK compus `(category_id, opportunity_id) → opportunities(category_id, id)`, cu cheie unică pe țintă. Metodele sunt `system/user_attestation/partner_review`. Stare `draft/active/paused/archived`, cu vizibilitate `all/premium`. Textul explicativ poate fi completat cu un criteriu structurat `criterion_code?, criterion_params? JSON` dintr-o listă controlată. |
| `mission_templates` | `opportunity_id, title, description?, required_action?, verification_method, xp_points, deadline_on?, deadline_anchor?, deadline_after_days?, anchor_step_id?, position, assign_on_accept, is_required, status, version` | FK la oportunitate. `UNIQUE(opportunity_id, position)`. Stări editoriale `draft/active/archived`. `assign_on_accept` stabilește alocarea la înscriere, iar `is_required` stabilește dacă finalizarea misiunii este necesară pentru închiderea proiectului. |
| `mission_template_steps` | `template_id, position, title, description?, instructions?, required_action?, deadline_on?, deadline_anchor?, deadline_after_days?, anchor_step_id?, xp_points, verification_method?, confirms_receipt, status` | FK la șablon. `UNIQUE(template_id, position)`. Starea `draft/active/archived` controlează includerea la alocare. `confirms_receipt` identifică pasul în care titularul declară o încasare. Modificarea sau reordonarea pașilor incrementează versiunea șablonului. |

**Recompensa afișată în catalog.** În `opportunities`, propun `reward_type` pentru familia din admin (fixă, procentuală, cashback, bonus furnizor extern, nemonetară), `reward_unit` pentru `money/percent/non_cash`, `reward_amount_minor?`, `reward_currency?`, `reward_bps?`, `reward_label` și `payer`. Astfel, „până la 50 lei”, „150 lei/client” și „30% recurent” au reprezentări distincte. Pentru `money` sunt obligatorii suma și moneda, pentru `percent` valoarea este între 0 și 10.000 puncte de bază, iar pentru `non_cash` intervine descrierea. O constrângere `CHECK` permite doar combinația de coloane corespunzătoare unității. Filtrul „cel puțin 100 lei” compară valori monetare RON, minimum 10.000 bani. Ofertele procentuale pot rămâne vizibile prin celelalte filtre.

**Eligibilitate și disponibilitate.** Catalogul aplică starea categoriei, vizibilitatea, starea ofertei și fereastra de înscriere `[starts_at, ends_at)`. O dată finală inclusivă introdusă în formular devine începutul zilei următoare în fusul campaniei, propus inițial `Europe/Bucharest`. Filtrul „Expirate” poate prezenta ofertele încheiate utilizatorilor cu acces. Pauza oprește înscrierile noi. Arhivarea păstrează accesul proprietarului la proiect și la termenii acceptați.

Regulile active ale categoriei și cele specifice ofertei se cumulează. Vizibilitatea unei reguli stabilește segmentul căruia i se aplică. O condiție obligatorie pentru toți se configurează `all`. Serverul verifică regulile de sistem la acceptare și păstrează rezultatul. Pentru „18+, client nou”, interfața poate cere o declarație explicită. Pentru o condiție pe care numai partenerul o verifică ulterior, înscrierea este provizorie și proiectul include un pas de verificare într-o misiune obligatorie. Dacă oferta cere validare de partener înainte de înscriere, publicarea cere o sursă conectată care poate furniza decizia pentru utilizatorul respectiv. Accesul Premium necesită o sursă reală pentru dreptul de acces. Până atunci se publică pentru toți sau se ține oferta Premium în ciornă.

### Proiecte, progres și verificări

| Tabel nou | Coloane de domeniu | Relații și constrângeri |
|---|---|---|
| `projects` | `user_id, opportunity_id, title, source, created_by, status, accepted_at, started_at?, completed_at?, due_on?, opportunity_version, offer_snapshot JSON, eligibility_snapshot JSON` | `UNIQUE(user_id, opportunity_id)`. `user_id` este titularul, `created_by` este actorul. Sursa este `self/admin`. Stările sunt `accepted/active/completed`. La acceptare se copiază oferta, termenii, recompensa și regulile evaluate, inclusiv versiunea regulii, rezultatul, actorul și data declarației. |
| `project_missions` | `project_id, template_id, template_version, position, title, description?, required_action?, verification_method, xp_points, is_required, due_on?, deadline_anchor?, deadline_after_days?, anchor_step_id?, status, assigned_by, assigned_at, completed_at?` | FK la proiect, șablon și actor. `UNIQUE(project_id, template_id)` și `UNIQUE(project_id, position)`. Titularul se obține din proiect. La alocare se verifică oportunitatea comună și se copiază caracterul obligatoriu și regula de termen. |
| `project_mission_steps` | `mission_id, source_step_id?, position, title, description?, instructions?, required_action?, verification_method, confirms_receipt, xp_points, due_on?, deadline_anchor?, deadline_after_days?, anchor_step_id?, status, completed_at?` | FK la misiune, `UNIQUE(mission_id, position)`. FK opțional la pasul din editor cu `ON DELETE SET NULL`. Instanța păstrează instrucțiunile, metoda, regula de termen și rolul pasului la confirmarea încasării. |
| `mission_submissions` | `mission_id, step_id?, external_earning_id?, method, status, submitted_by?, submitted_at, evidence_text?, private_file_key?, provider?, external_event_id?, request_key, reviewed_by?, reviewed_at?, review_note?` | Fiecare rând este o încercare. `external_earning_id?` este FK la încasarea documentată de acea încercare, verificată ca aparținând aceluiași proiect. Dacă `step_id` este NULL, decizia privește misiunea întreagă. Altfel, FK compus `(mission_id, step_id) → project_mission_steps(mission_id, id)` cu cheie unică pe țintă. `UNIQUE(request_key)` și `UNIQUE(provider, external_event_id)` pentru evenimentele externe, unde ambele valori sunt obligatorii. |
| `project_events` | `project_id, mission_id?, step_id?, actor_user_id?, event_type, from_status?, to_status?, event_key, occurred_at, metadata? JSON` | Jurnal cronologic al acceptării, începerii proiectului, alocării, bifării, retrimiterii, deciziei și finalizării. FK la proiect și la actor. Relațiile compuse la misiune și pas verifică apartenența la același proiect și aceeași misiune. `UNIQUE(event_key)`. |

**Alocare și istoric.** Datele curente se citesc din misiuni și pași. Evenimentele explică traseul și alimentează fila „Istoric”. La copiere, un pas fără metodă proprie primește metoda declarativă. Metoda de pe șablon guvernează validarea finală a misiunii.

**Termene.** Fereastra definită de `opportunities.starts_at` și `opportunities.ends_at` controlează acceptarea ofertei. Un proiect deja acceptat poate începe sau continua după închiderea ferestrei. Termenele de execuție sunt date calendaristice inclusive, calculate în fusul campaniei (`Europe/Bucharest`). Depășirea lor se afișează ca întârziere și păstrează progresul.

| Termen de execuție | Configurare | Data individuală |
|---|---|---|
| Proiect | Oferta poate avea fie o dată fixă `project_due_on`, fie un număr întreg de zile `project_duration_days ≥ 0`. Ambele NULL înseamnă proiect fără termen. | La acceptare, `projects.due_on` primește data fixă sau data locală a lui `accepted_at` plus numărul de zile. |
| Misiune sau pas, termen fix | Pe șablon se completează doar `deadline_on`. | La alocare, data se copiază în `due_on` pe instanța personală. |
| Misiune sau pas, termen relativ la acceptare | `deadline_anchor = accepted` și `deadline_after_days ≥ 0`. | Data locală a acceptării proiectului plus numărul de zile, inclusiv pentru o misiune alocată ulterior. |
| Misiune sau pas, termen relativ la un pas anterior | `deadline_anchor = step_checked` pentru prima bifare/trimitere a titularului sau `step_approved` pentru prima aprobare a pasului. Se cer `deadline_after_days ≥ 0` și `anchor_step_id`. | Până la eveniment, `due_on` este NULL și interfața indică pasul așteptat. La eveniment, termenul devine data locală a bifării sau a aprobării plus numărul de zile. |

Pe șablon se admite o singură regulă de termen. Câmpurile nefolosite sunt NULL. Dacă instanța are `due_on` completat, se afișează acea dată. Când lipsesc atât data proprie, cât și regula relativă, pasul moștenește termenul misiunii, iar misiunea pe cel al proiectului. O regulă proprie care așteaptă bifarea sau aprobarea păstrează termenul în așteptare până la acel eveniment.

În șabloane, `anchor_step_id` indică prin FK un pas activ din aceeași oportunitate, anterior țintei în ordinea misiune–pas. Pentru termenul unei misiuni, pasul aparține unei misiuni anterioare. Publicarea și reordonarea validează ordinea și disponibilitatea reperelor la alocare. Pe instanțe se copiază regula, iar FK `anchor_step_id` indică pasul **personal** anterior din același proiect. La alocare se folosește istoricul dacă evenimentul a avut deja loc. Altfel, prima bifare sau aprobare fixează data în tranzacția evenimentului. O retrimitere, o debifare sau repetarea deciziei păstrează termenul deja stabilit.

**Exemplu de termen.** Ana bifează „Am deschis contul” pe 5 martie. Pentru cumpărături, un termen de 30 de zile cu `step_checked` expiră pe 4 aprilie inclusiv. Dacă regula cere `step_approved` și administratorul aprobă pe 8 martie, termenul expiră pe 7 aprilie. Fiecare participant are propriul pas de referință și propria dată.

**Integritate.** Oportunitățile, categoriile, șabloanele și proiectele folosite se arhivează sau se protejează cu `RESTRICT` la ștergere. Pentru pașii din ciornă se permite ștergerea și reordonarea. Pentru verificări și istoric păstrăm inclusiv încercările respinse. Pentru FK compuse, se declară și `UNIQUE(project_id, id)` pe `project_missions`, respectiv `UNIQUE(mission_id, id)` pe `project_mission_steps`. Un eveniment cu pas impune misiune. Proprietarul este stabilit din sesiune sau din alocarea autorizată. Pe conturi cu istoric, operația uzuală este schimbarea stării contului. O eventuală ștergere se tratează explicit împreună cu politica de păstrare a dovezilor.

### Memento și recompensă externă

| Tabel nou | Coloane de domeniu | Relații și efect |
|---|---|---|
| `mission_reminders` | `mission_id, remind_at, timezone, status, sent_at?, revision` | FK unic la misiune. Un memento curent per misiune și titular implicit din proiect. Stări `scheduled/sent/cancelled`. Reprogramarea crește revizia. Procesul de livrare verifică revizia, starea și unicitatea livrării pe `(id, revision)`. |
| `external_earnings` | `project_id, earning_key, source, status, amount_minor?, currency?, description?, provider_reference?, confirmed_at?, recorded_by?` | FK la proiect și la actor. `UNIQUE(project_id, earning_key)` identifică o singură încasare. Stări `pending/confirmed/rejected`. Fiecare plată distinctă primește cheie și rând propriu, inclusiv două plăți cu aceeași sumă în același proiect. O încasare monetară confirmată cere sumă pozitivă și monedă. |

**Încasări individuale.** La un pas marcat `confirms_receipt`, titularul poate introduce suma efectiv primită și moneda. „Am încasat 250 lei pentru recomandarea unui prieten” produce un rând cu `amount_minor = 25000`, `currency = RON` și `source = user_declaration`. Pentru un pas cu textul „Confirm că am încasat integral cei 100 lei”, suma poate fi precompletată și confirmată explicit de titular. Estimarea din ofertă se afișează separat. O bifă fără sumă explicit declarată poate confirma pasul și lasă evidența sumelor neschimbată. O misiune dedicată recompensei folosește același tip de pas.

Când se declară o sumă, aceeași tranzacție leagă încercarea prin `mission_submissions.external_earning_id` și înregistrează încasarea. Pentru metoda declarativă, suma este confirmată la bifare. Pentru verificare, rândul este `pending` până la decizie, care îl trece în `confirmed` sau `rejected`. După respingere, retrimiterea dovezii pentru aceeași plată deschide o nouă încercare și readuce încasarea în `pending`, cu aceeași cheie, sumă și monedă. Pentru o validare externă se păstrează sursa `provider` și referința partenerului. Repetarea cererii cu aceeași cheie și altă sumă produce conflict. O încasare ulterioară se adaugă cu altă cheie. Istoricul păstrează fiecare încasare, iar totalurile includ doar sumele confirmate și se grupează pe monedă. Evidența este informativă, fără transfer automat în Wallet.

XP configurat pe misiune și pe pași este un număr întreg de puncte. Propun acordarea la validarea finală a misiunii, o singură dată pentru fiecare sursă eligibilă. Când se conectează modulul XP, el consumă evenimentul de finalizare și deduplică fiecare acordare după ID-ul misiunii sau pasului.

## 5. Reguli de funcționare

1. **Publicare.** Administratorul creează categoria, oferta, regulile, șabloanele și pașii. La publicare, serverul validează perioada, recompensa, categoria, publicul eligibil și existența a cel puțin unei misiuni obligatorii alocate la acceptare. Fiecare șablon activ are cel puțin un pas activ. Misiunile obligatorii ale ofertei se alocă la acceptare, iar pentru o ofertă cu recompensă de primit, una dintre ele include pasul de confirmare a primirii. Recompensele intermediare se pot confirma prin alți pași sau misiuni. La reordonare, serverul verifică lista completă de ID-uri, versiunea din formular și apartenența pașilor la șablon. Actualizează pozițiile într-o singură tranzacție, folosind temporar poziții libere pentru a evita coliziunile de unicitate.
2. **Acceptarea oportunității și începerea proiectului.** Acceptarea blochează în tranzacție rândul oportunității. După verificarea accesului la cont, dacă titularul are deja proiect, îl returnează, inclusiv după expirarea ofertei. Pentru o înscriere nouă se verifică eligibilitatea, perioada și capacitatea. `participation_limit = NULL` înseamnă nelimitat. Limita numără titularii de la acceptare, inclusiv după finalizare. Tranzacția creează proiectul `accepted` cu `accepted_at` completat, `started_at` și `completed_at` NULL. Din șabloanele active cu `assign_on_accept = true` creează misiunile personale în `assigned` și pașii în `pending`, apoi înregistrează acceptarea. Un eșec anulează întreaga alocare. Modificările de catalog care afectează înscrierea respectă aceeași ordine de blocare. Acțiunea ulterioară „Începe proiectul” blochează proiectul titularului, trece `accepted → active`, setează `started_at` și înregistrează începerea o singură dată. Citirea proiectului și a misiunilor sale păstrează starea curentă.
3. **Alocarea de către admin.** Administratorul selectează utilizatorul, oportunitatea și șablonul activ. Pentru un proiect existent, serverul adaugă misiunea la finalul listei și întoarce instanța existentă la repetarea cererii. Pentru prima alocare, serverul folosește același flux de acceptare, apoi adaugă șablonul ales dacă lipsește. Eligibilitatea și limita se aplică identic. Alocarea permite titularului să înceapă proiectul ulterior. Pentru o misiune dependentă de un pas anterior, alocarea cere ca acel pas să existe în proiect. Când lipsește declarația personală obligatorie, utilizatorul o completează la înscriere, iar alocarea poate fi reluată. Pentru proiecte deja finalizate propun închiderea alocărilor suplimentare.
4. **Urmărire și verificare.** Prima acțiune de execuție asupra unei misiuni `assigned` o trece în `in_progress`. Execuția presupune un proiect început. Un pas pornește `pending`. Declarația utilizatorului îl poate duce direct în `completed`, cu o confirmare `approved` și metoda `user_declaration`. Pentru document sau aprobare, o trimitere creează o încercare `pending` și trece pasul în `in_review`. Aprobarea îl finalizează. Respingerea îl duce în `needs_changes` cu motiv vizibil utilizatorului. Retrimiterea creează un rând nou. Metoda `provider_event` cere un eveniment autentic, corelat cu instanța corectă. Până la conectarea partenerului, o astfel de misiune poate rămâne în verificare sau poate fi publicată cu o politică manuală explicită. Codurile `user_declaration`, `document_upload`, `admin_approval` și `provider_event` corespund opțiunilor reale „Declarație utilizator”, „Încărcare document”, „Aprobare administrator” și „Eveniment API extern”.
5. **Finalizare și debifare.** Toți pașii alocați unei misiuni trebuie acceptați pentru a o finaliza. După ultimul pas acceptat, misiunea se închide automat pentru metoda declarativă sau trece în `in_review` pentru validare finală. Respingerea finală o trece în `needs_changes` și permite retrimiterea. Când ultima misiune cu `is_required = true` ajunge în `completed`, aceeași tranzacție trece proiectul din `active` în `completed` și setează `completed_at` o singură dată. Misiunile opționale își păstrează progresul propriu. De exemplu, dacă „Confirm că am încasat integral cei 100 lei” este ultimul pas obligatoriu și misiunea este declarativă, bifarea închide automat misiunea și proiectul. Dacă misiunea cere verificare finală, aprobarea declanșează aceeași regulă. Debifarea unui pas declarativ este permisă până la trimiterea finală sau închiderea misiunii și se scrie în istoric. O decizie de admin sau de partener se corectează printr-o acțiune trasabilă.
6. **Cereri repetate sau simultane.** Pentru progres și verificare, deciziile curente se recitesc după obținerea blocării. Se blochează întâi proiectul, apoi misiunea, pasul și încercarea relevantă. Se verifică starea curentă și se scriu decizia, stările derivate, datele de finalizare și evenimentul în aceeași tranzacție. Se acceptă cel mult o încercare în așteptare pe țintă, verificată sub blocarea misiunii. Repetarea aceleiași cereri returnează rezultatul existent. O decizie incompatibilă cu una deja luată produce conflict. ID-urile de operație sunt asociate actorului, țintei și conținutului cererii.
7. **Calcul pentru liste.** Misiunea arată pași acceptați / pași alocați. Proiectul afișează procentul pașilor acceptați din totalul pașilor alocați, rotunjit la un întreg. Dacă mai există pași opționali, un proiect finalizat poate avea sub 100% din pași acceptați. În interfață se poate vedea „100% din pași · în verificare”. „Proiectele mele” arată distinct proiectul „Acceptat · neînceput”, cu acțiunea „Începe proiectul”. „În progres” include execuția, verificările și corecturile, iar „Finalizate” folosește starea finală. „Istoric” prezintă participările și evenimentele lor în ordine descrescătoare. Într-un proiect început, următorul pas este prima acțiune disponibilă după ordinea misiune–pas. Dacă utilizatorul a terminat de acționat și așteaptă o decizie, cardul arată „Așteaptă verificarea”. Procentele și numărul de participanți se calculează din rândurile persistate.

## 6. Contractul rutelor propuse

Rutele de mai jos descriu ținta de implementare. Listele păstrează URL-urile familiare din V2. În exemple, `{mission}` și `{step}` sunt ID-uri de **instanță**, iar `{template}` desemnează o definiție administrativă. Căutarea, filtrele, sortarea și pagina sunt parametri GET. Exportul folosește aceleași filtre ca tabelul.

| Cine și ce acțiune | Rută propusă | Rezultat și autorizare |
|---|---|---|
| User · catalog și detaliu | `GET /opportunities`, `GET /opportunities/{opportunity}` | Catalog conform accesului. Detaliul prezintă condițiile și pașii șabloanelor alocate la acceptare. |
| User · „Acceptă oportunitatea” | `POST /opportunities/{opportunity}/projects` | Creează proiectul `accepted` sau regăsește proiectul titularului în starea curentă. Afișează detaliul proiectului. |
| User · „Începe proiectul” | `POST /projects/{project}/start` | Titularul poate începe un proiect acceptat și poate continua cu o misiune. O cerere repetată returnează proiectul cu data inițială de început. |
| User · „Proiectele mele”, „Deschide proiectul” | `GET /projects`, `GET /projects/{project}` | Lista titularului și pagina unui proiect cu toate misiunile, termenele, progresul și istoricul său. |
| User · „Misiunile mele”, detaliu, revino la ofertă | `GET /missions`, `GET /missions/{mission}` | Misiunile titularului și pașii lor. „Vezi oportunitatea” folosește proiectul pentru a identifica oferta și, după arhivare, condițiile copiate la înscriere. |
| User · bifează sau debifează | `PATCH /missions/{mission}/steps/{step}` | Trimite starea dorită. Serverul verifică titularul, apartenența pasului, începerea proiectului, metoda și starea misiunii. La confirmarea primirii unei recompense, titularul poate declara suma, moneda și cheia încasării. |
| User · trimite dovada sau solicită verificare | `POST /missions/{mission}/submissions` | `step_id` opțional. Formularul acceptă declarație, referință sau document potrivit metodei și, la confirmarea unei încasări, aceleași date de plată ca la bifare. Pentru fișiere se păstrează o cheie de stocare privată și se autorizează descărcarea. |
| User · memento | `PUT /missions/{mission}/reminder`, `DELETE /missions/{mission}/reminder` | Creează, reprogramează sau anulează. Ora se interpretează în fusul orar al titularului și se salvează în UTC. Livrarea aparține serviciului de notificări. |
| User · declară o altă încasare în proiect, extensie preluată din V1 | `POST /projects/{project}/external-earnings` | Înregistrează informativ o plată distinctă cu sumă, monedă, `earning_key` și sursa `user_declaration`. UI afișează „Declarată de utilizator”. O cerere repetată identifică plata existentă. Acțiunea păstrează progresul curent. Pentru o confirmare care contează la progres, titularul folosește pasul misiunii. |
| Admin · catalog, categorii, reguli, șabloane | Pentru fiecare resursă `/admin/opportunities`, `/admin/categories`, `/admin/eligibility`, `/admin/mission-templates` se folosesc `GET` listă, `GET /create`, `POST` creare, `GET /{id}`, `GET /{id}/edit`, `PATCH /{id}`. | Publicarea, pauza și arhivarea sunt modificări de stare validate. „Configurează pașii” deschide șablonul. Salvarea șablonului poate include lista completă a pașilor și ordinea lor. |
| Admin · creează proiect, alocă misiune | `POST /admin/projects`, `POST /admin/missions` | Prima primește titularul și oportunitatea, a doua primește și șablonul. Un singur serviciu aplică aceleași reguli ca la acceptarea de către utilizator. |
| Admin · urmărește proiecte și misiuni | `GET /admin/projects/{project}`, `GET /admin/missions`, `GET /admin/missions/{mission}`, `PATCH /admin/missions/{mission}` | Detaliu accesibil din misiune, cu utilizator, pași, termene și decizii. Ajustarea justificată a termenului se jurnalizează. Filtre după titular, oportunitate, stare și termen. |
| Admin · verificări | `GET /admin/verification`, `GET /admin/verification/{submission}`, `PATCH /admin/verification/{submission}` | Coadă și decizie `approved/rejected` cu evaluator, dată și observație. Motiv obligatoriu la respingere. |
| Admin · exportul listelor | `GET /admin/{resursă}/export`, înregistrat explicit pentru listele cu export | CSV pentru selecția autorizată. |
| Partener · verificare automată, la integrare | `POST /integrations/{provider}/mission-events` | Endpoint dedicat, autentificat pentru furnizor. Identifică misiunea/pasul și deduplică referința externă înaintea deciziei. |

Toate rutele user citesc resursele prin titularul autentificat. Admin păstrează grupul existent `auth + active + super_admin`. Formularele web de scriere folosesc protecția CSRF. Pentru detaliile imbricate se verifică întregul lanț proiect–misiune–pas–încercare. Rutele concrete `create`, `export` și cele administrative se înregistrează înaintea rutei demo generice. Navigarea Blade și JavaScript trebuie să genereze direct adresele reale.

## 7. Indexuri, implementare și verificare

| Interogare frecventă | Index propus, pe lângă cheile unice și FK |
|---|---|
| Catalog și disponibilitate în categorie | `opportunities(category_id, status, starts_at)`. Pentru catalogul tuturor categoriilor, verificați și planul pentru `(status, ends_at)`. |
| Reguli aplicabile la înscriere | `eligibility_rules(category_id, status, opportunity_id)`. |
| Proiectele unui titular, după stare și dată | `projects(user_id, status, accepted_at)`. Numărul de participanți folosește indexul pe `opportunity_id`. |
| Misiuni de urmărit și coada de verificare | `project_missions(status, due_on)` pentru admin, `mission_submissions(status, submitted_at)` pentru verificări. Pentru lista user, se pornește din proiectele titularului și se face join pe `project_id`. |
| Cronologie și notificări de trimis | `project_events(project_id, occurred_at, id)` și `mission_reminders(status, remind_at)`. |

**Ordine de implementare**

1. Catalogul, eligibilitatea, editorul de șabloane și pași. Adminul poate crea o ofertă în ciornă, o poate publica, iar userul o poate găsi prin filtre.
2. Proiectele, alocarea tranzacțională, pașii personali și jurnalul. Se înlocuiesc listele comune și ID-urile demo cu paginile de proiect și misiune, apoi se conectează dashboardul la aceleași interogări.
3. Confirmările și coada admin, mementourile și evidența recompensei. Se activează metodele de verificare, Premium și livrarea automată pe măsură ce serviciile din care depind sunt disponibile. Metoda declarativă și aprobarea admin permit rularea integrală a unui proiect înaintea unei integrări externe.

**Date de pornire.** În bazele locale verificate, modulul poate porni cu migrații noi V2 și conținut aprobat de administrator. Fixture-urile admin, lista user din JavaScript și starea din browser sunt exemple. Butonul demo „Începe misiunea” combină înscrierea și accesul la misiune. Interfața propusă are acțiuni distincte pentru acceptare și începere. Într-un mediu V1 populat, se repetă inventarul înainte de import. Se mapează utilizatorii printr-o corespondență verificată de identități, apoi categoriile, ofertele și proiectele. Pentru modelul vechi se poate genera un șablon de misiune pe oportunitate, cu câte un pas pentru fiecare rând ordonat din `opportunity_mission_templates`. Fiecare `project_mission` V1 al acelui parcurs devine un pas personal, iar confirmările se leagă de pasul rezultat. Se păstrează titlurile și termenele efectiv cunoscute. Înainte de impunerea cheilor unice se raportează înscrierile duplicate, confirmările cu stări neuniforme, rândurile fără corespondent și proiectele închise numai prin declararea unui câștig. Importul păstrează o mapare reluabilă între ID-uri și se reconciliază pe număr de proiecte, pași, confirmări și sume per monedă.

**Criterii de acceptare**

- O ofertă publicată apare la filtrele potrivite. O ofertă expirată, complet ocupată sau inaccesibilă planului utilizatorului refuză o înscriere nouă inclusiv prin cerere directă.
- Două acceptări simultane ale aceleiași oferte de către același titular produc un proiect `accepted`, un loc ocupat și un singur set de misiuni. Deschiderea pe alt dispozitiv îl găsește cu `started_at = NULL`. Două cereri de începere produc o singură dată de început. La ultimul loc disponibil, doar una dintre două persoane distincte se poate înscrie.
- Adminul alocă o misiune suplimentară, titularul o vede în ambele contexte, iar un alt cont nu poate citi sau modifica proiectul, dovada ori mementoul.
- Reordonarea sau eliminarea unui pas din șablon afectează următoarea alocare. O misiune începută își păstrează ordinea, configurația și dovezile.
- Bifarea, respingerea cu motiv, retrimiterea și aprobarea actualizează corect procentul, următorul pas și fila de istoric. O decizie repetată păstrează o singură finalizare și o singură acordare XP per sursă.
- Un proiect cu două misiuni obligatorii rămâne activ cât timp una așteaptă verificarea. Confirmarea finală „Am încasat integral cei 100 lei” închide automat proiectul când ultima misiune obligatorie este finalizată, inclusiv cu o misiune opțională încă deschisă. Pe alt dispozitiv se vede același progres.
- După expirarea înscrierilor, termenul proiectului rămâne cel de la acceptare. Pentru cumpărături, termenul de 30 de zile pornește la bifare sau la aprobare, conform regulii copiate, chiar dacă șablonul s-a modificat între timp. O a doua bifare și activitatea altui titular păstrează data calculată inițial.
- Mementoul reprogramat se livrează o dată la noua oră. Două încasări de câte 250 lei din recomandări diferite produc două rânduri și un total confirmat de 500 lei. Retrimiterea aceleiași cereri păstrează două rânduri. Confirmarea lor lasă soldul Wallet neschimbat.

**Campanii repetate.** `UNIQUE(user_id, opportunity_id)` permite o singură înscriere a titularului la fiecare ofertă. Pentru o campanie nouă, administratorul creează o oportunitate cu propriul `opportunity_id`. Titularul se poate înscrie din nou și primește un proiect cu misiuni, termene și încasări proprii. În fiecare proiect se păstrează câte o singură instanță a fiecărui șablon.

Proiectele libere, create fără oportunitate, ar necesita o sursă de misiuni independente și o acțiune nouă în „Proiectele mele”. Limita de participanți pe campanie, acordarea XP la validarea finală și păstrarea alocărilor existente rămân alegeri de proiectare pentru prima implementare.

## Repere pentru verificarea analizei

Căile pornesc din rădăcina repository-ului indicat. Fișierele de migrare descriu structura, iar serviciile, rutele și scripturile de interfață explică efectul butoanelor.

| Repository | Fișiere |
|---|---|
| V1 · structura instalată | `database/migrations/2026_11_12_000000_create_aura_domain_tables.php`, `database/migrations/2026_11_12_000001_add_categories_and_admin_fields.php` |
| V1 · pornire, completare și revizuire | `app/Services/OpportunityService.php`, `app/Http/Controllers/OpportunityController.php`, `app/Http/Controllers/Admin/AccessController.php`, `routes/aura-demo.php`, `routes/admin.php` |
| V2 · rute și navigare | `routes/aura-demo.php`, `routes/admin.php`, `resources/views/layouts/app.blade.php`, `resources/views/admin/layouts/app.blade.php` |
| V2 · ecrane user | `resources/views/opportunities/`, `resources/views/missions/`, `resources/views/projects/`, `public/assets/aura/app.js` (funcțiile `startMission`, `mission`, `missionList`, `completeStep`) |
| V2 · formulare și verificări admin | `resources/views/admin/pages/`, `resources/data/admin-demo.json`, `public/assets/admin/data.js`, `public/assets/admin/admin.js` (funcțiile `templates`, `stepConfig`, `review`, `applyReview`) |
