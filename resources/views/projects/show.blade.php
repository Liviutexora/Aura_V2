@extends('layouts.project')
@section('project-content')
<div class="detail"><div class="heading"><div><div class="eyebrow">SPAȚIUL TĂU DE OPORTUNITĂȚI</div><h1>{{ $project->title }}</h1><p>{{ config('project_labels.'.$project->status) }} · {{ $project->progress() }}%</p></div></div>
<section class="card"><div class="progress"><i style="width:{{ $project->progress() }}%"></i></div>
<p>Acceptat · {{ $project->accepted_at->timezone('Europe/Bucharest')->format('d.m.Y H:i') }} @if($project->due_on) · Termen {{ $project->due_on->format('d.m.Y') }}@endif</p>
@if($project->status === 'accepted')<form method="POST" action="{{ route('aura.projects.start',$project) }}">@csrf<button class="btn">Începe proiectul ⟶</button></form>@endif
@foreach($project->missions as $mission)<a class="row" href="{{ route('aura.missions.show',$mission) }}"><span class="tile"><svg class="aura-svg" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#checklist"></use></svg></span><div class="body"><b>{{ $mission->title }}</b><small>{{ $mission->is_required ? 'Obligatorie' : 'Opțională' }} · {{ config('project_labels.'.$mission->status) }}</small></div><b>{{ $mission->progress() }}%</b><span class="chevron">›</span></a>@endforeach
<a class="btn secondary" href="{{ route('aura.opportunities.show',$project->opportunity_id) }}">Vezi oportunitatea</a></section>
<section class="card"><h2>Încasări externe</h2>@forelse($project->earnings as $receipt)<p>{{ number_format($receipt->amount_minor/100,2,',','.') }} {{ $receipt->currency }} · {{ config('project_labels.'.$receipt->status) }} · {{ $receipt->description }}</p>@empty<p>Nicio încasare înregistrată.</p>@endforelse
<details><summary>Declară o încasare</summary><form class="settings-form" method="POST" action="{{ route('aura.projects.earning',$project) }}">@csrf
@include('missions.payment',['required'=>true,'earningKey'=>old('earning_key', (string) Str::uuid())])<label>Descriere<input name="description" maxlength="1000"></label><button class="btn secondary">Salvează încasarea</button></form></details></section>
<section class="card"><h2>Istoric</h2>@foreach($events as $event)<p>{{ $event->occurred_at->timezone('Europe/Bucharest')->format('d.m.Y H:i') }} · {{ config('project_labels.'.$event->event_type,$event->event_type) }} @if($event->step_id) · {{ $project->missions->flatMap->steps->firstWhere('id',$event->step_id)?->title }}@endif {{ $event->metadata['note'] ?? '' }}</p>@endforeach
@include('projects.pagination',['items'=>$events])</section></div>
@endsection
