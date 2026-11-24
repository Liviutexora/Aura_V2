(() => {
    const dialog = document.getElementById('admin-dialog');
    const template = document.getElementById('subscription-form-template');
    if (!dialog || !template) return;

    let ownsDialog = false;
    let sending = false;
    let saved = false;

    function openForm() {
        if (dialog.open) return;
        dialog.replaceChildren(template.content.cloneNode(true));
        dialog.className = 'admin-dialog';
        dialog.setAttribute('aria-labelledby', 'subscription-dialog-title');
        ownsDialog = true;
        const form = dialog.querySelector('form');
        if (form.dataset.currentPlan) {
            const {plan_id: plan, timing, confirm_replace_membership_id: confirmation} = form.elements;
            const updateTiming = () => {
                const extending = plan.value === form.dataset.currentPlan;
                if (extending) timing.value = 'period_end';
                timing.querySelector('[value="now"]').disabled = extending;
                const replacing = Boolean(plan.value) && !extending && timing.value === 'now';
                form.querySelector('[data-confirmation]').hidden = !replacing;
                confirmation.disabled = !replacing;
                confirmation.required = replacing;
                confirmation.checked = false;
            };
            plan.addEventListener('change', updateTiming);
            timing.addEventListener('change', updateTiming);
            updateTiming();
        }
        form.addEventListener('submit', submit);
        dialog.showModal();
    }

    async function submit(event) {
        event.preventDefault();
        if (sending || saved) return;
        const form = event.currentTarget;
        const error = form.querySelector('[data-form-error]');
        const submitButton = form.querySelector('button:not([type="button"])');
        const body = new FormData(form);
        sending = true;
        form.inert = true;
        submitButton.disabled = true;
        error.hidden = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST', body, credentials: 'same-origin', cache: 'no-store',
                headers: {Accept: 'application/json'}
            });
            const payload = await response.json();
            if (response.status !== 201) {
                const errors = Object.values(payload.errors || {}).flat();
                const message = response.status === 419 ? 'Sesiunea a expirat. Reîncarcă pagina.' : payload.message;
                throw new Error(errors.join(' ') || message || 'Salvarea a eșuat.');
            }
            saved = true;
            if (form.hasAttribute('data-issue-code')) {
                dialog.replaceChildren(document.getElementById('issued-code-template').content.cloneNode(true));
                const field = dialog.querySelector('#issued-promo-code');
                field.value = payload.code;
                dialog.querySelector('[data-copy-code]').addEventListener('click', () => copyCode(field));
                field.focus();
            } else {
                dialog.replaceChildren(document.getElementById('saved-membership-template').content.cloneNode(true));
                dialog.querySelector('[data-close]').focus();
            }
        } catch (failure) {
            error.textContent = failure instanceof TypeError || failure instanceof SyntaxError
                ? 'Răspunsul nu a putut fi confirmat. Verifică istoricul înaintea unei operațiuni noi.'
                : failure.message;
            error.hidden = false;
        } finally {
            sending = false;
            form.inert = false;
            submitButton.disabled = false;
        }
    }

    async function copyCode(field) {
        const status = dialog.querySelector('[data-copy-status]');
        try {
            await navigator.clipboard.writeText(field.value);
            status.textContent = 'Cod copiat.';
        } catch {
            field.focus();
            field.select();
            status.textContent = 'Copiază manual codul selectat.';
            if (document.execCommand?.('copy')) status.textContent = 'Cod copiat.';
        }
    }

    function clearDialog() {
        const field = dialog.querySelector('#issued-promo-code');
        if (field) field.value = '';
        dialog.replaceChildren();
        dialog.removeAttribute('aria-labelledby');
        ownsDialog = false;
    }

    dialog.addEventListener('click', event => {
        if (ownsDialog && !sending && event.target.closest('[data-close]')) dialog.close();
    });
    dialog.addEventListener('cancel', event => {
        if (ownsDialog && sending) event.preventDefault();
    });
    dialog.addEventListener('close', () => {
        if (!ownsDialog) return;
        clearDialog();
        if (template.hasAttribute('data-auto-open')) {
            const list = new URL(window.location.href);
            list.searchParams.delete('manage_user');
            window.location.replace(list.href);
        } else if (saved) {
            window.location.reload();
        }
    });
    window.addEventListener('pagehide', () => {
        if (ownsDialog) clearDialog();
    });

    document.querySelector('[data-open-subscription-form]')?.addEventListener('click', openForm);
    if (template.hasAttribute('data-auto-open')) openForm();
})();
