@extends('admin.layouts.app')
@section('content')
<div class="admin-heading"><div><h1>{{ $readonly ? ($record->title ?? $record->name) : (($record->exists ? 'Editează' : 'Adaugă').' · '.$pageTitle) }}</h1><p>{{ $readonly ? 'Detalii înregistrare.' : 'Completează datele și salvează modificările.' }}</p></div>
@if($readonly)<a class="btn admin-btn" href="{{ route('aura.admin.'.$entity.'.edit',$record) }}"><x-aura-admin::icon name="edit"/> Editează</a>@else<a class="btn admin-btn secondary" href="{{ route('aura.admin.'.$entity) }}">Înapoi</a>@endif</div>
@if($readonly)@include('admin.pages.project-tabs')@endif
<x-aura-admin::card class="admin-card">
@if($readonly)<div class="section-head"><h2>#{{ $record->id }}</h2><x-aura-admin::status-badge :status="config('project_labels.'.$record->status)"/></div><div class="detail-grid">@foreach($fields as $key=>[$label,$rule])<div class="detail-item"><small>{{ $label }}</small><b>@php($value = $values[$key] ?? null){{ isset($choices[$key]) ? ($choices[$key][$value] ?? '—') : ($rule==='boolean' ? ($value?'Da':'Nu') : (is_scalar($value) ? config('project_labels.'.$value,$value) : '—')) }}</b></div>@endforeach</div>
@if($entity==='mission-templates')<ol>@foreach($values['steps'] as $step)<li>{{ $step['title'] }}</li>@endforeach</ol>@endif
<div class="form-actions"><a class="btn admin-btn secondary" href="{{ route('aura.admin.'.$entity) }}">Înapoi</a>
@if(in_array($entity,['opportunities','categories','eligibility'],true) && $record->status !== 'archived')
<button type="button" class="btn admin-btn secondary" onclick="document.getElementById('archive-confirm').showModal()">Arhivează</button>
<dialog id="archive-confirm" class="admin-dialog"><h2>Confirmă arhivarea</h2><p>{{ $record->title ?? $record->name }}</p><form method="POST" action="{{ route('aura.admin.'.$entity.'.archive',$record) }}">@csrf @method('PATCH')<input type="hidden" name="version" value="{{ $record->version }}"><div class="form-actions"><button class="btn admin-btn">Confirmă arhivarea</button><button class="btn admin-btn secondary" type="button" onclick="this.closest('dialog').close()">Renunță</button></div></form></dialog>@endif</div>
@else
<form class="admin-form" id="record-form" method="POST" action="{{ $record->exists ? route('aura.admin.'.$entity.'.update',$record) : route('aura.admin.'.$entity.'.store') }}">@csrf @if($record->exists)@method('PATCH')<input name="version" type="hidden" value="{{ old('version',$record->version) }}">@endif
@foreach($fields as $key=>$definition)@include('admin.pages.project-field')@endforeach
@if($entity==='mission-templates')<section class="full" id="step-builder"><label>Pași ordonați</label><div data-step-list>@foreach(old('steps',$values['steps'] ?? []) as $index=>$step)
@include('admin.pages.project-step',['slotIndex'=>$index,'values'=>$step])@endforeach</div>
<template data-new-step>@include('admin.pages.project-step',['slotIndex'=>'NEW','values'=>['status'=>'active']])</template>
<button type="button" class="btn admin-btn secondary" data-add-step><x-aura-admin::icon name="plus"/> Adaugă pas</button><p class="helper">Configurează instrucțiunile, acțiunea, termenul, XP și verificarea fiecărui pas.</p><input type="hidden" name="steps_complete" value="1"></section>@endif
<div class="full form-actions"><button class="btn admin-btn" type="submit">Salvează</button><a class="btn admin-btn secondary" href="{{ route('aura.admin.'.$entity) }}">Renunță</a></div></form>@endif
</x-aura-admin::card>@endsection
