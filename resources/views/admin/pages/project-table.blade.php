<div class="admin-heading"><div><h1>{{ $pageTitle }}</h1><p>Caută, filtrează și revizuiește înregistrările.</p></div>
@if($entity !== 'verification')<a class="btn admin-btn" href="{{ route('aura.admin.'.$entity.'.create') }}"><x-aura-admin::icon name="plus" /> Adaugă</a>
@else<select name="range" form="project-filter" aria-label="Perioada raportului" onchange="this.form.submit()"><option value="">Toate perioadele</option>@foreach([7,30,90] as $range)<option value="{{ $range }}" @selected(request('range')==$range)>Ultimele {{ $range }} zile</option>@endforeach</select>@endif</div>
@include('admin.pages.project-tabs')
<x-aura-admin::card class="admin-card"><form class="toolbar" id="project-filter" method="GET" action="{{ route('aura.admin.'.$entity) }}">
<input name="q" type="search" aria-label="Caută în tabel" placeholder="Caută după nume, referință…" value="{{ request('q') }}">
@php($states = match($entity){ 'categories','eligibility'=>['draft','active','paused','archived'], 'opportunities'=>['draft','published','paused','archived'], 'missions'=>['assigned','in_progress','in_review','needs_changes','completed'], 'projects'=>['accepted','active','completed'], default=>['pending','approved','rejected'] })
<select name="status" aria-label="Filtrează starea" onchange="this.form.submit()"><option value="">Toate stările</option>@foreach($states as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ config('project_labels.'.$status) }}</option>@endforeach</select>
<select name="sort" aria-label="Sortează" onchange="this.form.submit()"><option value="">Ordine implicită</option>@foreach($columns as $key=>$label)<option value="{{ $key }}" @selected(request('sort')===$key)>{{ $label }}</option>@endforeach</select>
@if($entity==='missions')<input type="date" name="due_on" aria-label="Termen" value="{{ request('due_on') }}">@endif
<button name="dir" value="{{ request('dir')==='asc' ? 'desc' : 'asc' }}" class="icon-button" aria-label="Inversează ordinea"><x-aura-admin::icon name="sort"/></button>
<noscript><button class="btn admin-btn secondary">Caută</button></noscript><a class="btn admin-btn secondary" href="{{ route('aura.admin.'.$entity.'.export', request()->except('page')) }}"><x-aura-admin::icon name="download"/> Export</a></form>
<div class="table-wrap"><table class="admin-table"><thead><tr>@foreach($columns as $key=>$label)<th scope="col"><button type="submit" form="project-filter" formaction="{{ route('aura.admin.'.$entity) }}" data-column-sort="{{ $key }}">{{ $label }}</button></th>@endforeach<th>Acțiuni</th></tr></thead><tbody>
@foreach($items as $item)<tr>@foreach($columns as $key=>$label)
@php($value = $key === 'due_on' && $entity === 'missions' ? $item->effectiveDueOn() : data_get($item,$key))
<td>@if(in_array($key,['title','name'],true))<b>{{ $value }}</b>@elseif($key==='status')<x-aura-admin::status-badge :status="config('project_labels.'.$value,$value)"/>
@elseif($value instanceof \DateTimeInterface){{ $value->format('d.m.Y') }}
@elseif(in_array($key,['visibility','method','verification_method'],true)){{ config('project_labels.'.$value,$value) }}
@else{{ $value ?? '—' }}@endif</td>@endforeach
<td><div class="actions"><a class="icon-button" aria-label="Detalii" href="{{ route('aura.admin.'.$entity.'.show',$item) }}"><x-aura-admin::icon name="eye"/></a>
@if(isset(\App\Services\CatalogEditor::MODELS[$entity]))<a class="icon-button" aria-label="Editează" href="{{ route('aura.admin.'.$entity.'.edit',$item) }}"><x-aura-admin::icon name="edit"/></a>@endif
@if($entity==='verification')<a class="icon-button" aria-label="Revizuiește #{{ $item->id }}" data-project-review href="{{ route('aura.admin.verification.show',$item) }}"><x-aura-admin::icon name="check"/></a>@endif</div></td></tr>@endforeach
</tbody></table></div>@if($items->isEmpty())<x-aura-admin::empty-state title="Nicio înregistrare" description="Nu există rezultate pentru selecția curentă."/>@endif
<div id="table-pagination"><div class="pagination"><span>{{ $items->total() }} rezultate · Pagina {{ $items->currentPage() }} / {{ $items->lastPage() }}</span><span>
<button type="button" @disabled($items->onFirstPage()) data-page-url="{{ $items->previousPageUrl() }}">‹ Înapoi</button>
<button type="button" @disabled(!$items->hasMorePages()) data-page-url="{{ $items->nextPageUrl() }}">Înainte ›</button></span></div></div></x-aura-admin::card>
<x-aura-admin::alert :message="match($entity){'categories','eligibility'=>'Regulile active se verifică la acceptarea oportunității.', 'verification'=>'Deciziile sunt păstrate împreună cu dovada și administratorul care le-a luat.', 'missions','projects'=>'Progresul și termenele aparțin fiecărui participant.', default=>'Oportunitățile publicate apar în catalog în perioada de înscriere.'}"/>
