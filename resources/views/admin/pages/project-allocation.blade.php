@extends('admin.layouts.app')
@section('content')
<div class="admin-heading"><div><h1>{{ $entity==='missions' ? 'Alocă misiune' : 'Creează proiect' }}</h1></div></div>
<x-aura-admin::card class="admin-card"><form method="POST" class="admin-form" action="{{ route('aura.admin.'.$entity.'.store') }}">@csrf
<label>Utilizator<select name="user_id" required><option value="">Alege utilizatorul</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(old('user_id')==$user->id)>{{ $user->name }} · {{ $user->email }}</option>@endforeach</select></label>
<label>Oportunitate<select name="opportunity_id" required data-allocation-offer><option value="">Alege oportunitatea</option>@foreach($offers as $offer)<option value="{{ $offer->id }}" @selected(old('opportunity_id')==$offer->id)>{{ $offer->title }}</option>@endforeach</select></label>
@if($entity==='missions')<label>Șablon<select name="template_id" required data-allocation-template><option value="">Alege șablonul</option>@foreach($templates as $template)<option data-offer="{{ $template->opportunity_id }}" value="{{ $template->id }}" @selected(old('template_id')==$template->id)>{{ $template->title }}</option>@endforeach</select></label>@endif
<div class="full form-actions"><button class="btn admin-btn">Salvează</button><a class="btn admin-btn secondary" href="{{ route('aura.admin.'.$entity) }}">Renunță</a></div></form></x-aura-admin::card>@endsection
