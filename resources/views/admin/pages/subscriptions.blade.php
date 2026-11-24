@extends('admin.layouts.app')
@section('content')
<div class="admin-heading">
    <div>
        <h1>Abonamente utilizatori</h1>
        <p>
            @isset($events)
                <a href="{{ route('aura.admin.subscriptions') }}">Înapoi la abonamente</a>
            @else
                <a href="{{ route('aura.admin.users') }}">Selectează un utilizator</a> ·
                <a href="{{ route('aura.admin.subscriptions', ['tab' => 'history']) }}">Istoric operațiuni</a>
            @endisset
        </p>
    </div>
    @isset($periods)
        <select name="range" form="subscription-filter" aria-label="Perioada raportului" onchange="this.form.requestSubmit()">
            <option value="">Toate perioadele</option>
            @foreach([7, 30, 90] as $range)
                <option value="{{ $range }}" @selected(($filters['range'] ?? '') == $range)>Ultimele {{ $range }} zile</option>
            @endforeach
        </select>
    @endisset
</div>
@if(session('period_status'))
    <x-aura-admin::alert :message="session('period_status')" />
@endif
@foreach($errors->all() as $error)
    <x-aura-admin::alert :message="$error" />
@endforeach
<x-aura-admin::card class="admin-card">
    @isset($events)
        <x-aura-admin::subscription-history :events="$events" />
    @else
        @php
            $columns = ['user' => 'Utilizator', 'plan' => 'Abonament', 'source' => 'Sursă', 'start' => 'Activare', 'end' => 'Expirare', 'status' => 'Stare'];
            $statuses = ['active' => 'Activ', 'expired' => 'Expirat', 'scheduled' => 'Programat', 'cancelled' => 'Anulat'];
        @endphp
        <form class="toolbar" id="subscription-filter" method="GET" action="{{ route('aura.admin.subscriptions') }}">
            <input type="search" name="q" maxlength="255" aria-label="Caută în tabel" placeholder="Caută după nume, email sau plan…" value="{{ $filters['q'] ?? '' }}">
            <select name="status" aria-label="Filtrează starea" onchange="this.form.requestSubmit()">
                <option value="">Toate stările</option>
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="sort" aria-label="Sortează" onchange="this.form.requestSubmit()">
                <option value="">Ordine implicită</option>
                @foreach($columns as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['sort'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="hidden" name="direction" value="{{ $direction }}">
            <button type="button" class="icon-button" aria-label="Inversează ordinea" onclick="this.form.elements.direction.value=this.form.elements.direction.value==='asc'?'desc':'asc';this.form.requestSubmit()"><x-aura-admin::icon name="sort" /></button>
            <button class="btn admin-btn secondary" type="button" disabled><x-aura-admin::icon name="download" /> Export DEMO</button>
            <noscript><button class="btn admin-btn secondary">Caută</button></noscript>
        </form>
        <div class="table-wrap"><table class="admin-table">
            <thead><tr>
                @foreach($columns as $key => $label)
                    <th scope="col"><a href="{{ route('aura.admin.subscriptions', array_merge($filters, ['sort' => $key, 'direction' => ($filters['sort'] ?? '') === $key && $direction === 'asc' ? 'desc' : 'asc'])) }}">{{ $label }}</a></th>
                @endforeach
                <th scope="col">Zile rămase</th><th scope="col">Acțiuni</th>
            </tr></thead>
            <tbody>
                @foreach($periods as $period)
                    <tr>
                        <td><b>{{ $period->user_name }}</b><br><small>{{ $period->user_email }}</small></td>
                        <td>{{ $period->terms['name'] }}</td>
                        <td>{{ $period->sourceLabel() }}</td>
                        <td>{{ $period->starts_at->format('d.m.Y H:i') }}</td>
                        <td>{{ $period->ends_at->format('d.m.Y H:i') }}</td>
                        <td><x-aura-admin::status-badge :status="$statuses[$period->period_status]" /></td>
                        <td>{{ $period->period_status === 'active' ? (int) ceil($at->diffInDays($period->ends_at)) : '—' }}</td>
                        <td><div class="actions">
                            <a class="icon-button" href="{{ route('aura.admin.users.show', ['user' => $period->user_id, 'tab' => 'subscription']) }}" aria-label="Istoric pentru {{ $period->user_name }}"><x-aura-admin::icon name="eye" /></a>
                            <a class="icon-button" href="{{ route('aura.admin.subscriptions', $filters + ['page' => $periods->currentPage(), 'manage_user' => $period->user_id]) }}" title="Schimbă planul" aria-label="Schimbă planul pentru {{ $period->user_name }}"><x-aura-admin::icon name="edit" /></a>
                            @if($period->period_status === 'scheduled')
                                <button class="icon-button" type="button" title="Anulează" aria-label="Anulează perioada programată pentru {{ $period->user_name }}"
                                    aria-haspopup="dialog" data-cancel-period="{{ route('aura.admin.subscriptions.periods.cancel', $period) }}"
                                    data-user="{{ $period->user_name }}" data-plan="{{ $period->terms['name'] }}"
                                    data-range="{{ $period->starts_at->format('d.m.Y H:i T') }} – {{ $period->ends_at->format('d.m.Y H:i T') }}"
                                    data-start="{{ $period->starts_at->timestamp }}" data-end="{{ $period->ends_at->timestamp }}">×</button>
                            @endif
                        </div></td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
        <x-aura-admin::subscription-pagination :items="$periods" />
        @if($periods->isEmpty())
            <x-aura-admin::empty-state title="Niciun abonament găsit" description="Nu există perioade de abonament pentru selecția curentă." />
        @endif
    @endisset
</x-aura-admin::card>
@isset($periods)
    @if($periods->contains('period_status', 'scheduled'))
        <dialog class="admin-dialog" id="cancel-period-dialog" aria-labelledby="cancel-period-title">
            <h2 id="cancel-period-title">Anulează perioada programată</h2>
            <p><b data-cancel-user></b> · <span data-cancel-plan></span></p>
            <p data-cancel-range></p>
            <form method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <input type="hidden" name="starts_at">
                <input type="hidden" name="ends_at">
                <div class="form-actions">
                    <button type="submit" class="btn admin-btn">Confirmă anularea</button>
                    <button type="button" class="btn admin-btn secondary" data-dismiss-cancellation autofocus>Înapoi</button>
                </div>
            </form>
        </dialog>
    @endif
    <script src="{{ asset('assets/admin/memberships.js') }}" defer></script>
@endisset
@isset($recipient)
    <template id="subscription-form-template" data-auto-open>
        <h2 id="subscription-dialog-title">Schimbă planul</h2>
        <p>{{ $recipient->name }} · {{ $recipient->email }}</p>
        <p>@if($currentPeriod){{ $currentPeriod->terms['name'] }} · Activ până la {{ $currentPeriod->ends_at->format('d.m.Y H:i T') }}. Același plan se prelungește la expirare.@else Fără abonament activ. Perioada acordată începe imediat. @endif</p>
        <form class="admin-form" method="POST" action="{{ route('aura.admin.subscriptions.grant', $recipient) }}" data-current-plan="{{ $currentPeriod?->plan_id }}">
            @csrf
            <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <div><label for="grant-plan">Abonament</label>
                <select name="plan_id" id="grant-plan" required>
                    <option value="">Selectează planul</option>
                    @foreach($activePlans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach
                </select>
            </div>
            <div><label for="grant-days">Durata acordată (zile)</label><input type="number" id="grant-days" name="duration_days" min="1" max="3650" required></div>
            <div class="full"><label for="grant-reason">Motivul schimbării</label><textarea id="grant-reason" name="reason" maxlength="1000" required></textarea></div>
            <div><label for="grant-timing">Momentul aplicării</label>
                <select name="timing" id="grant-timing">
                    @if($currentPeriod)<option value="period_end">La expirarea perioadei active</option>@endif
                    <option value="now">Imediat</option>
                </select>
            </div>
            @if($currentPeriod)
                <div class="full" data-confirmation hidden><label>
                    <input type="checkbox" name="confirm_replace_membership_id" value="{{ $currentPeriod->id }}" disabled>
                    Confirm pierderea perioadei rămase din {{ $currentPeriod->terms['name'] }}.
                </label></div>
            @endif
            <p class="helper full" data-form-error role="alert" hidden></p>
            <div class="form-actions full"><button class="btn admin-btn" @disabled($activePlans->isEmpty())>Confirmă schimbarea</button>
                <button class="btn admin-btn secondary" type="button" data-close>Renunță</button></div>
        </form>
    </template>
    <template id="saved-membership-template">
        <h2 id="subscription-dialog-title">Abonamentul a fost salvat</h2>
        <div class="form-actions"><button type="button" class="btn admin-btn" data-close>Înapoi la abonamente</button></div>
    </template>
@endisset
@endsection
