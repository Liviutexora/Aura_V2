(() => {
    const catalog = document.querySelector('[data-plan-catalog]');
    if (!catalog) return;
    const track = catalog.querySelector('.plan-track');
    const controls = catalog.querySelector('.plan-controls');
    if (controls) {
        const [previous, next] = controls.querySelectorAll('button');
        const sync = () => {
            const last = track.scrollWidth - track.clientWidth;
            controls.hidden = last < 2;
            previous.disabled = track.scrollLeft < 1;
            next.disabled = track.scrollLeft >= last - 1;
        };
        controls.addEventListener('click', event => {
            const button = event.target.closest('[data-step]');
            if (!button) return;
            const stride = track.children[1].offsetLeft - track.children[0].offsetLeft;
            track.scrollTo({left: (Math.round(track.scrollLeft / stride) + Number(button.dataset.step)) * stride});
        });
        track.addEventListener('scroll', sync, {passive: true});
        window.addEventListener('resize', sync);
        sync();
    }

    const dialog = document.getElementById('activation-dialog');
    if (!dialog) return;
    const form = dialog.querySelector('form');
    const fields = form.elements;
    const loss = dialog.querySelector('[data-loss]');
    const loadedAt = performance.now();
    let timer;
    function updateLoss() {
        const seconds = Math.max(0, Number(fields.confirm_replace_ends_at.value) - Number(dialog.dataset.serverTime)
            - Math.floor((performance.now() - loadedAt) / 1000));
        loss.querySelector('[data-remaining]').textContent = Math.floor(seconds / 86400) + ' zile, '
            + Math.floor(seconds / 3600) % 24 + ' h, ' + Math.floor(seconds / 60) % 60 + ' min, ' + seconds % 60 + ' s';
    }
    function syncTiming() {
        if (!loss) return;
        const immediate = fields.timing.value === 'now' && fields.plan_id.value !== dialog.dataset.currentPlan;
        loss.hidden = !immediate;
        fields.confirm_replace_membership_id.disabled = !immediate;
        fields.confirm_replace_membership_id.required = immediate;
        fields.confirm_replace_membership_id.checked = false;
        fields.confirm_replace_ends_at.disabled = !immediate;
        clearInterval(timer);
        if (immediate) {
            updateLoss();
            timer = setInterval(updateLoss, 1000);
        }
    }
    function openPlan(card) {
        form.reset();
        fields.plan_id.value = card.dataset.planCard;
        const switching = Boolean(dialog.dataset.currentPlan && dialog.dataset.currentPlan !== fields.plan_id.value);
        dialog.querySelector('[data-switch]').hidden = !switching;
        fields.timing.value = switching || dialog.dataset.currentPlan ? 'period_end' : 'now';
        dialog.querySelector('h2').textContent = card.querySelector('h2').textContent;
        dialog.querySelector('[data-summary]').replaceChildren(
            ...Array.from(card.querySelectorAll('.plan-price, p, .steps'), node => node.cloneNode(true))
        );
        form.querySelector('[role="alert"]').hidden = true;
        syncTiming();
        dialog.showModal();
    }
    fields.timing.addEventListener('change', syncTiming);
    dialog.querySelector('.plan-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('cancel', event => { if (form.inert) event.preventDefault(); });
    dialog.addEventListener('close', () => { clearInterval(timer); fields.code.value = ''; });
    catalog.addEventListener('click', event => {
        if (event.target.closest('[data-open-plan]')) openPlan(event.target.closest('[data-plan-card]'));
    });
    const selected = Array.from(track.children).find(card => card.dataset.planCard && card.dataset.planCard === dialog.dataset.selected);
    if (selected) openPlan(selected);

    document.querySelectorAll('form[data-activation]').forEach(activation => {
        activation.addEventListener('submit', async event => {
            event.preventDefault();
            if (activation.inert) return;
            const error = activation.querySelector('[role="alert"]');
            activation.inert = true;
            if (activation === form) dialog.querySelector('.plan-close').disabled = true;
            error.hidden = true;
            try {
                const response = await fetch(activation.action, {
                    method: 'POST', body: new FormData(activation), credentials: 'same-origin',
                    headers: {Accept: 'application/json'}, cache: 'no-store'
                });
                if (response.status === 201) {
                    location.reload();
                    return;
                }
                const payload = await response.json();
                error.textContent = response.status === 429 ? 'Prea multe încercări. Încearcă din nou peste un minut.'
                    : Object.values(payload.errors || {}).flat().join(' ') || payload.message || 'Activarea a eșuat.';
            } catch {
                error.textContent = 'Rezultatul nu a putut fi confirmat. Reîncarcă pagina pentru a verifica abonamentul.';
            }
            error.hidden = false;
            activation.inert = false;
            if (activation === form) dialog.querySelector('.plan-close').disabled = false;
        });
    });
})();
