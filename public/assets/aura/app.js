'use strict';

const demoStateKey = 'aura-demo-v1';
let demoState;

try {
    demoState = JSON.parse(localStorage.getItem(demoStateKey));
} catch {
    demoState = null;
}

if (!demoState || typeof demoState !== 'object') {
    demoState = {};
}

if (!Array.isArray(demoState.apps)) {
    demoState.apps = ['Aurelion'];
}

function saveDemoState() {
    localStorage.setItem(demoStateKey, JSON.stringify(demoState));
}

function toast(message) {
    const element = document.getElementById('toast');

    if (!element) {
        return;
    }

    element.textContent = message;
    element.classList.add('show');
    window.setTimeout(() => element.classList.remove('show'), 3300);
}

function info(title, message) {
    const dialog = document.getElementById('modal');

    if (!dialog) {
        return;
    }

    const close = document.createElement('button');
    close.type = 'button';
    close.style.cssText = 'float:right';
    close.setAttribute('aria-label', 'Închide');
    close.textContent = '✕';
    close.addEventListener('click', () => dialog.close());

    const heading = document.createElement('h2');
    heading.textContent = title;

    const details = document.createElement('p');
    details.textContent = message;

    const acknowledge = document.createElement('button');
    acknowledge.type = 'button';
    acknowledge.className = 'btn secondary';
    acknowledge.style.marginTop = '20px';
    acknowledge.textContent = 'Am înțeles';
    acknowledge.addEventListener('click', () => dialog.close());

    dialog.replaceChildren(close, heading, details, acknowledge);
    dialog.showModal();
}

function choosePlan(name) {
    info(
        `Plan Aura ${name}`,
        `Ai ales ${name}. Aceasta este o previzualizare; nu se efectuează plăți. Integrarea Stripe va fi adăugată ulterior.`,
    );
}

function filterTransactions() {
    const tableBody = document.getElementById('transactions');
    const statusFilter = document.getElementById('tx-status');

    if (!tableBody || !statusFilter) {
        return;
    }

    tableBody.querySelectorAll('tr').forEach((row) => {
        row.hidden = statusFilter.value !== 'Toate stările'
            && row.dataset.status !== statusFilter.value;
    });
}

function syncIntegrationCards() {
    document.querySelectorAll('.catalog .card').forEach((card) => {
        const name = card.querySelector('h3')?.textContent?.trim();
        const status = card.querySelector('.status');
        const toggle = Array.from(card.querySelectorAll('button')).find((button) => (
            button.getAttribute('onclick')?.startsWith('toggleApp(')
        ));

        if (!name || !status || !toggle) {
            return;
        }

        let preview = card.querySelector('[data-integration-preview]');

        if (!preview) {
            preview = Array.from(card.querySelectorAll('button')).find((button) => button !== toggle);
            preview?.setAttribute('data-integration-preview', '');
        }

        const connected = demoState.apps.includes(name);
        status.textContent = connected ? 'Conectat · demo' : 'Neconectat';
        status.classList.toggle('pending', !connected);
        toggle.textContent = connected ? 'Deconectează' : 'Conectează';

        if (!connected) {
            preview?.remove();
            return;
        }

        if (!preview) {
            preview = document.createElement('button');
            preview.type = 'button';
            preview.setAttribute('data-integration-preview', '');
            preview.textContent = 'Deschide aplicația ↗';
            preview.addEventListener('click', () => info(
                name,
                'Previzualizare de integrare. Conectarea reală și autentificarea vor fi implementate separat în Laravel.',
            ));
            card.appendChild(preview);
        }
    });
}

function toggleApp(name) {
    demoState.apps = demoState.apps.includes(name)
        ? demoState.apps.filter((app) => app !== name)
        : [...demoState.apps, name];

    saveDemoState();
    syncIntegrationCards();
    toast('Conexiune demo actualizată');
}

const transactionFilter = document.getElementById('tx-status');
transactionFilter?.addEventListener('change', filterTransactions);
filterTransactions();
syncIntegrationCards();
