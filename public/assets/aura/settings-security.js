'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('#profile-form');
    const emailField = document.querySelector('#profile-email');
    const saveButton = form?.querySelector('[data-profile-save]');
    const template = document.querySelector('#profile-email-confirmation-template');
    const dialog = document.querySelector('#modal');

    if (
        !(form instanceof HTMLFormElement)
        || !(emailField instanceof HTMLInputElement)
        || !(saveButton instanceof HTMLButtonElement)
        || !(template instanceof HTMLTemplateElement)
        || !(dialog instanceof HTMLDialogElement)
    ) {
        return;
    }

    const normalizeEmail = value => value.trim().toLowerCase();
    const currentEmail = normalizeEmail(template.dataset.currentEmail || '');
    let confirmationGranted = false;

    const passwordField = () => dialog.querySelector('#profile-email-current-password');

    const clearPassword = () => {
        const field = passwordField();

        if (field instanceof HTMLInputElement) {
            field.value = '';
        }
    };

    const closeConfirmation = () => {
        if (dialog.open) {
            dialog.close();
        }
    };

    const openConfirmation = () => {
        confirmationGranted = false;
        dialog.classList.add('profile-email-dialog');
        dialog.innerHTML = template.innerHTML;
        clearPassword();
        dialog.showModal();

        const field = passwordField();

        if (field instanceof HTMLInputElement) {
            field.focus();
        }
    };

    form.addEventListener('submit', event => {
        const emailChanged = normalizeEmail(emailField.value) !== currentEmail;

        if (!emailChanged) {
            confirmationGranted = false;
            clearPassword();

            return;
        }

        if (confirmationGranted) {
            return;
        }

        event.preventDefault();
        openConfirmation();
    });

    dialog.addEventListener('click', event => {
        if (!(passwordField() instanceof HTMLInputElement)) {
            return;
        }

        const target = event.target instanceof Element ? event.target : null;
        const confirmButton = target?.closest('[data-profile-email-confirm]');

        if (confirmButton instanceof HTMLButtonElement) {
            const field = passwordField();

            if (!(field instanceof HTMLInputElement) || !field.checkValidity()) {
                field?.reportValidity();

                return;
            }

            confirmationGranted = true;
            confirmButton.disabled = true;
            form.requestSubmit(saveButton);

            return;
        }

        if (target?.closest('[data-profile-email-cancel]') || event.target === dialog) {
            closeConfirmation();
        }
    });

    dialog.addEventListener('keydown', event => {
        const field = passwordField();

        if (event.key !== 'Enter' || event.target !== field) {
            return;
        }

        event.preventDefault();
        dialog.querySelector('[data-profile-email-confirm]')?.click();
    });

    dialog.addEventListener('cancel', event => {
        if (!(passwordField() instanceof HTMLInputElement)) {
            return;
        }

        event.preventDefault();
        closeConfirmation();
    });

    dialog.addEventListener('close', () => {
        if (!dialog.classList.contains('profile-email-dialog')) {
            return;
        }

        clearPassword();
        confirmationGranted = false;
        dialog.classList.remove('profile-email-dialog');
        dialog.innerHTML = '';
    });

    if (template.dataset.reopen === '1') {
        openConfirmation();
    }
});
