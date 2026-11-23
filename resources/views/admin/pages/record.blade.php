@extends('admin.layouts.app')
@section('content')
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
@endsection
