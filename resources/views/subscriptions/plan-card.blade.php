<article data-plan-card="{{ $plan->id }}" class="card {{ $loop->iteration === 2 ? 'highlight' : '' }}">
    <h2>{{ $plan->name }}</h2>
    <div class="plan-price gold">{{ number_format($plan->monthly_price_minor / 100, 2, ',', '.') }} <small>lei / lună</small></div>
    <p>{{ number_format($plan->quarterly_price_minor / 100, 2, ',', '.') }} lei / 3 luni · {{ number_format($plan->quarterly_price_minor / 300, 2, ',', '.') }} lei/lună</p>
    <ul class="steps">
        @if($plan->trial_days > 0)
            <li>✓ Primele {{ $plan->trial_days }} {{ $plan->trial_days === 1 ? 'zi gratuită' : 'zile gratuite' }}</li>
        @endif
        <li>✓ Maximum proiecte simultane: {{ $plan->max_projects ?? 'Nelimitat' }}</li>
        <li>✓ Maximum proiecte Explorator: {{ $plan->max_explorer_projects ?? 'Nelimitat' }}</li>
        <li>✓ Maximum proiecte Mentor: {{ $plan->max_mentor_projects ?? 'Nelimitat' }}</li>
        @foreach($plan->benefits as $benefit)
            <li>✓ {{ $benefit }}</li>
        @endforeach
    </ul>
    @if($publicCatalog)
        <a class="btn {{ $loop->first ? 'secondary' : '' }}" href="{{ route('aura.subscriptions.select', $plan) }}">Alege {{ $plan->name }}</a>
    @else
        <button class="btn {{ $loop->first ? 'secondary' : '' }}" type="button" data-open-plan>Alege {{ $plan->name }}</button>
    @endif
</article>
