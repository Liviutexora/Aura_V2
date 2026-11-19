@extends('admin.layouts.app')
@section('content')
<div class="admin-heading"><div><h1>Șabloane de misiuni</h1><p>Construiește și reordonează pașii fără a afecta recompensele financiare.</p></div><a class="btn admin-btn" href="{{ route('aura.admin.mission-templates.create') }}">Creează șablon</a></div>
@include('admin.pages.project-tabs')
<div class="admin-grid equal">@forelse($items as $item)<x-aura-admin::card class="admin-card"><h2>{{ $item->title }}</h2><p style="font-size:11px">{{ $item->opportunity->title }} · {{ config('project_labels.'.$item->verification_method) }} · {{ $item->xp_points }} XP</p><ol style="font-size:12px;color:#e2caee;line-height:2">@foreach($item->steps->where('status','!=','archived') as $step)<li>{{ $step->title }}</li>@endforeach</ol><a class="btn admin-btn secondary" href="{{ route('aura.admin.mission-templates.edit',$item) }}">Configurează pașii</a></x-aura-admin::card>
@empty<x-aura-admin::empty-state title="Niciun șablon" description="Creează primul șablon pentru o oportunitate."/>@endforelse</div>@include('projects.pagination')
@endsection
