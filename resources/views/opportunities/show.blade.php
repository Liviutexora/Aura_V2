@extends('layouts.project')
@section('project-content')
<div class="detail"><div class="heading"><div><div class="eyebrow">SPAȚIUL TĂU DE OPORTUNITĂȚI</div><h1>{{ $offer->title }}</h1><p>{{ $offer->category_name ?? $offer->category?->name }} · {{ $offer->provider }}</p></div><span class="demo">{{ $offer->difficulty }}</span></div>
<section class="card"><div class="section-head"><div class="tile"><svg class="aura-svg" aria-hidden="true"><use href="{{ asset('assets/aura/icons.svg') }}#{{ $offer->icon ?: 'target' }}"></use></svg></div><b class="gold" style="font-size:26px">{{ $offer->reward_label }}</b></div>
<p>{{ $offer->summary }}</p><div class="notice">Recompensă potențială, condiționată de eligibilitate și validare. Plătitor · {{ $offer->payer }}</div>
<h2>Condiții de eligibilitate</h2><p>{{ $offer->eligibility_text }} {{ $offer->geography }}</p>
@foreach($rules as $rule)<p>{{ data_get($rule,'name') }} · {{ data_get($rule,'conditions_text') }}</p>@endforeach
<h2 style="margin-top:25px">Pașii recomandați</h2><p>{{ $offer->instructions }}</p>
@foreach($templates as $template)<b>{{ $template->title }}</b><ol class="steps">@foreach($template->steps as $step)<li><span class="check">{{ $loop->iteration }}</span>{{ $step->title }}</li>@endforeach</ol>@endforeach
<p>◷ {{ $offer->estimated_minutes }} min @if($offer->ends_at) · Înscrieri până la {{ $offer->ends_at->timezone('Europe/Bucharest')->subSecond()->format('d.m.Y') }}@endif</p>
<p>Sursă · @if($offer->source_url)<a href="{{ $offer->source_url }}" rel="noopener noreferrer">{{ $offer->source_label ?: $offer->provider }}</a>@else{{ $offer->source_label ?: $offer->provider }}@endif</p>
<details><summary>Termeni și condiții</summary><p>{{ $offer->terms }}</p></details>
@if($project)<a class="btn wide" href="{{ route('aura.projects.show',$project) }}">Deschide proiectul ⟶</a>
@elseif($available)<form method="POST" action="{{ route('aura.projects.accept',$opportunity) }}">@csrf
@foreach($rules->where('check_method','user_attestation') as $rule)<label><input type="checkbox" required name="attestations[]" value="{{ $rule->id.':'.$rule->version }}"> Confirm · {{ $rule->conditions_text }}</label>@endforeach
<button class="btn wide" style="margin-top:20px">Acceptă oportunitatea ⟶</button></form>@endif
</section></div>
@endsection
