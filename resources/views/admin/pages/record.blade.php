@extends('admin.layouts.app')
@section('content')
@if($realPlanForm ?? false)
<div class="admin-heading">
    <div><h1>{{ $plan->exists ? 'Editează '.$plan->name : 'Adaugă abonament' }}</h1><p>Completează datele și salvează modificările.</p></div>
    <a class="btn admin-btn secondary" href="{{ route('aura.admin.plans') }}">Înapoi</a>
</div>
<x-aura-admin::card class="admin-card">
    <form id="record-form" class="admin-form" method="POST" action="{{ $plan->exists ? route('aura.admin.plans.update', $plan) : route('aura.admin.plans.store') }}" data-real-plan-form>
        @csrf
        @if($plan->exists)
            @method('PATCH')
            <input type="hidden" name="version" value="{{ old('version', $plan->version) }}">
        @endif
        <input type="hidden" name="plan_form" value="1">
        <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
        <div>
            <label for="field-name">Nume abonament *</label>
            <input id="field-name" name="name" type="text" maxlength="120" value="{{ old('name', $plan->name) }}" required>
        </div>
        <div>
            <label for="field-access_level">Nivel numeric de acces *</label>
            <input id="field-access_level" name="access_level" type="number" min="1" max="1000000" step="1" value="{{ old('access_level', $plan->access_level) }}" required>
        </div>
        <div>
            <label for="field-monthly_price">Preț lunar (lei) *</label>
            <input id="field-monthly_price" name="monthly_price" type="text" inputmode="decimal" value="{{ old('monthly_price', $plan->exists ? number_format($plan->monthly_price_minor / 100, 2, '.', '') : '') }}" required>
        </div>
        <div>
            <label for="field-quarterly_price">Preț trimestrial (lei) *</label>
            <input id="field-quarterly_price" name="quarterly_price" type="text" inputmode="decimal" value="{{ old('quarterly_price', $plan->exists ? number_format($plan->quarterly_price_minor / 100, 2, '.', '') : '') }}" required>
        </div>
        @foreach([
            'max_projects' => 'Maximum proiecte simultane',
            'max_explorer_projects' => 'Maximum proiecte Explorator',
            'max_mentor_projects' => 'Maximum proiecte Mentor',
        ] as $field => $label)
            @php
                $unlimited = (string) old($field.'_unlimited', $plan->exists && $plan->{$field} === null ? '1' : '0') === '1';
            @endphp
            <div>
                <label for="field-{{ $field }}">{{ $label }} *</label>
                <input id="field-{{ $field }}" name="{{ $field }}" type="number" min="0" max="1000000" step="1" value="{{ old($field, $plan->{$field}) }}" title="0 = proiectele de acest tip nu sunt permise" @required(!$unlimited) @disabled($unlimited)>
                <input type="hidden" name="{{ $field }}_unlimited" value="0">
                <label class="field"><span><input type="checkbox" name="{{ $field }}_unlimited" value="1" data-unlimited-for="field-{{ $field }}" @checked($unlimited)> Nelimitat</span></label>
            </div>
        @endforeach
        <div>
            <label for="field-trial_days">Trial gratuit — număr de zile (0 = dezactivat) *</label>
            <input id="field-trial_days" name="trial_days" type="number" min="0" max="3650" step="1" value="{{ old('trial_days', $plan->trial_days) }}" required>
        </div>
        <div class="full">
            <label for="field-benefits_text">Beneficii — câte unul pe linie</label>
            <textarea id="field-benefits_text" name="benefits_text">{{ old('benefits_text', implode("\n", $plan->benefits ?? [])) }}</textarea>
        </div>
        <div>
            <label for="field-is_active">Stare *</label>
            @php
                $activeValue = (string) old('is_active', $plan->exists ? ($plan->is_active ? '1' : '0') : '');
            @endphp
            <select id="field-is_active" name="is_active" required>
                <option value="" @selected($activeValue === '')>—</option>
                <option value="1" @selected($activeValue === '1')>Activ</option>
                <option value="0" @selected($activeValue === '0')>Inactiv</option>
            </select>
        </div>
        <div class="full form-actions">
            <button class="btn admin-btn" type="submit">Salvează</button>
            <a class="btn admin-btn secondary" href="{{ route('aura.admin.plans') }}">Renunță</a>
        </div>
        <p id="form-error" class="full" role="alert" style="color:#ffc1d1">
            @foreach($errors->all() as $message)
                {{ $message }}
                @if(!$loop->last)<br>@endif
            @endforeach
        </p>
    </form>
</x-aura-admin::card>
<script src="{{ asset('assets/admin/plans.js') }}?v={{ filemtime(public_path('assets/admin/plans.js')) }}" defer></script>
@else
@php
$entity = strtok($demoPage, '/');
$isCreate = $recordId === 'create';
$isEdit = $mode === 'edit';
$schema = array_values(array_filter(explode(',', $demoData['schemas'][$entity] ?? '')));
$recordTitle = $record['title'] ?? $record['name'] ?? $recordId;
$statusOptions = match (true) {
    in_array($entity, ['levels', 'xprules', 'plans', 'referrals'], true) => ['Activ', 'Inactiv', 'Pauză'],
    in_array($entity, ['notifications', 'announcements', 'messageTemplates'], true) => ['Ciornă', 'Programată · demo', 'Activ'],
    default => ['Activă', 'Ciornă', 'Pauză', 'Expirată', 'Arhivată'],
};
$fieldOptions = [
    'plan' => ['Free', 'Premium', 'Premium trimestrial'],
    'category' => ['Promoții bancare', 'Cashback', 'Recomandări', 'Vânzări', 'Abonamente', 'Altele'],
    'rewardType' => ['Fixă', 'Procentuală', 'Cashback', 'Bonus furnizor extern', 'Nemonetară'],
    'source' => ['Angels Legacy', 'Loyalty Club', 'Texora Studio', 'Texora CRM', 'Aurelion'],
    'visibility' => ['Toți utilizatorii', 'Premium'],
    'verification' => ['Declarație utilizator', 'Încărcare document', 'Aprobare administrator', 'Eveniment API extern'],
    'featured' => ['Da', 'Nu'],
    'model' => ['Fix', 'Procentual', 'Recurent'],
    'channel' => ['În aplicație', 'Email', 'Web Push'],
    'audience' => ['Toți utilizatorii', 'Free', 'Premium', 'Misiuni active'],
    'trigger' => ['Oportunitate nouă', 'Memento misiune', 'Misiune finalizată', 'Recompensă confirmată', 'Comision disponibil', 'Reînnoire abonament', 'Expirare abonament', 'Insignă deblocată'],
    'interval' => ['Lunar', '3 luni'],
    'icon' => ['bank', 'cart', 'coins', 'wings', 'infinity', 'gem', 'crown', 'rocket', 'star'],
    'status' => $statusOptions,
];
@endphp

@if(($isCreate || $isEdit) && $schema !== [])
<script>window.AURA_ADMIN_RECORD_STEPS = {{ Js::from($record['steps'] ?? ['Citește cerințele', 'Îndeplinește condițiile', 'Verifică rezultatul la sursă']) }};</script>
<div class="admin-heading"><div><h1>{{ $isEdit ? 'Editează '.$recordTitle : 'Adaugă '.$pageTitle }}</h1><p>Formular demonstrativ, fără operațiuni externe.</p></div><a class="btn admin-btn secondary" href="{{ route('aura.admin.'.$entity) }}">Înapoi</a></div>
<x-aura-admin::card class="admin-card"><form id="record-form" class="admin-form" data-entity="{{ $entity }}" data-record-id="{{ $isCreate ? '' : $recordId }}" onsubmit="saveRecord(event,this.dataset.entity,this.dataset.recordId)">
@foreach($schema as $field)
@php
$value = $record[$field] ?? null;
$label = $demoData['labels'][$field] ?? $field;
$required = in_array($field, ['name', 'title', 'email'], true);
$type = in_array($field, ['amount', 'xp', 'level', 'threshold', 'limits', 'value', 'price', 'missions', 'retry'], true) ? 'number' : (in_array($field, ['start', 'end', 'deadline'], true) ? 'date' : ($field === 'schedule' ? 'datetime-local' : ($field === 'email' ? 'email' : (in_array($field, ['summary', 'description', 'instructions', 'terms', 'conditions', 'eligibility', 'criteria', 'approval', 'features', 'message'], true) ? 'textarea' : 'text'))));
$options = $fieldOptions[$field] ?? null;
@endphp
@if($field === 'steps')
<div class="full"><label>Pași ordonați</label><div id="step-builder"></div><button class="btn admin-btn secondary" type="button" onclick="addStep()"><x-aura-admin::icon name="plus" /> Adaugă pas</button><p class="helper">Configurează instrucțiunile, acțiunea, termenul, XP și verificarea fiecărui pas.</p></div>
@else
<div class="{{ $type === 'textarea' ? 'full' : '' }}"><label for="field-{{ $field }}">{{ $label }}{{ $required ? ' *' : '' }}</label>
@if($options !== null)
<select id="field-{{ $field }}" name="{{ $field }}">@foreach($options as $option)<option @selected((string) $value === (string) $option)>{{ $option }}</option>@endforeach</select>
@elseif($type === 'textarea')
<textarea id="field-{{ $field }}" name="{{ $field }}" maxlength="5000" @required($required)>{{ $value }}</textarea>
@else
<input id="field-{{ $field }}" name="{{ $field }}" type="{{ $type }}" @if($type === 'number') min="0" step="0.01" @endif @required($required) @if($type === 'text') maxlength="250" @endif value="{{ $value }}">
@endif
@if($field === 'payer')<p class="helper">Bonusul extern este informativ și nu devine sold Aura plătibil.</p>@endif
@if($field === 'price')<p class="helper">Preț pentru întregul interval ales.</p>@endif
</div>
@endif
@endforeach
<div class="full form-actions"><button class="btn admin-btn" type="submit">Salvează în demo</button><a class="btn admin-btn secondary" href="{{ route('aura.admin.'.$entity) }}">Renunță</a></div><p id="form-error" class="full" role="alert" style="color:#ffc1d1"></p></form></x-aura-admin::card>
@elseif($entity === 'integrations' && $record)
<div class="admin-heading"><div><h1>{{ $record['name'] }}</h1><p>Integrare demonstrativă · baze de date și reguli independente.</p></div><a class="btn admin-btn secondary" href="{{ route('aura.admin.integrations') }}">Înapoi la aplicații</a></div>
<div class="tabs"><a class="active" href="{{ route('aura.admin.integrations') }}">Integrări aplicații</a><a href="{{ route('aura.admin.integration-config') }}">Configurare integrare</a><a href="{{ route('aura.admin.events') }}">Evenimente & sincronizări</a></div>
<div class="admin-grid equal"><x-aura-admin::card class="admin-card"><span class="app-icon" style="font-size:50px"><x-aura-admin::icon :name="match($record['name']) { 'Angels Legacy' => 'wings', 'Loyalty Club' => 'infinity', 'Texora Studio' => 'monitor', 'Texora CRM' => 'gem', default => 'crown' }" /></span><h2>{{ $record['name'] }}</h2><x-aura-admin::status-badge :status="$record['status'] === 'Connected' ? 'Conectat · demo' : 'Deconectat'" />
@foreach([['Stare API', $record['health'] === 'OK' ? 'OK · simulat' : 'Degradat · simulat'], ['Ultima sincronizare', $record['sync']], ['Conturi asociate', $record['users']], ['Comisioane demo', number_format($record['commissions'], 0, ',', '.').' lei'], ['Evenimente demo', $record['events']]] as [$label, $value])<div class="integration-stat"><span>{{ $label }}</span><b>{{ $value }}</b></div>@endforeach
<div class="form-actions"><button class="btn admin-btn" data-record-id="{{ $recordId }}" onclick="testConnection(this.dataset.recordId)">Testează conexiunea demo</button><button class="btn admin-btn secondary" data-record-id="{{ $recordId }}" onclick="confirmConnection(this.dataset.recordId)">{{ $record['status'] === 'Connected' ? 'Deconectează demo' : 'Conectează demo' }}</button></div></x-aura-admin::card>
<x-aura-admin::card class="admin-card"><h2>Istoric sincronizare</h2><div class="timeline"><div class="event"><time>15:40</time><span>Sincronizare demo {{ $record['health'] === 'OK' ? 'reușită' : 'cu eroare simulată' }}</span></div><div class="event"><time>14:40</time><span>Evenimente demo prelucrate</span></div></div><h2 style="margin-top:20px">Mapări evenimente</h2><p style="font-size:12px">commission.confirmed → comision confirmat<br>referral.validated → conversie validată<br>user.linked → asociere autorizată</p><a class="btn admin-btn secondary" href="{{ route('aura.admin.integration-config') }}">Configurează mapările</a></x-aura-admin::card></div><x-aura-admin::alert message="Nu sunt folosite credențiale reale. Testele și sincronizările sunt simulate." />
@elseif($record)
<div class="admin-heading"><div><h1>{{ $recordTitle }}</h1><p>Detalii demonstrative.</p></div>@if($schema !== [])<a class="btn admin-btn" href="{{ route('aura.admin.record', ['entity' => $entity, 'recordId' => $recordId, 'mode' => 'edit']) }}"><x-aura-admin::icon name="edit" /> Editează</a>@else<a class="btn admin-btn secondary" href="{{ route('aura.admin.'.$entity) }}">Înapoi</a>@endif</div>
<x-aura-admin::card class="admin-card"><div class="section-head"><h2>{{ $recordId }}</h2><x-aura-admin::status-badge :status="$record['status'] ?? 'Demo'" /></div><div class="detail-grid">
@foreach($record as $field => $value) @continue($field === 'id')<div class="detail-item"><small>{{ $demoData['labels'][$field] ?? $field }}</small><b>@if($field === 'status')<x-aura-admin::status-badge :status="$value" />@elseif(is_array($value))@foreach($value as $index => $item){{ $index + 1 }}. {{ is_array($item) ? ($item['title'] ?? '') : $item }}@if(!$loop->last)<br>@endif @endforeach @else{{ $value }}@endif</b></div>@endforeach
</div>@if($entity === 'achievements')<div class="preview-badge"><x-aura-admin::icon :name="$record['icon']" /></div>@endif
<div class="form-actions"><a class="btn admin-btn secondary" href="{{ route('aura.admin.'.$entity) }}">Înapoi</a>
@if($entity === 'referrals')<button class="btn admin-btn secondary" data-entity="{{ $entity }}" data-record-id="{{ $recordId }}" onclick="confirmStatus(this.dataset.entity,this.dataset.recordId,'Arhivată')">Arhivează demo</button>@endif
@if(in_array($entity, ['commissions', 'payouts'], true))<button class="btn admin-btn" data-entity="{{ $entity }}" data-record-id="{{ $recordId }}" onclick="review(this.dataset.entity,this.dataset.recordId)">Revizuiește demo</button>@endif
@if(in_array($entity, ['notifications', 'announcements', 'messageTemplates'], true))<button class="btn admin-btn" data-entity="{{ $entity }}" data-record-id="{{ $recordId }}" onclick="previewMessage(this.dataset.entity,this.dataset.recordId)">Previzualizează mesajul</button>@endif
@if($entity === 'links')<button class="btn admin-btn secondary" data-record-id="{{ $recordId }}" onclick="copyLink(this.dataset.recordId)">Copiază linkul demo</button>@endif
@if($entity === 'events')<button class="btn admin-btn secondary" data-record-id="{{ $recordId }}" onclick="changeEvent(this.dataset.recordId)">Simulează reprocesarea</button>@endif
</div></x-aura-admin::card>
@if(in_array($entity, ['transactions', 'commissions', 'payouts'], true))<x-aura-admin::alert :message="($record['type'] ?? null) === 'Bonus extern informativ' ? 'Bonus extern informativ: nu reprezintă datorie Aura și nu intră în soldul disponibil.' : 'Registru de comisioane. Fără depozite sau transferuri reale.'" /><x-aura-admin::card class="admin-card" style="margin-top:18px"><h2>Urmă de audit</h2><div class="timeline"><div class="event"><time>05 nov.</time><span>Înregistrare demonstrativă primită · {{ $record['reference'] ?? $recordId }}</span></div><div class="event"><time>05 nov.</time><span>Stare: {{ $record['status'] ?? 'Demo' }}</span></div></div></x-aura-admin::card>@endif
@else
<div class="admin-heading"><div><h1>Înregistrare negăsită</h1><p>Referința nu există în eșantion.</p></div><a class="btn admin-btn secondary" href="{{ route('aura.admin.'.$entity) }}">Înapoi</a></div><x-aura-admin::empty-state title="Înregistrare negăsită" description="Referința nu există în datele demonstrative." />
@endif
@endif
@endsection
