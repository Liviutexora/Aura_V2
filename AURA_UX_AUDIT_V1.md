# AURA V2 — Audit UX V1

**26 noiembrie 2026 · Perspectiva utilizatorului final · Versiunea locală `68eef95`**

## 1. Rezumat executiv

AURA are un traseu funcțional de la oportunitate la proiect și încasare declarată, dar utilizatorul trebuie să descopere singur cum se leagă etapele. În forma actuală, produsul este organizat în jurul modulelor. Ecranul de start arată indicatori și secțiuni înainte de a răspunde la întrebarea „Ce pot face acum?”.

Cele mai costisitoare probleme apar la trei treceri:

1. **Interes → cont utilizabil.** Landing Page afișează planuri reale, iar pagina publică „Abonamente” prezintă alte planuri, demonstrative. Înregistrarea generică nu explică ce acces primește utilizatorul după verificare. Catalogul îi poate arăta o ofertă de nivel 1, iar acceptarea cere totuși un abonament activ.
2. **Ofertă → primul pas.** Recompensa se vede devreme, dar condițiile esențiale, instrucțiunile, detaliile și confirmările concurează în aceeași pagină. După acceptare, utilizatorul trebuie să pornească proiectul și să găsească misiunea potrivită.
3. **Progres → rezultat.** Proiecte, Misiuni și Dashboard afișează progres în paralel. Un Wallet demonstrativ cu sume concrete poate fi confundat cu evidența reală a încasărilor din proiecte.

Prima livrare UX ar trebui să pună datele reale în prim-plan, să facă vizibilă activarea exact unde blochează o acțiune și să ofere o singură destinație evidentă pentru lucrul în curs.

## 2. Evaluare generală

**UX general: 4,5/10.** Scor euristic, cu încredere moderată. Evaluează cât de ușor poate un utilizator nou să înțeleagă serviciul, să se orienteze, să înceapă o oportunitate și să ajungă la următoarea acțiune. Nu reprezintă o rată de conversie sau un rezultat de testare cu participanți.

| Dimensiune | Scor | Motiv observabil |
| --- | ---: | --- |
| Orientare și navigare | 4/10 | Mai multe meniuri simultane, denumiri alternative și destinații diferite între module. |
| Intrare și activare | 4/10 | Formulare reale și selecție păstrată din Landing, cu pasul de activare slab semnalizat pe traseul generic și la acceptare. |
| Înțelegerea oportunității | 4/10 | Recompensa este vizibilă, dar volumul și repetarea textului ascund decizia. |
| Execuție și progres | 6/10 | Pași, termene și stări reale, cu o trecere greoaie de la acceptare la lucru. |
| Încredere și feedback | 4,5/10 | Avertismentele și stările reale sunt utile. Datele demonstrative din zonele de bani, progres și notificări le slăbesc credibilitatea. |

Metodă: **B** = observat în browser pe serverul local temporar, **C** = confirmat în sursa actuală, **H** = confirmat într-o captură istorică locală. **Ipoteză** = efect probabil asupra utilizatorului, fără măsurători de comportament. Prioritățile descriu severitatea efectului, independent de complexitatea remedierii.

## 3. Evaluare pe pagini și fluxuri

| Pagină / flux | Scor | Evidență | Evaluare desktop și mobil |
| --- | ---: | --- | --- |
| Landing `/` | 5 | B+C | Promisiune clară și „Începe gratuit” vizibil. Planurile reale sunt mult mai jos, după o previzualizare lungă care arată ca un cont personal. Browserul a numărat 23 de elemente neutralizate din zona demonstrativă. La 390 px, pagina nu a depășit lățimea viewportului, dar secțiunile se acumulează vertical. |
| Abonamente publice `/pricing` | 2 | B+C | Afișează Aura Free, Premium și Premium trimestrial în locul catalogului din Landing. Vizitatorul poate vedea „Planul tău actual” fără cont. Butoanele deschid o previzualizare. Diferența de ofertă există și pe mobil. |
| Înregistrare `/register` | 5,5 | B+C | Formular cu patru câmpuri obligatorii și o bifă, o acțiune principală și păstrarea numelui/emailului la erori. După alegerea Standard am ajuns aici, dar planul nu apare în formular și pasul următor e descris în text mic. Bifa pentru termeni și confidențialitate nu conține linkuri către documente. |
| Verificare email `/verify-email` | 6 | C; B doar ca vizitator | Adresa destinatară, tastatura numerică, completarea OTP, expirarea și retrimiterea sunt explicite. Fără plan selectat apare încă „Continuă în cont” după succes. Acțiunile publice din antet distrag, iar o adresă introdusă greșit nu are o cale clară de corectare pe acel ecran. |
| Autentificare `/login` | 6,5 | B+C | Formular scurt, cu creare cont și recuperare parolă. Starea cu dovadă de acces din email mărește sarcina când utilizatorul deja are dificultăți. Recuperarea ar trebui să explice limpede următorul pas, fără detalii despre existența contului. |
| Recuperare și resetare parolă | 6 | B+C pentru cerere; C pentru resetare | Cererea se reduce la un email. Ecranul de resetare cere email, parolă și confirmare. Emailul livrat, linkul expirat și succesul real nu au fost reproduse. |
| Abonamentul meu `/subscriptions` | 4,5 | C | Statusul, trialul, planurile reale și cupoanele sunt funcționale în cod. Pentru un cupon, dialogul pornește de la alegerea planului și afișează perioada și cardul indisponibil, deși cuponul decide numărul de zile. Caruselul mobil arată un plan o dată; opțiunile de acces nu pot fi comparate ușor. |
| Dashboard | 4,5 | C | Misiunea, proiectele și totalul extern confirmat au surse reale. „7 insigne” și progresul de nivel coexistă cu ele. Pentru un proiect acceptat, dar neînceput, „Nu ai misiuni în progres” este corect tehnic, dar nu ghidează spre pornire. |
| Navigare USER | 3,5 | C | Layoutul central are patru legături principale, zece secundare și cinci în bara mobilă. Pe mobil, bara de sus se ascunde, submeniul se glisează fără scrollbar, iar bara de jos include Misiuni și Wallet, dar omite Proiecte. În Abonamente bara de jos are patru destinații și pierde Profil. |
| Catalog `/opportunities` | 5 | C; B pentru accesul vizitatorului | Cardurile au recompensă, dificultate și date. Căutarea plus cinci selectoare ocupă spațiu înaintea rezultatelor. Pagina nu are buton de căutare vizibil când JS rulează și unele filtre trimit automat. Fără rezultate, mesajul sugerează ajustarea filtrelor, dar nu oferă resetare sau traseu alternativ. |
| Detalii oportunitate | 4 | C+H | Recompensa și plătitorul sunt vizibile. Urmează descrierea integrală, regulile, instrucțiunile, șabloanele, sursa și regulamentul; CTA-ul apare la final. Exemplul istoric Salt Bank arată 4 misiuni, 15 pași și 6 confirmări într-o singură pagină. Ofertelor expirate le lipsește o explicație vizibilă lângă locul acțiunii. |
| Acceptare oportunitate | 3,5 | C+H | Atestările există și trebuie păstrate. Același text poate apărea în rezumat, listă și bifă. Un cont de nivel implicit vede oferta, dar află abia după submit că îi trebuie abonament activ. Ecranul afișează eroarea, iar formularul nu reconstruiește bifele din intrarea anterioară. |
| Lista de proiecte | 5,5 | C | Progresul, starea, termenul și „Următorul pas” sunt utile. „Istoric” include și elementele deja afișate în celelalte liste, iar starea goală nu trimite spre oportunități. Pe mobil, descrierea integrală împinge acțiunea sub informații secundare. |
| Detaliu proiect | 5,5 | C | Stările, misiunile obligatorii/opționale, încasările și istoricul sunt utile. Lista nu evidențiază o misiune recomandată. După „Începe proiectul”, utilizatorul revine pe aceeași pagină și trebuie să deschidă o misiune. |
| Lista de misiuni | 5 | C | Circulație rapidă între misiuni, cu stări și următorul pas. Ca destinație egală cu Proiecte, permite două răspunsuri la „unde continui?”. Legătura cu proiectul de proveniență nu apare în lista comună. |
| Detaliu misiune și pași | 5,5 | C+H | Verificarea pe pas, starea, termenul, nota de revizie și istoricul sunt utile. Bifarea trimite un formular și reîncarcă pagina. Pașii complet parcurși rămân la fel de extinși ca cei activi. „Așteaptă pasul de referință” numește mecanismul, fără să identifice acțiunea pentru utilizator. |
| Recomandări `/referrals` | 6,5 | C | Copierea și distribuirea linkului, lista reală și paginarea sunt utile. Indicatorii sunt afișați înaintea acțiunii principale. „Cont activ” folosește starea contului, fără a distinge email verificat sau abonament activ. Separarea de câștigurile din oportunități trebuie explicată. |
| Realizări și gamificare | 3,5 | C | Insignele și nivelurile sunt într-un loc ușor de scanat, dar datele fixe apar sub „Realizările tale” și sunt preluate și în Dashboard. Acest progres nu poate fi folosit drept feedback personal verificabil. |
| Wallet `/wallet` | 2 | C | Eticheta DEMO există. Totuși, solduri ca „250 lei”, istoricul „Plătit” și aspectul unui cont financiar formează un mesaj concurent. Finanțele sunt accesibile direct din meniul principal și de pe bara mobilă. |
| Profil și setări | 5,5 | C | Numele, emailul și parola au fluxuri reale. Confirmarea parolei pentru schimbarea adresei este utilă. Limba și preferințele demonstrative apar în același formular cu salvarea reală, ceea ce creează așteptări de persistență. |
| Aplicațiile mele | 3 | C | Conexiunile sunt marcate ca simulate. „Conectează” și „Deconectează” schimbă o stare locală, ceea ce imită o integrare activă. Modulul are o destinație în meniul secundar, deși nu ajută la finalizarea unei oportunități. |
| Notificări, feedback și CTA | 4,5 | C | În layoutul proiectelor, clopoțelul afișează mementouri reale și are stare goală. În Wallet, Setări și Realizări același simbol deschide un mesaj demo. Mementoul poate fi setat din misiune, dar pagina nu explică lângă buton unde va fi livrat. |

## 4. Ce funcționează și merită păstrat

- **Recompensa, plătitorul și caracterul condiționat** apar în detalii. Decizia trebuie să păstreze aceste informații, împreună cu eligibilitatea reală, costurile, termenele și sursa.
- **Proiectele și misiunile păstrează progres, stări și istoric.** Lista arată deja următorul pas. Acesta este un punct de plecare pentru aceeași acțiune în Dashboard și în detaliu.
- **Planul ales din Landing se păstrează în sesiune**, iar verificarea emailului poate trimite direct spre abonament. Păstrează continuitatea când simplifici onboardingul.
- **Verificarea emailului și securitatea contului** au mesaje și controale reale. Păstrează temporizările, confirmarea parolei și opțiunea clară de retrimitere.
- **Copierea și distribuirea recomandărilor**, evidența încasărilor externe și regulile de eligibilitate sunt utile fără un strat demonstrativ.

## 5. Probleme critice, dovezi și impact

| ID | Prioritate | Observație verificată | Impact asupra utilizatorului |
| --- | --- | --- | --- |
| F01 | **P0** | `/pricing` arată alt catalog și un „plan actual” pentru vizitatori. Landing folosește catalogul real. **B+C** | Decizia de abonare se poate baza pe o ofertă indisponibilă. Traseul din antet se termină într-o simulare. |
| F02 | **P0** | Wallet arată sume și plăți fictive, iar Dashboard arată total extern real lângă progres demonstrativ. **C** | Risc de a confunda bani datorați sau încasați cu exemple. Nu am măsurat câți utilizatori fac această confuzie. |
| F03 | **P1** | Oferta de nivel 1 poate fi vizibilă fără abonament, dar acceptarea e refuzată doar după submit. Lipsesc CTA către activare și revenirea la ofertă. **C** | Utilizatorul poate completa confirmări înainte de a afla precondiția. |
| F04 | **P1** | Proiecte lipsește din bara mobilă centrală. Profilul dispare din bara Abonamente. „Aura”, „Dashboard”, „Finanțe” și „Wallet” se suprapun ca destinații. **C** | Sarcina principală e ascunsă în variante de meniu și în derulare laterală. |
| F05 | **P1** | Salt Bank avea șase reguli, patru misiuni și 15 pași. Condițiile erau repetate în bife, cu CTA la final. **H+C** | Cost de citire și risc de confirmare mecanică. Termenul unei oferte și termenul unei acțiuni bancare pot fi confundate. |
| F06 | **P1** | După Acceptă urmează Start, apoi deschiderea misiunii. Dashboard selectează cea mai veche misiune nefinalizată a unui proiect activ, inclusiv una aflată în verificare. **C** | Poate recomanda așteptarea în timp ce există lucru disponibil în altă misiune. |
| F07 | **P1** | Înregistrarea nu afișează selecția și checkboxul de acord nu oferă acces la documente. **B+C** | Alegerea pare pierdută, iar utilizatorul este invitat să accepte documente pe care formularul nu le leagă. |
| F08 | **P1** | Clopoțelul schimbă sensul după pagină. Unele preferințe apar în formularul de salvare, dar sunt demonstrative. **C** | Utilizatorul nu poate deduce ce preferințe sunt salvate sau de unde provine un memento. |
| F09 | **P2** | Filtrele au praguri fixe, trimit automat selectoarele și nu oferă o resetare evidentă la zero rezultate. **C** | Pe mobil, valoarea fiecărui filtru nu justifică mereu spațiul și reîncărcarea consumate. |
| F10 | **P2** | Formularul de cupon acceptă formate precise, dar câmpul nu spune dinainte ce format este așteptat. Mesajele detaliate pentru plan inactiv, cupon indisponibil, programare conflictuală și limitare ies prin același loc. **C** | Utilizatorii au cost mare de recuperare după eroare. Păstrează protecțiile împotriva ghicirii, dar oferă acțiuni sigure precum verificarea planului sau revenirea la ofertă. |
| F11 | **P2** | Unele formulare au etichete vizuale fără asociere programatică, progresul este redat prin bare vizuale, iar starea activă a navigării depinde deseori de clasă CSS. **C** | Risc la navigare cu tastatura sau cititorul de ecran. A11y completă necesită testare separată. |
| F12 | **P3** | Sloganuri și ornamente repetitive în preajma sarcinilor, filtre duplicative pentru „Istoric”, jocuri de progres demonstrative. **C** | Un spațiu mai calm ar lăsa loc informațiilor indispensabile, în special pe ecrane mici. |

### Efort estimat pe traseele actuale

Numerele reprezintă acțiuni deliberate în AURA. Câmpurile completate, deschiderea emailului, scrollul, citirea și timpul de așteptare sunt separate. **B** marchează doar trecerea reprodusă, nu completarea unui flux cu scrieri.

| Traseu | Acțiuni minime | Cost ascuns |
| --- | ---: | --- |
| Landing → plan ales → formular | **1** pentru „Alege”, urmat de redirect la `/register` **B** | Planurile sunt mult sub primul ecran. |
| Landing → cont verificat → Dashboard, fără plan | **5**: Începe, bifează acordul, Creează cont, Verifică, Continuă **C** | Patru câmpuri, codul email și o posibilă retrimitere. |
| Dashboard → trial | **2** după găsirea „Abonament”: deschide, „Testează gratuit” **C** | Pe mobil, legătura e în submeniul glisant. După oferta blocată traseul de revenire e manual. |
| Dashboard → activare cupon | **3** după găsirea „Abonament”: deschide, Alege, Activează **C** | Planul cuponului trebuie cunoscut, codul trebuie introdus, iar la schimbarea planului apar acțiuni suplimentare. |
| Catalog → primul pas bifat | **5 + N**: detaliu, Acceptă, Începe, deschide misiunea, bifează pasul; **N** confirmări de eligibilitate **C+H** | Cu cele șase bife Salt Bank, **11 acțiuni** din catalog, dacă trec toate verificările. Din Dashboard poate fi încă una pentru catalog. |
| Dashboard → continuă o misiune deja activă | **1**, apoi o acțiune pe pas **C** | Dacă sugestia e în verificare sau alt proiect este mai urgent, trebuie căutată alternativa. |
| Recomandări → copiază link | **1** după deschiderea paginii **C** | Descoperirea paginii cere meniul secundar în layoutul central. |

## 6. Simplificări recomandate

1. **Un catalog public de planuri, o singură destinație.** Leagă „Abonamente” de catalogul real. Înainte de înregistrare, arată ce poate face utilizatorul imediat și cum se activează accesul disponibil.
2. **O acțiune principală pe stare.** „Explorează oportunitățile”, „Începe proiectul”, „Continuă cu [pas]” sau „Vezi ce se verifică”. Când o acțiune este blocată, arată motivul și butonul care rezolvă blocajul.
3. **Afișare progresivă.** În listă, rezumă recompensa, implicarea, termenul și condiția care schimbă decizia. În detaliu, mută sub secțiuni expandabile textul integral, istoricul și pașii deja parcurși. Păstrează avertismentele, costurile și condițiile decisive vizibile.
4. **Separă accesul de ofertă.** „Folosește un cupon” și „Activează trialul” ca acțiuni directe, cu starea și data de expirare. Compararea planurilor poate deveni o acțiune secundară. Metoda de plată indisponibilă poate fi descrisă printr-un text scurt.
5. **Încredere prin denumiri exacte.** „Câștiguri externe confirmate” pentru datele reale. Într-o secțiune demonstrativă, pune „Exemplu de interfață” înaintea valorilor și evită persoana a doua. În contul utilizatorului, fiecare buton care promite salvare trebuie să indice starea reală a operațiunii.

## 7. Navigare propusă

Aceleași destinații și aceeași ordine pe desktop și pe mobil. Bara mobilă are patru intrări, fiecare cu text și stare activă accesibilă.

| Intrare | Conținut |
| --- | --- |
| **Acasă** | O acțiune recomandată, ce urmează și cel mult câteva oportunități disponibile. |
| **Oportunități** | Catalog, căutare, filtre secundare, detalii. |
| **Proiectele mele** | Proiecte în curs și finalizate. Lista „De făcut” și misiunile sunt aici, cu legături directe din Acasă și notificări. |
| **Cont** | Abonament, Recomandă AURA, Profil și setări, deconectare. Încasări externe dacă există date. |

Păstrează clopoțelul pentru notificări reale. Folosește „Înapoi la proiect” și „Înapoi la rezultate” în detalii, cu păstrarea filtrelor. Mută Wallet, Realizări și Aplicațiile mele în afara navigării principale până când au o funcție clară în contul real. Păstrarea rutelor existente permite accesul prin legături deja distribuite.

## 8. Flux propus pentru utilizatorii noi

1. **Intrare.** Vizitatorul poate vedea ce oferă AURA, condițiile esențiale și un exemplu clar marcat. „Creează cont” rămâne o acțiune proprie, iar un plan sau o oportunitate selectată este afișată și păstrată în timpul înregistrării.
2. **Cont și verificare.** Formularul spune „Urmează verificarea emailului” înainte de submit. Verificarea arată adresa, opțiunea de corectare și situația codului. După succes, utilizatorul ajunge direct la destinația relevantă.
3. **Acces, când este necesar.** În același parcurs apar trialul eligibil, câmpul de cupon sau opțiunea de a explora. Arată explicit când este nevoie de abonament pentru a accepta. După activare, întoarce utilizatorul la oferta selectată.
4. **Primul proiect.** „Verifică dacă te califici” conduce la confirmări concise, fără repetarea paragrafelor. După acceptare, arată imediat primul pas. Dacă separarea între acceptare și pornire are o regulă de produs sau declanșează termene, păstrează confirmarea necesară, dar pune-o lângă acțiunea de start cu efectul explicat.
5. **Revenire.** La reîntoarcere, Acasă arată primul pas pe care utilizatorul îl poate face, termenul și starea. Prioritizează pașii efectivi peste misiunile care așteaptă validare. O stare goală trebuie să aibă o destinație clară.

Țintă de prototip: de la Dashboard la prima acțiune fără precondiții, maximum două navigări. Dacă este necesară activarea, utilizatorul află înainte de confirmări și revine la aceeași ofertă după acces. Nu trata ținta drept o măsurătoare actuală.

## 9. Model de prezentare simplificată a oportunității

**Primul ecran:** titlu, recompensă potențială și plătitor, termen exact, costuri sau obligații, pentru cine este, apoi acțiunea disponibilă. Afișează text explicit dacă datele despre un cost nu sunt confirmate. Diferențiază durata efortului activ de așteptarea validării.

**Checklist scurt.** Exemplu de structură inspirat de scenariul Revolut dat în brief, fără valori inventate:

1. Recomandă un prieten folosind linkul eligibil.
2. Prietenul își deschide contul eligibil.
3. Prietenul alimentează contul în condițiile campaniei.
4. Prietenul comandă cardul fizic. Afișează costul aplicabil lângă acest pas dacă există.
5. Prietenul efectuează cumpărăturile necesare până la termen. Afișează numărul, suma și excluderile imediat ce sunt cunoscute.

**Sub checklist:** cine primește recompensa, condițiile complete, sursa, ce dovadă se cere și ce se întâmplă dacă o etapă rămâne nevalidată. Termenii oficiali rămân accesibili. Dacă un prieten trebuie să facă o acțiune, spune asta explicit. Pe mobil, o singură acțiune principală duce la etapa următoare, vizibilă fără derulare prin misiunile încheiate.

## 10. Plan prioritizat și complexitate tehnică

Estimare orientativă pentru un dezvoltator, în zile de lucru, cu verificări manuale desktop/mobil. Nu include implementarea Wallet, Stripe, instrumentare deja absentă sau schimbarea identității cromatice.

| Ordine | Prioritate | Livrare | Complexitate | Verificare de acceptare |
| --- | --- | --- | --- | --- |
| 1 | **P0** | Unifică intrarea publică spre planurile reale. Scoate soldurile și plățile fictive din zona financiară personală. | Mică · 1–2 zile | Aceleași planuri și destinații din antet și Landing. Nicio sumă demo prezentată drept rezultat personal. |
| 2 | **P1** | Navigare comună cu patru destinații și CTA clar din stările goale. Un singur clopoțel cu date reale. | Medie · 2–4 zile | Proiectele la un tap din orice pagină mobilă. Același element activ între module, inclusiv pe detalii. |
| 3 | **P1** | Păstrează și afișează intenția prin cont și email. Activează din context, înainte de bife, apoi revino la ofertă. Expune documentele din formular. | Medie–mare · 4–7 zile | Trasee distincte pentru plan ales, ofertă aleasă, cont existent, trial disponibil, cupon și acces indisponibil. |
| 4 | **P1** | Rezumat standard în detalii, confirmări la locul deciziei și primul pas evidențiat după acceptare. Clarifică expirarea și condițiile. | Medie–mare · 4–8 zile | Utilizatorul poate spune rapid ce primește, ce plătește, dacă se califică și ce face în continuare. Verifică cel puțin o ofertă simplă și una cu condiții multiple. |
| 5 | **P2** | Filtre compacte, resetare, mesaje specifice și accesibilitate pentru ecranele prioritare. | Medie · 2–4 zile | Administrarea erorii cu tastatura, zoom 200%, formularul pe mobil, stări fără rezultate și fără proiecte. |
| 6 | **P3** | Reanalizează utilitatea nivelurilor, insignelor și integrărilor în funcție de comportamentul real. | Mică pentru mutare · 1–2 zile | Zonele secundare nu reduc vizibilitatea următorului pas și fiecare acțiune are un rezultat explicabil. |

Măsoară înainte și după, dacă există instrumentare: conversia din Landing în cont verificat, procentul care folosește accesul după un blocaj, rata de acceptare, timpul până la primul pas, revenirea după verificare și succesul pe mobil față de desktop. Completează cu câteva observații de utilizare pe telefoane reale. Valorile actuale nu au fost disponibile în acest audit.

## 11. Limite și surse

- **Browser local:** Landing, `/pricing`, Register, Login și cererea de resetare la **1440×900** și **390×844**. La 390 px am verificat și redirecturile pentru vizitatori către Login la `/opportunities` și `/verify-email`, respectiv trecerea rutei Standard către Register. Am folosit sesiuni și cache în memorie pentru această verificare.
- **Cod, cu dovezi istorice unde existau:** starea autentificată a Dashboard, Abonamente, Oportunități, Proiecte, Misiuni, Recomandări, Realizări, Wallet, Integrări și Setări. Captura istorică Salt Bank confirmă structura detaliului și a confirmărilor, nu disponibilitatea actuală a proiectului. Navigarea mobilă autentificată a fost evaluată din markup și breakpointuri, deci scorurile respective au încredere mai mică.
- **Trasee fără execuție efectivă:** creare cont, primire sau introducere OTP, autentificare, resetare completă, trial, consum cupon, acceptare proiect, schimbare de stare, dovadă, memento și editare profil. Ar fi necesare o sesiune de test și date controlate. Nu am folosit date reale de cont pentru aceste operațiuni. Nu am măsurat timpi reali, compatibilitate iOS/Android, accesibilitate completă sau comportamentul producției.
- Surse principale: [rutele USER](routes/aura-demo.php), [navigarea proiectelor](resources/views/layouts/project.blade.php), [CSS responsive](public/assets/aura/app.css), [Landing](resources/views/public/landing.blade.php), [Pricing](resources/views/public/pricing.blade.php), [Register](resources/views/auth/register.blade.php), [verificarea emailului](app/Http/Controllers/EmailVerificationController.php), [abonamente](app/Http/Controllers/SubscriptionController.php), [catalog și proiecte](app/Http/Controllers/ProjectController.php), [regula de acces](app/Services/SubscriptionAccess.php), [detaliu ofertă](resources/views/opportunities/show.blade.php), [detaliu misiune](resources/views/missions/show.blade.php), [Wallet](resources/views/wallet/index.blade.php).

Auditul evaluează structura și interacțiunile existente. Light/Dark Mode rămâne o inițiativă separată.
