@if($selectedPlanId && ! $activePlans->contains('id', $selectedPlanId))
    <p role="status">Planul selectat nu mai este disponibil.</p>
@endif
<dialog id="activation-dialog" aria-labelledby="activation-plan-name" data-selected="{{ $selectedPlanId }}"
    data-current-plan="{{ $currentMembership?->plan_id }}" data-server-time="{{ now()->timestamp }}">
    <button type="button" class="btn secondary plan-close" aria-label="Închide">×</button>
    <h2 id="activation-plan-name"></h2>
    <div data-summary></div>
    <form method="POST" action="{{ route('aura.subscriptions.redeem') }}" data-activation>
        @csrf
        <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <input type="hidden" name="plan_id">
        <label>Perioadă
            <select name="interval"><option value="monthly">Lunar</option><option value="quarterly">Trimestrial</option></select>
        </label>
        <fieldset><legend>Metodă de activare</legend>
            <label><input type="radio" name="method" value="coupon" checked> Cupon promoțional</label>
            <label><input type="radio" name="method" value="card" disabled> Card bancar (Stripe) · În curând</label>
        </fieldset>
        <p>Cuponul acordă gratuit numărul de zile stabilit la emitere, indiferent de perioada selectată.</p>
        <label>Cod promoțional <input name="code" required maxlength="40" spellcheck="false" autocomplete="off"></label>
        <label data-switch hidden>Aplicare
            <select name="timing"><option value="period_end">La expirarea abonamentului actual</option><option value="now">Imediat</option></select>
        </label>
        @if($currentMembership)
            <label data-loss hidden>
                <input type="checkbox" name="confirm_replace_membership_id" value="{{ $currentMembership->id }}" disabled>
                Confirm pierderea perioadei rămase de la activare până la {{ $currentMembership->ends_at->utc()->format('d.m.Y H:i:s') }} UTC
                (<span data-remaining></span>).
                <input type="hidden" name="confirm_replace_ends_at" value="{{ $currentMembership->ends_at->timestamp }}" disabled>
            </label>
        @endif
        @if($scheduledMembership)
            <p>Ai deja o perioadă programată. Poți folosi cuponul după începerea acesteia, dacă mai este valabil.</p>
        @endif
        <button class="btn" @disabled($scheduledMembership !== null)>Activează prin cupon</button>
        <p role="alert" hidden></p>
    </form>
</dialog>
