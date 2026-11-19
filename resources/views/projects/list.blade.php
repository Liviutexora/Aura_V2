<div class="heading"><div><div class="eyebrow">SPAȚIUL TĂU DE OPORTUNITĂȚI</div><h1>{{ $projects ? 'Proiectele tale în Aura' : 'Misiunile mele' }}</h1><p>Un plan clar pentru fiecare oportunitate.</p></div><span class="demo">{{ $items->total() }} {{ $projects ? 'proiecte' : 'misiuni' }}</span></div>
<div class="filters">@foreach(['active'=>'În progres','completed'=>'Finalizate','history'=>'Istoric'] as $key=>$label)<a class="btn secondary" href="{{ request()->fullUrlWithQuery(['tab'=>$key,'page'=>1]) }}" @if($tab===$key) aria-current="page" @endif>{{ $label }}</a>@endforeach</div>
<div id="mission-list">@forelse($items as $item)
@php($steps = $projects ? $item->missions->flatMap->steps : $item->steps)
<div class="card" style="margin-bottom:15px"><div class="section-head"><h2><svg class="aura-svg" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#folder"></use></svg> {{ $item->title }}</h2><span class="status">{{ config('project_labels.'.$item->status) }}</span></div>
<p>{{ $projects ? ($item->offer_snapshot['summary'] ?? '') : $item->description }}</p><div class="progress"><i style="width:{{ $item->progress() }}%"></i></div>
<p>{{ $item->progress() }}% · {{ $steps->where('status','completed')->count() }} din {{ $steps->count() }} pași
@if($next = $steps->first(fn($step)=>in_array($step->status,['pending','needs_changes'],true))) · Următorul pas · {{ $next->title }}@elseif($item->status !== 'completed') · Așteaptă verificarea @endif
@if($date = ($projects ? $item->due_on : $item->effectiveDueOn())) · {{ $date->format('d.m.Y') }}@endif</p>
<a class="btn secondary" href="{{ route($projects ? 'aura.projects.show' : 'aura.missions.show',$item) }}">Deschide {{ $projects ? 'proiectul' : 'misiunea' }} ⟶</a></div>
@empty<div class="card">{{ $projects ? 'Nu ai proiecte în această categorie.' : 'Nu ai misiuni în această categorie.' }}</div>@endforelse</div>
@include('projects.pagination')
