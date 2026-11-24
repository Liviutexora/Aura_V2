<section data-plan-catalog aria-label="Planuri de abonament">
    <div class="catalog plan-track" tabindex="0" aria-label="Planuri disponibile">
        @forelse($activePlans as $plan)
            @include('subscriptions.plan-card')
        @empty
            <p>Momentan nu există planuri active.</p>
        @endforelse
    </div>
    @if($activePlans->count() > 1)
        <div class="plan-controls" hidden>
            <button type="button" class="btn secondary" data-step="-1" aria-label="Planul anterior">←</button>
            <button type="button" class="btn secondary" data-step="1" aria-label="Planul următor">→</button>
        </div>
    @endif
</section>
<link rel="stylesheet" href="{{ asset('assets/aura/subscriptions.css') }}">
<script src="{{ asset('assets/aura/subscriptions.js') }}" defer></script>
