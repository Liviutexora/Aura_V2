@extends('admin.layouts.app')
@section('content')
<div class="admin-heading"><div><h1>{{ $mission->title ?? $project->title }}</h1><p>{{ $project->user->name }} · {{ config('project_labels.'.$project->status) }}</p></div><a class="btn admin-btn secondary" href="{{ route('aura.admin.'.$entity) }}">Înapoi</a></div>
<x-aura-admin::card class="admin-card">@isset($mission)<a href="{{ route('aura.admin.projects.show',$project) }}">{{ $project->title }}</a><p>{{ $mission->progress() }}% · {{ config('project_labels.'.$mission->status) }}</p>
<ol>@foreach($mission->steps as $step)<li>{{ $step->title }} · {{ config('project_labels.'.$step->status) }}</li>@endforeach</ol>
<form class="admin-form" method="POST" action="{{ route('aura.admin.missions.update',$mission) }}">@csrf @method('PATCH')<label>Termen<input type="date" name="due_on" value="{{ $mission->effectiveDueOn()?->toDateString() }}" required></label><label>Motiv<input name="reason" required maxlength="1000"></label><button class="btn admin-btn">Actualizează termenul</button></form>
@foreach($mission->submissions as $attempt)<a class="admin-row" href="{{ route('aura.admin.verification.show',$attempt) }}">Verificare #{{ $attempt->id }} · {{ config('project_labels.'.$attempt->status) }} · {{ $attempt->reviewer?->name }}</a>@endforeach
@else @foreach($project->missions as $item)<a class="admin-row" href="{{ route('aura.admin.missions.show',$item) }}"><b>{{ $item->title }}</b> · {{ $item->progress() }}% · {{ config('project_labels.'.$item->status) }}</a>@endforeach
@foreach($project->earnings as $receipt)<p>{{ number_format($receipt->amount_minor/100,2,',','.') }} {{ $receipt->currency }} · {{ config('project_labels.'.$receipt->status) }}</p>@endforeach @endisset</x-aura-admin::card>@endsection
