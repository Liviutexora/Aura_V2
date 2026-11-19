@extends('layouts.project')
@section('project-content')
<div class="heading"><div><div class="eyebrow">SPAȚIUL TĂU DE OPORTUNITĂȚI</div><h1>Găsește următoarea oportunitate</h1><p>Alege ce ți se potrivește. Fiecare pas contează.</p></div><span class="demo">{{ $offers->total() }} oportunități</span></div>
<form class="filters" method="GET" data-catalog-filter>
    <input name="q" type="search" aria-label="Caută oportunități" placeholder="⌕ Caută oportunități…" value="{{ request('q') }}">
    <select name="category" aria-label="Categorie"><option value="">Toate categoriile</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>@endforeach</select>
    @foreach(['difficulty'=>['Orice dificultate',['Ușor'=>'Ușor','Mediu'=>'Mediu','Dificil'=>'Dificil']], 'reward'=>['Orice recompensă',[100=>'Cel puțin 100 lei']], 'time'=>['Orice durată',[15=>'Până la 15 min']]] as $key=>[$label,$options])
        <select name="{{ $key }}" aria-label="{{ $label }}"><option value="">{{ $label }}</option>@foreach($options as $value=>$caption)<option value="{{ $value }}" @selected(request($key) == $value)>{{ $caption }}</option>@endforeach</select>
    @endforeach
    <select name="availability" aria-label="Disponibilitate"><option value="available">Disponibile acum</option><option value="expired" @selected(request('availability') === 'expired')>Expirate</option></select>
    <noscript><button class="btn secondary">Caută</button></noscript>
</form>
<div class="catalog" id="results">
@forelse($offers as $offer)
    <article class="card"><div class="section-head"><div class="tile"><svg class="aura-svg" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#{{ $offer->icon ?: 'target' }}"></use></svg></div><span class="status">{{ $offer->difficulty }}</span></div>
    @if($offer->image_path)<img src="{{ asset($offer->image_path) }}" alt="" width="100%">@endif
    <small>{{ $offer->category->name }}</small><h3>{{ $offer->title }}</h3><b class="gold">{{ $offer->reward_label }}</b><small>Recompensă potențială</small>
    <p>{{ $offer->summary }}</p><small>◷ {{ $offer->estimated_minutes }} min @if($offer->ends_at) · până la {{ $offer->ends_at->timezone('Europe/Bucharest')->subSecond()->format('d.m.Y') }}@endif</small>
    <small>Eligibilitate și condiții în detalii</small><a class="btn secondary" href="{{ route('aura.opportunities.show',$offer) }}">Vezi oportunitatea ↗</a></article>
@empty
    <div class="card"><h3>Nicio oportunitate găsită</h3><p>Încearcă alți termeni sau modifică filtrele.</p></div>
@endforelse
</div>
@include('projects.pagination',['items'=>$offers])
@endsection
