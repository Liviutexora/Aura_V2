@extends('admin.layouts.app')
@section('content')
<div id="plans-page">
    <div class="admin-heading">
        <div><h1>Planuri de abonament</h1><p>Caută, filtrează și revizuiește planurile existente.</p></div>
        <a class="btn admin-btn " href="{{ route('aura.admin.plans.create') }}"><x-aura-admin::icon name="plus" /> Adaugă</a>
    </div>
    <x-aura-admin::card class="admin-card">
        <div class="toolbar">
            <input aria-label="Caută în tabel" placeholder="Caută după nume, referință…" value="" data-plans-search>
            <select aria-label="Filtrează starea" data-plans-status>
                <option value="">Toate stările</option><option>Activ</option><option>Inactiv</option>
            </select>
            <select aria-label="Sortează" data-plans-sort>
                <option value="">Ordine implicită</option>
                <option value="name">Nume</option>
                <option value="price">Preț RON / interval</option>
                <option value="interval">Interval</option>
                <option value="limits">Limite proiecte</option>
                <option value="features">Beneficii</option>
                <option value="status">Stare</option>
            </select>
            <button class="icon-button" aria-label="Inversează ordinea" data-plans-reverse><x-aura-admin::icon name="sort" /></button>
            <button class="btn admin-btn secondary" data-plan-preview><x-aura-admin::icon name="download" /> Export demo</button>
        </div>
        <div class="table-wrap"><table class="admin-table">
            <thead><tr>
                <th scope="col"><button data-plans-column="name">Nume</button></th>
                <th scope="col"><button data-plans-column="price">Preț RON / interval</button></th>
                <th scope="col"><button data-plans-column="interval">Interval</button></th>
                <th scope="col"><button data-plans-column="limits">Limite proiecte</button></th>
                <th scope="col"><button data-plans-column="features">Beneficii</button></th>
                <th scope="col"><button data-plans-column="status">Stare</button></th>
                <th scope="col">Acțiuni</th>
            </tr></thead>
            <tbody id="table-body">
                @foreach($plans as $plan)
                    <tr data-plan-row data-reference="{{ $plan->id }}" @if($loop->index >= 6) hidden @endif>
                        <td data-sort-name="{{ $plan->name }}"><b>{{ $plan->name }}</b></td>
                        <td data-sort-price="{{ $plan->monthly_price_minor }}">
                            <b>{{ number_format($plan->monthly_price_minor / 100, 2, ',', '.') }} lei</b><br>
                            <b>{{ number_format($plan->quarterly_price_minor / 100, 2, ',', '.') }} lei</b>
                        </td>
                        <td data-sort-interval="1">Lunar<br>Trimestrial · 3 luni</td>
                        <td data-sort-limits="{{ $plan->max_projects ?? 'unlimited' }}">
                            Total: {{ $plan->max_projects ?? 'Nelimitat' }}<br>
                            Explorator: {{ $plan->max_explorer_projects ?? 'Nelimitat' }}<br>
                            Mentor: {{ $plan->max_mentor_projects ?? 'Nelimitat' }}
                        </td>
                        <td data-sort-features="{{ implode('; ', $plan->benefits) }}">{{ implode('; ', $plan->benefits) }}</td>
                        <td data-sort-status="{{ $plan->is_active ? 'Activ' : 'Inactiv' }}"><x-aura-admin::status-badge :status="$plan->is_active ? 'Activ' : 'Inactiv'" /></td>
                        <td><div class="actions">
                            <a class="icon-button" href="#" data-plan-preview aria-label="Detalii {{ $plan->name }}"><x-aura-admin::icon name="eye" /></a>
                            <a class="icon-button" href="{{ route('aura.admin.plans.edit', $plan) }}" aria-label="Editează {{ $plan->name }}"><x-aura-admin::icon name="edit" /></a>
                        </div></td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
        <div id="table-pagination"><div class="pagination">
            <span data-plans-count>{{ $plans->count() }} rezultate · Pagina 1 / {{ max(1, (int) ceil($plans->count() / 6)) }}</span>
            <span><button data-plans-previous disabled>‹ Înapoi</button> <button data-plans-next @disabled($plans->count() <= 6)>Înainte ›</button></span>
        </div></div>
        <div id="table-empty" @if($plans->isNotEmpty()) hidden @endif>
            <x-aura-admin::empty-state title="Niciun rezultat" description="Schimbă filtrele sau termenul de căutare." />
        </div>
    </x-aura-admin::card>
    <x-aura-admin::alert message="Planuri reale din baza de date. Detaliile și exportul vor fi conectate ulterior." />
</div>
<script src="{{ asset('assets/admin/plans.js') }}?v={{ filemtime(public_path('assets/admin/plans.js')) }}" defer></script>
@endsection
