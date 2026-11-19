@extends('layouts.project')
@section('project-content')
@php($project = $mission->project)
@php($zone = auth()->user()->profile?->timezone ?: 'Europe/Bucharest')
<div class="detail"><div class="heading"><div><div class="eyebrow">SPAȚIUL TĂU DE OPORTUNITĂȚI</div><h1>{{ $mission->title }}</h1><p>Misiunea ta · {{ config('project_labels.'.$mission->status) }} · {{ $mission->effectiveDueOn()?->format('d.m.Y') ?? ($mission->deadline_anchor ? 'Așteaptă pasul de referință' : 'Fără termen') }}</p></div><span class="demo">{{ $mission->steps->count() }} pași</span></div>
<div class="card"><div class="section-head"><h2>Progresul misiunii</h2><b class="gold">{{ $mission->progress() }}%</b></div><div class="progress"><i style="width:{{ $mission->progress() }}%"></i></div>
<p>Bifează pașii parcurși. Confirmă încasarea recompensei la pasul dedicat.</p>
@if($mission->description || $mission->required_action)<p>{{ $mission->description }} {{ $mission->required_action }}</p>@endif
@if($project->status === 'accepted')<form method="POST" action="{{ route('aura.projects.start',$project) }}">@csrf<button class="btn">Începe proiectul</button></form>@endif
<ul class="steps">@foreach($mission->steps as $step)
@php($step->setRelation('mission',$mission))
@php($latest = $mission->submissions->where('step_id',$step->id)->sortByDesc('id')->first())
@php($editable = $project->started_at && !in_array($mission->status,['completed','in_review'],true) && $step->status !== 'in_review')
<li><div class="body">
@if($step->verification_method === 'user_declaration')
<form method="POST" action="{{ route('aura.missions.check',[$mission,$step]) }}">@csrf @method('PATCH')
<input type="hidden" name="request_key" value="{{ Str::uuid() }}"><input type="hidden" name="checked" value="0">
<label><input type="checkbox" name="checked" value="1" @checked($step->status === 'completed') @disabled(!$editable) onchange="this.form.requestSubmit()"> {{ $step->title }}</label>
@if($step->confirms_receipt && $editable && $step->status !== 'completed')
@include('missions.payment',['required'=>false,'currency'=>$latest?->earning?->currency ?? $project->offer_snapshot['reward_currency'] ?? 'RON','receivedAmount'=>$latest?->earning ? number_format($latest->earning->amount_minor/100,2,'.','') : '','earningKey'=>$latest?->earning?->earning_key ?? (string) Str::uuid()])
@endif
</form>
@else
<b>{{ $step->title }}</b> · {{ config('project_labels.'.$step->status) }}
@if($editable && $step->status !== 'completed' && $step->verification_method !== 'provider_event')
@include('missions.submission',['step'=>$step,'method'=>$step->verification_method,'earningKey'=>$latest?->earning?->earning_key ?? (string) Str::uuid()])
@endif
@endif
<small>{{ $step->description }} {{ $step->instructions }} {{ $step->required_action }}</small>
@if($due = $step->effectiveDueOn())<small>Termen · {{ $due->format('d.m.Y') }} @if($step->status !== 'completed' && $due->toDateString() < now('Europe/Bucharest')->toDateString()) · Întârziat @endif</small>@elseif($step->deadline_anchor)<small>Termenul așteaptă pasul de referință.</small>@endif
@if($latest?->review_note)<small>{{ $latest->review_note }}</small>@endif
</div></li>@endforeach</ul>
@if($project->started_at && in_array($mission->status,['in_review','needs_changes'],true) && !$mission->submissions->whereNull('step_id')->contains('status','pending') && $mission->steps->every(fn($s)=>$s->status === 'completed') && in_array($mission->verification_method,['admin_approval','document_upload'],true))
@include('missions.submission',['step'=>null,'method'=>$mission->verification_method])
@endif
<div class="row"><span class="icon"><svg class="aura-svg"><use href="{{ asset('assets/aura/icons.svg') }}#coins"></use></svg></span><div class="body"><b>Recompensă potențială · {{ $project->offer_snapshot['reward_label'] ?? '' }}</b>
@forelse($project->earnings as $receipt)<small>{{ config('project_labels.'.$receipt->status) }} · {{ number_format($receipt->amount_minor/100,2,',','.') }} {{ $receipt->currency }}</small>@empty<small>În așteptarea confirmării încasării</small>@endforelse</div></div>
<button type="button" class="btn secondary" onclick="document.getElementById('mission-reminder').showModal()">◷ Setează un memento</button>
<a class="btn secondary" href="{{ route('aura.opportunities.show',$project->opportunity_id) }}">Vezi oportunitatea</a> <a class="btn secondary" href="{{ route('aura.projects.show',$project) }}">Deschide proiectul</a>
<dialog id="mission-reminder"><button type="button" class="dialog-close" onclick="this.closest('dialog').close()">✕</button><h2>Memento</h2><form method="POST" action="{{ route('aura.missions.reminder',$mission) }}">@csrf @method('PUT')
<label>Data și ora · {{ $zone }}<input type="datetime-local" name="remind_at" value="{{ $mission->reminder?->remind_at->timezone($zone)->format('Y-m-d\TH:i') }}" required></label><button class="btn">Salvează</button></form>
@if($mission->reminder?->status === 'scheduled')<form method="POST" action="{{ route('aura.missions.reminder',$mission) }}">@csrf @method('DELETE')<button class="btn secondary">Anulează mementoul</button></form>@endif</dialog>
@if($mission->submissions->isNotEmpty())<details><summary>Istoricul verificărilor</summary>@foreach($mission->submissions->sortByDesc('id') as $attempt)
<p>{{ $attempt->submitted_at->timezone($zone)->format('d.m.Y H:i') }} · {{ config('project_labels.'.$attempt->status) }} · {{ $attempt->review_note }}
@if($attempt->private_file_key)<a href="{{ route('aura.missions.evidence',$attempt) }}">Descarcă dovada</a>@endif</p>@endforeach</details>@endif
</div></div>@endsection
