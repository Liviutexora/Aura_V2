@extends('admin.layouts.app')
@section('content')
<div class="admin-heading">
    <div>
        <h1>Toți utilizatorii</h1>
        <p>Caută, filtrează și revizuiește utilizatorii existenți în Aura.</p>
    </div>
    <button class="btn admin-btn" type="button" disabled><x-aura-admin::icon name="plus" /> Adaugă</button>
</div>

<x-aura-admin::card class="admin-card">
    <form class="toolbar" method="GET" action="{{ route('aura.admin.users') }}">
        <input name="q" aria-label="Caută în tabel" placeholder="Caută după nume sau email…" value="{{ request('q') }}">

        <select name="status" aria-label="Filtrează starea" onchange="this.form.submit()">
            <option value="">Toate stările</option>
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $statusLabels[$status] ?? $status }}</option>
            @endforeach
        </select>

        <select aria-label="Filtrează abonamentul" disabled>
            <option>Abonament —</option>
        </select>

        <select name="sort" aria-label="Sortează" onchange="this.form.submit()">
            <option value="registered" @selected((request('sort') ?: 'registered') === 'registered')>Înregistrare</option>
            <option value="name" @selected(request('sort') === 'name')>Nume</option>
            <option value="email" @selected(request('sort') === 'email')>Email</option>
            <option value="status" @selected(request('sort') === 'status')>Stare</option>
        </select>

        <input type="hidden" name="direction" value="{{ $direction }}">
        <button class="icon-button" type="button" aria-label="Inversează ordinea" onclick="this.form.elements.direction.value=this.form.elements.direction.value==='asc'?'desc':'asc';this.form.submit()"><x-aura-admin::icon name="sort" /></button>
        <button class="icon-button" type="submit" aria-label="Caută"><x-aura-admin::icon name="search" /></button>
        <button class="btn admin-btn secondary" type="button" disabled><x-aura-admin::icon name="download" /> Export</button>
    </form>

    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th scope="col">Nume</th>
                    <th scope="col">Email</th>
                    <th scope="col">Înregistrare</th>
                    <th scope="col">Abonament</th>
                    <th scope="col">Câștiguri</th>
                    <th scope="col">XP</th>
                    <th scope="col">Sponsor direct</th>
                    <th scope="col">Cod de recomandare</th>
                    <th scope="col">Recomandări directe</th>
                    <th scope="col">Stare</th>
                    <th scope="col">Acțiuni</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    @php($statusLabel = $statusLabels[$user->status] ?? ($user->status ?: '—'))
                    <tr>
                        <td><b>{{ $user->name }}</b></td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->created_at?->format('d.m.Y') ?? '—' }}</td>
                        <td>—</td>
                        <td>—</td>
                        <td>—</td>
                        <td>{{ $user->sponsor?->name ?? '—' }}</td>
                        <td>{{ $user->referral_code }}</td>
                        <td>{{ $user->sponsored_users_count }}</td>
                        <td><x-aura-admin::status-badge :status="$statusLabel" /></td>
                        <td>
                            <div class="actions">
                                <a class="icon-button" href="{{ route('aura.admin.users.show', $user) }}" aria-label="Vizualizează {{ $user->name }}"><x-aura-admin::icon name="eye" /></a>
                                <a class="icon-button" href="{{ route('aura.admin.users.edit', $user) }}" aria-label="Editează {{ $user->name }}"><x-aura-admin::icon name="edit" /></a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11">
                            <div class="empty-state">
                                <x-aura-admin::icon name="search" />
                                <h3>Niciun utilizator găsit</h3>
                                <p>Schimbă filtrele sau termenul de căutare.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <span>{{ $users->total() }} {{ $users->total() === 1 ? 'rezultat' : 'rezultate' }} · Pagina {{ $users->currentPage() }} / {{ $users->lastPage() }}</span>
        <div>
            <form method="GET" action="{{ route('aura.admin.users') }}">
                @foreach(request()->except('page') as $key => $value)
                    @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                @endforeach
                <button type="submit" name="page" value="{{ max(1, $users->currentPage() - 1) }}" @disabled($users->onFirstPage())>‹ Înapoi</button>
                <button type="submit" name="page" value="{{ min($users->lastPage(), $users->currentPage() + 1) }}" @disabled(!$users->hasMorePages())>Înainte ›</button>
            </form>
        </div>
    </div>
</x-aura-admin::card>

<x-aura-admin::alert message="Lista conține exclusiv utilizatorii existenți în baza de date. Paginile de profil și editare sunt disponibile numai în mod vizual." />
@endsection
