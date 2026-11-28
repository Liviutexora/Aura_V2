<section class="card" data-onboarding-plan data-selection-url="{{ route('aura.subscriptions.choose') }}" data-dashboard-url="{{ route('aura.dashboard') }}" style="margin-bottom:24px">
    <p class="eyebrow">Pasul 2 din 2 — Alege abonamentul</p>
    <h2>Activează planul potrivit pentru tine</h2>
    <p>Poți continua mai târziu. Până la activare poți vedea oportunitățile, dar nu poți accepta proiecte.</p>
    <form method="POST" action="{{ route('aura.subscriptions.choose') }}" data-plan-chooser>
        @csrf
        <label for="onboarding-plan">Planuri disponibile</label>
        <select class="wide" id="onboarding-plan" name="plan_id" required>
            <option value="">Alege un plan</option>
            @foreach($activePlans as $plan)
                <option value="{{ $plan->id }}" data-trial-days="{{ $plan->trial_days }}" @selected($selectedPlan?->id === $plan->id)>{{ $plan->name }}</option>
            @endforeach
        </select>
        <p role="alert" data-selection-error hidden></p>
    </form>
    @if($selectionUnavailable)
        <p role="status">Planul ales anterior nu mai este disponibil. Selectează un plan activ.</p>
    @endif
    <div data-selected-summary>
        @if($selectedPlan)
            <h3>{{ $selectedPlan->name }}</h3>
            <p>{{ number_format($selectedPlan->monthly_price_minor / 100, 2, ',', '.') }} lei / lună · {{ number_format($selectedPlan->quarterly_price_minor / 100, 2, ',', '.') }} lei / 3 luni</p>
            <ul class="steps">@foreach($selectedPlan->benefits as $benefit)<li>{{ $benefit }}</li>@endforeach</ul>
        @else
            <p>Selectează un plan pentru a vedea prețul și beneficiile.</p>
        @endif
    </div>
    <button class="btn wide" type="button" data-open-selected-plan @disabled(! $selectedPlan)>Activează prin cupon</button>
    @if($trialEligible)
        <form method="POST" action="{{ route('aura.subscriptions.trial') }}" data-activation data-selected-trial @if(! $selectedPlan || $selectedPlan->trial_days < 1) hidden @endif>
            @csrf
            <input type="hidden" name="plan_id" value="{{ $selectedPlan?->id }}">
            <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <button class="btn secondary wide">Începe trialul gratuit de {{ $selectedPlan?->trial_days ?? 0 }} zile, fără card</button>
            <p role="alert" hidden></p>
        </form>
    @endif
    <p>Plată cu cardul (Stripe) · Disponibil în curând</p>
</section>
