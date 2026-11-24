@props(['events'])
<h2>Istoric abonament</h2>
<div class="timeline">
    @forelse($events as $event)
        @php
            $beneficiary = $event->membership?->user ?? $event->promoCode?->reservedUser;
            $planName = $event->membership?->terms['name'] ?? $event->details['after']['name'] ?? $event->plan?->name;
        @endphp
        <div class="event">
            <time datetime="{{ $event->occurred_at->toIso8601String() }}">{{ $event->occurred_at->format('d.m.Y H:i:s') }}</time>
            <span>
                <b>{{ $event->operationLabel() }}</b> · {{ $planName }}
                @if($event->membership) · {{ $event->membership->sourceLabel() }} · Perioada #{{ $event->membership_id }} @endif
                @if($event->promo_code_id) · Cod promoțional #{{ $event->promo_code_id }} @endif
                @if($beneficiary)<br>Beneficiar · {{ $beneficiary->name }} ({{ $beneficiary->email }})@endif
                <br>{{ $event->actor ? 'Efectuat de '.$event->actor->name.' ('.$event->actor->email.')' : 'Efectuat de sistem' }}
                @if($event->reason !== null)<br>Motiv · {{ $event->reason }}@endif
            </span>
        </div>
    @empty
        <p>Nicio operațiune înregistrată.</p>
    @endforelse
</div>
@if($events->hasPages())
    <x-aura-admin::subscription-pagination :items="$events" />
@endif
