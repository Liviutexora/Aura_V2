'use strict';

const referralLink = document.getElementById('referral-link');

if (referralLink) {
    const toast = document.getElementById('toast');
    let timer;

    function notify(message) {
        clearTimeout(timer);
        toast.textContent = message;
        toast.classList.add('show');
        timer = setTimeout(() => toast.classList.remove('show'), 3500);
    }

    async function copyReferral() {
        try {
            await navigator.clipboard.writeText(referralLink.value);
        } catch {
            referralLink.focus();
            referralLink.select();
            try {
                if (document.execCommand('copy')) {
                    notify('Linkul a fost copiat.');
                    return;
                }
            } catch {
                // The selected field also permits manual copying.
            }
            notify('Link selectat. Folosește comanda de copiere.');
            return;
        }
        notify('Linkul a fost copiat.');
    }

    document.getElementById('referral-copy').addEventListener('click', copyReferral);
    document.getElementById('referral-share').addEventListener('click', async () => {
        if (navigator.share) {
            try {
                await navigator.share({ title: 'Invitație în AURA', url: referralLink.value });
                return;
            } catch (error) {
                if (error.name === 'AbortError') return;
            }
        }
        await copyReferral();
    });
}
