<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SaltBankBlackFriday2026Seeder extends Seeder
{
    private const TITLE = 'Salt Bank — Black Friday 2026 — 300 lei cashback';

    private const SOURCE_URL = 'https://salt.bank/storage/app/efsfiles/media/black-friday/2026/Regulament_Black_Friday_MGM_2026.pdf';

    public function run(): void
    {
        DB::transaction(function (): void {
            $category = Category::firstOrCreate(['slug' => 'promotii-bancare'], [
                'name' => 'Promoții bancare',
                'description' => 'Oportunități oferite de instituții bancare, precum bonusuri de bun venit, cashback și campanii promoționale.',
                'visibility' => 'all',
                'status' => 'active',
            ]);
            // The unique category row serializes repeated imports of this offer.
            $category = Category::lockForUpdate()->findOrFail($category->id);

            if (Opportunity::where('title', self::TITLE)->orWhere('source_url', self::SOURCE_URL)
                ->lockForUpdate()->first(['id'])) {
                $this->command?->info('Oferta Salt Bank Black Friday 2026 există deja.');

                return;
            }

            if ($category->status !== 'active' || $category->visibility !== 'all') {
                throw new RuntimeException('Categoria Promoții bancare trebuie să fie activă și vizibilă tuturor pentru acest import.');
            }

            $admin = User::where('role', 'super_admin')->where('status', 'active')->orderBy('id')->first(['id']);
            if ($admin === null) {
                throw new RuntimeException('Importul necesită un cont SUPER ADMIN activ pentru autorul oportunității.');
            }

            $opportunity = $category->opportunities()->create([
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
                'title' => self::TITLE,
                'source_url' => self::SOURCE_URL,
                'summary' => 'Deschide un cont Salt Bank în perioada campaniei Black Friday 2026, efectuează tranzacții eligibile cumulate de minimum 300 lei în primele 30 de zile de la deschiderea contului și poți primi 300 lei cashback, conform regulamentului oficial. Recompensa este condiționată de îndeplinirea tuturor criteriilor și nu este garantată.',
                'provider' => 'Salt Bank S.A.',
                'source_label' => 'Salt Bank — Regulamentul oficial Black Friday Welcome Offer',
                'geography' => 'România',
                'eligibility_text' => 'Persoană fizică de minimum 18 ani, cu rezidență fiscală în România. Deschiderea relației bancare în intervalul 13.11.2026, 00:00–20.11.2026, 23:59. Tranzacții eligibile cumulate de minimum 300 RON în primele 30 de zile de la deschiderea contului. Păstrarea contului deschis și a calității de client activ până la acordarea cashbackului, cel târziu la 30.12.2026. Respectarea excluderilor și a tuturor condițiilor regulamentului. Alte beneficii bazate pe codul de recomandare nu se cumulează: se aplică beneficiul cel mai favorabil.',
                'instructions' => 'Pagina oficială a campaniei: https://www.salt.bank/black-friday'."\n".'Regulament oficial PDF: https://salt.bank/storage/app/efsfiles/media/black-friday/2026/Regulament_Black_Friday_MGM_2026.pdf'."\n".'Deschide sursa oficială prin linkul de la «Sursă» de mai jos. Urmează cele patru misiuni. Confirmă deschiderea contului în AURA chiar în ziua deschiderii: termenul de 30 de zile este calculat de AURA de la bifarea acelei confirmări, pe baza declarației tale, și nu reprezintă o dată bancară verificată. Termenul oficial curge de la deschiderea contului. Închiderea înscrierilor la 20 noiembrie este distinctă de termenul individual de cumpărături.'."\n".'Durata afișată de 15 minute este o estimare editorială AURA pentru citirea și organizarea inițială a pașilor; nu include timpul de deschidere bancară, cumpărăturile ori așteptarea cashbackului.'."\n".'Înscrierile la bancă s-au încheiat la 20.11.2026. Poți începe proiectul AURA până la finalul zilei de 30.12.2026 doar dacă ai deschis deja contul în perioada eligibilă. Dacă îți înregistrezi confirmarea în AURA după deschidere, verifică termenul oficial folosind data efectivă a deschiderii.',
                'terms' => 'AURA prezintă o sinteză informativă a oportunității. Condițiile oficiale sunt stabilite exclusiv de Salt Bank și pot fi modificate conform regulamentului campaniei. Verifică documentele oficiale înainte de participare. AURA nu este organizatorul campaniei și nu garantează eligibilitatea sau acordarea recompensei.'."\n".'Regulamentul oficial Salt Bank prevalează asupra rezumatului AURA. PDF: https://salt.bank/storage/app/efsfiles/media/black-friday/2026/Regulament_Black_Friday_MGM_2026.pdf'."\n".'Pagina campaniei: https://www.salt.bank/black-friday'."\n".'Înscrieri: 13.11.2026, 00:00–20.11.2026, 23:59, ora României. Cashback: 300 RON pentru tranzacții eligibile de minimum 300 RON în primele 30 de zile de la deschiderea contului. Data finală de acordare: 30.12.2026. Tranzacțiile eligibile au valoarea minimă de 1 leu/1 euro și respectă regulamentul. Sunt excluse categoriile MCC 4829, 6010, 6011, 6012, 6050, 6051, 6529, 6530, 6532, 6533, 6534, 6536, 6537, 6538, 6540, 6211, 7801, 7995 și 9754 (transferuri, numerar/cvasi-numerar, valori mobiliare, jocuri de noroc etc.). Conturile/cardurile anulate sau blocate pot determina anularea ori suspendarea beneficiilor neplătite. Se acordă beneficiul cel mai favorabil pentru promoții suprapuse bazate pe recomandare. Păstrează calitatea de client activ până la acordare. Verifică toate detaliile în documentul original.',
                'difficulty' => 'Ușor',
                'estimated_minutes' => 15,
                'icon' => 'bank',
                'is_featured' => 0,
                'starts_at' => '2026-11-12 22:00:00',
                // Store the end of AURA enrollment as midnight in Romania, converted to UTC.
                'ends_at' => '2026-12-30 22:00:00',
                'project_due_on' => '2026-12-30',
                'status' => 'published',
                'version' => 2,
                'reward_type' => 'cashback',
                'reward_unit' => 'money',
                'reward_amount_minor' => 30000,
                'reward_currency' => 'RON',
                'reward_label' => '300 lei cashback extern, condiționat',
                'payer' => 'Salt Bank',
                'min_access_level' => 1,
                'participation_mode' => 'explorer',
            ]);

            foreach ($this->eligibilityRules() as $rule) {
                $opportunity->rules()->create($rule + [
                    'category_id' => $category->id,
                    'geography' => 'România',
                    'country_code' => 'RO',
                    'visibility' => 'all',
                    'check_method' => 'user_attestation',
                    'status' => 'active',
                ]);
            }

            $accountConfirmationStepId = null;
            foreach ($this->missionDefinitions() as $definition) {
                $steps = $definition['steps'];
                unset($definition['steps']);
                if (isset($definition['deadline_anchor'])) {
                    $definition['anchor_step_id'] = $accountConfirmationStepId;
                }
                $mission = $opportunity->templates()->create($definition + [
                    'assign_on_accept' => true,
                    'is_required' => true,
                    'verification_method' => 'user_declaration',
                    'status' => 'active',
                ]);

                foreach ($steps as $step) {
                    $created = $mission->steps()->create($step + ['verification_method' => 'user_declaration', 'status' => 'active']);
                    // Old anchor step ID 8 belongs to mission 2, position 5.
                    if ($definition['position'] === 2 && $step['position'] === 5) {
                        $accountConfirmationStepId = $created->id;
                    }
                }
            }
        }, 3);
    }

    /** @return list<array{name: string, conditions_text: string}> */
    private function eligibilityRules(): array
    {
        return [
            [
                'name' => 'Vârstă și rezidență fiscală',
                'conditions_text' => 'Declar că sunt persoană fizică de minimum 18 ani și am rezidența fiscală în România, conform definiției Client Activ din regulamentul Salt Bank.',
            ],
            [
                'name' => 'Client nou și perioada de deschidere',
                'conditions_text' => 'Confirm că particip prin deschiderea unei noi relații bancare Salt Bank în perioada 13.11.2026, ora 00:00–20.11.2026, ora 23:59. Acceptarea în AURA nu echivalează cu deschiderea contului bancar.',
            ],
            [
                'name' => 'Tranzacții eligibile de minimum 300 RON',
                'conditions_text' => 'Am înțeles că trebuie să efectuez tranzacții eligibile cumulate de minimum 300 RON în primele 30 de zile de la deschiderea contului. Voi confirma realizarea lor prin misiune. Această declarație la acceptare nu afirmă că tranzacțiile au fost deja efectuate.',
            ],
            [
                'name' => 'Cont deschis și client activ până la acordare',
                'conditions_text' => 'Mă angajez să păstrez contul deschis și calitatea de client activ până la acordarea cashbackului, cu data finală 30.12.2026. Am înțeles efectele anulării sau blocării contului/cardului prevăzute în regulament.',
            ],
            [
                'name' => 'Excluderi de tranzacții',
                'conditions_text' => 'Am citit lista tranzacțiilor neeligibile din regulament. Transferurile, retragerile de numerar, cvasi-numerarul, operațiunile cu valori mobiliare și jocurile de noroc din categoriile MCC excluse nu contribuie la pragul eligibil.',
            ],
            [
                'name' => 'Regulament oficial și beneficiul cel mai favorabil',
                'conditions_text' => 'Accept toate condițiile regulamentului oficial Salt Bank. Am înțeles că beneficiile acestei campanii nu se cumulează cu alte oferte bazate pe codul de recomandare și se aplică beneficiul cel mai favorabil. AURA nu garantează eligibilitatea ori plata.',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function missionDefinitions(): array
    {
        return [
            [
                'title' => 'Citește regulamentul și verifică eligibilitatea',
                'description' => 'Citește regulamentul oficial Salt Bank și confirmă eligibilitatea înainte de deschiderea contului. Regulamentul oficial prevalează asupra sintezei AURA.',
                'required_action' => 'Parcurge documentul oficial și declară personal că îndeplinești condițiile.',
                'position' => 1,
                'steps' => [
                    [
                        'position' => 1,
                        'title' => 'Deschide regulamentul oficial Salt Bank',
                        'description' => 'Regulamentul oficial «Black Friday Welcome Offer».',
                        'instructions' => 'PDF oficial: https://salt.bank/storage/app/efsfiles/media/black-friday/2026/Regulament_Black_Friday_MGM_2026.pdf'."\n".'În detaliul oportunității, apasă linkul de la «Sursă» pentru a deschide PDF-ul original. Citește documentul înainte de a bifa acest pas.',
                    ],
                    [
                        'position' => 2,
                        'title' => 'Citește condițiile de participare',
                        'description' => 'Parcurge definițiile, condițiile de participare, termenele și excluderile.',
                        'instructions' => 'Verifică paginile 2–4 din PDF. Citește pragul de 300 RON, fereastra de 30 de zile, tranzacțiile neeligibile și regula beneficiului cel mai favorabil. https://salt.bank/storage/app/efsfiles/media/black-friday/2026/Regulament_Black_Friday_MGM_2026.pdf',
                    ],
                    [
                        'position' => 3,
                        'title' => 'Confirmă că îndeplinești condițiile de eligibilitate',
                        'description' => 'Declarație personală privind eligibilitatea.',
                        'instructions' => 'Confirmă vârsta de minimum 18 ani, rezidența fiscală în România și îndeplinirea condițiilor aplicabile. Păstrează contul activ până la acordare. AURA prezintă o sinteză informativă a oportunității. Condițiile oficiale sunt stabilite exclusiv de Salt Bank și pot fi modificate conform regulamentului campaniei. Verifică documentele oficiale înainte de participare. AURA nu este organizatorul campaniei și nu garantează eligibilitatea sau acordarea recompensei.',
                    ],
                ],
            ],
            [
                'title' => 'Deschide contul Salt Bank',
                'description' => 'Deschide contul exclusiv în aplicația oficială Salt Bank în intervalul 13.11.2026, 00:00–20.11.2026, 23:59. Confirmă data prin declarație proprie. Pagina oficială: https://www.salt.bank/black-friday',
                'deadline_on' => '2026-11-20',
                'position' => 2,
                'steps' => [
                    [
                        'position' => 1,
                        'title' => 'Accesează pagina oficială Salt Bank',
                        'description' => 'Folosește site-ul oficial Salt Bank.',
                        'instructions' => 'Pagina campaniei: https://www.salt.bank/black-friday'."\n".'Pagina băncii: https://salt.bank'."\n".'Regulamentul oficial: https://salt.bank/storage/app/efsfiles/media/black-friday/2026/Regulament_Black_Friday_MGM_2026.pdf',
                    ],
                    [
                        'position' => 2,
                        'title' => 'Instalează aplicația oficială',
                        'description' => 'Instalează aplicația Salt Bank din magazinul oficial al dispozitivului.',
                        'instructions' => 'Accesează magazinul de aplicații pornind de pe site-ul oficial Salt Bank. Verifică editorul aplicației. Nu transmite parole, coduri de autentificare sau date bancare către AURA.',
                    ],
                    [
                        'position' => 3,
                        'title' => 'Folosește codul de recomandare primit, dacă este aplicabil',
                        'description' => 'Respectă instrucțiunile oficiale privind codul de recomandare.',
                        'instructions' => 'Dacă ai primit un cod valid pentru înscriere, folosește-l conform regulamentului. AURA nu furnizează aici un cod. Beneficiile ofertelor suprapuse nu se cumulează; se aplică beneficiul cel mai favorabil.',
                    ],
                    [
                        'position' => 4,
                        'title' => 'Finalizează deschiderea contului',
                        'description' => 'Finalizează înrolarea exclusiv în aplicația oficială Salt Bank.',
                        'instructions' => 'Încheie relația bancară în perioada 13–20 noiembrie 2026. Păstrează pentru tine confirmarea și data deschiderii. Nu încărca documente de identitate sau date bancare sensibile în AURA.',
                    ],
                    [
                        'position' => 5,
                        'title' => 'Confirmă că ai deschis contul în perioada campaniei',
                        'description' => 'Declarație a utilizatorului privind deschiderea contului până la 20.11.2026, ora 23:59.',
                        'instructions' => 'Bifează în AURA în aceeași zi în care contul a fost deschis efectiv. Această bifare este reperul tehnic pentru termenul de 30 de zile al misiunii de cumpărături. AURA nu verifică data bancară: termenul oficial curge de la deschiderea contului, chiar dacă bifezi mai târziu. Consultă data confirmată de Salt Bank.',
                    ],
                ],
            ],
            [
                'title' => 'Efectuează tranzacțiile eligibile',
                'description' => 'Realizează cumpărături eligibile de minimum 300 RON cumulat în primele 30 de zile de la deschiderea contului. AURA calculează orientativ termenul de la bifarea confirmării deschiderii contului, pe baza declarației utilizatorului. Termenul oficial rămâne cel de la deschiderea bancară efectivă.',
                'deadline_anchor' => 'step_checked',
                'deadline_after_days' => 30,
                'position' => 3,
                'steps' => [
                    [
                        'position' => 1,
                        'title' => 'Citește secțiunea despre tranzacțiile eligibile din regulament',
                        'description' => 'Citește definițiile și excluderile de la paginile 2–3.',
                        'instructions' => 'PDF oficial: https://salt.bank/storage/app/efsfiles/media/black-friday/2026/Regulament_Black_Friday_MGM_2026.pdf'."\n".'Verifică lista MCC exclusă, pragul minim pe tranzacție (1 leu/1 euro) și pragul cumulat de minimum 300 RON. În oportunitate, linkul «Sursă» deschide PDF-ul.',
                    ],
                    [
                        'position' => 2,
                        'title' => 'Efectuează cumpărături eligibile',
                        'description' => 'Folosește cardul Salt pentru cumpărături eligibile.',
                        'instructions' => 'Efectuează plățile conform regulamentului în primele 30 de zile de la deschiderea contului. Transferurile, numerarul/cvasi-numerarul, valorile mobiliare și jocurile de noroc din categoriile excluse nu sunt eligibile.',
                    ],
                    [
                        'position' => 3,
                        'title' => 'Confirmă că ai realizat tranzacții cumulate de minimum 300 lei',
                        'description' => 'Declară îndeplinirea pragului cumulat de minimum 300 RON.',
                        'instructions' => 'Consultă tranzacțiile din aplicația Salt Bank. Bifează numai după efectuarea tranzacțiilor eligibile. AURA păstrează declarația ta, fără verificare bancară automată.',
                    ],
                    [
                        'position' => 4,
                        'title' => 'Confirmă îndeplinirea condițiilor în perioada permisă',
                        'description' => 'Verifică termenul oficial de 30 de zile de la deschiderea contului.',
                        'instructions' => 'Termenul AURA este calculat de la bifarea pasului «Confirmă că ai deschis contul în perioada campaniei». Acea dată este declarată și nu este verificată bancar. Dacă ai bifat ulterior, respectă în continuare termenul mai devreme calculat de la deschiderea efectivă a contului. https://salt.bank/storage/app/efsfiles/media/black-friday/2026/Regulament_Black_Friday_MGM_2026.pdf',
                    ],
                ],
            ],
            [
                'title' => 'Confirmă primirea cashbackului',
                'description' => 'Verifică acordarea cashbackului în aplicația oficială Salt Bank și declară suma efectiv încasată. Data-limită oficială de acordare: 30 decembrie 2026. Încasarea este informativă și independentă de Wallet.',
                'deadline_on' => '2026-12-30',
                'position' => 4,
                'steps' => [
                    [
                        'position' => 1,
                        'title' => 'Verifică în aplicația Salt Bank acordarea cashbackului',
                        'description' => 'Verifică în aplicația oficială sau în extrasul de cont acordarea cashbackului.',
                        'instructions' => 'Regulamentul indică data finală de acordare 30.12.2026, cu respectarea tuturor condițiilor. Păstrează contul deschis și calitatea de client activ până la acordare. https://salt.bank/storage/app/efsfiles/media/black-friday/2026/Regulament_Black_Friday_MGM_2026.pdf',
                    ],
                    [
                        'position' => 2,
                        'title' => 'Confirmă suma efectiv primită',
                        'description' => 'Introdu suma încasată efectiv și moneda RON în câmpurile acestui pas.',
                        'instructions' => 'Completează suma efectiv primită înainte de a bifa. Recompensa estimată este 300 RON. AURA va înregistra separat încasarea externă declarată; nu o transferă în Wallet. Dacă suma diferă de estimare, declară valoarea reală și consultă Salt Bank pentru clarificări.',
                        'confirms_receipt' => true,
                    ],
                    [
                        'position' => 3,
                        'title' => 'Bifează confirmarea încasării',
                        'description' => 'Confirmă că ai verificat primirea cashbackului și ai înregistrat suma efectiv primită.',
                        'instructions' => 'Bifează după verificarea încasării și după completarea sumei la pasul anterior. Această ultimă confirmare finalizează misiunea. Proiectul se va finaliza automat când toate cele patru misiuni obligatorii sunt finalizate.',
                    ],
                ],
            ],
        ];
    }
}
