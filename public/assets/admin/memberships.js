(() => {
    const dialog = document.getElementById('cancel-period-dialog');
    if (!dialog) return;

    const form = dialog.querySelector('form');
    document.querySelectorAll('[data-cancel-period]').forEach(button => {
        button.addEventListener('click', () => {
            form.action = button.dataset.cancelPeriod;
            form.elements.starts_at.value = button.dataset.start;
            form.elements.ends_at.value = button.dataset.end;
            dialog.querySelector('[data-cancel-user]').textContent = button.dataset.user;
            dialog.querySelector('[data-cancel-plan]').textContent = button.dataset.plan;
            dialog.querySelector('[data-cancel-range]').textContent = button.dataset.range;
            dialog.showModal();
        });
    });
    dialog.querySelector('[data-dismiss-cancellation]').addEventListener('click', () => dialog.close());
})();

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
                field.value = (payload.codes || [{code: payload.code}]).map(item => item.code).join('\n');
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

    async function copyValue(value) {
        try {
            await navigator.clipboard.writeText(value);
            return true;
        } catch {
            const field = document.createElement('textarea');
            field.value = value;
            field.setAttribute('readonly', '');
            field.style.position = 'fixed';
            field.style.opacity = '0';
            document.body.appendChild(field);
            field.select();
            const copied = document.execCommand?.('copy') === true;
            field.remove();
            return copied;
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
    document.querySelectorAll('[data-delete-promo]').forEach(form => {
        form.addEventListener('submit', event => {
            if (!window.confirm('Ștergi definitiv acest cupon? Operațiunea nu poate fi anulată.')) {
                event.preventDefault();
                return;
            }
            form.elements.confirm_delete.value = '1';
            if (event.submitter) event.submitter.disabled = true;
        });
    });
    document.querySelectorAll('[data-copy-promo]').forEach(button => {
        button.addEventListener('click', async () => {
            if (await copyValue(button.dataset.copyPromo)) {
                button.textContent = 'Copiat';
                window.setTimeout(() => { button.textContent = 'Copiază'; }, 1500);
            } else {
                button.textContent = 'Copiere indisponibilă';
            }
        });
    });
    if (template.hasAttribute('data-auto-open')) openForm();
})();
