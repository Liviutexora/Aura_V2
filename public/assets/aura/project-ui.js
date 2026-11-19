'use strict';

function info(title, message) {
    const dialog = document.getElementById('modal');
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'dialog-close';
    close.textContent = '✕';
    close.onclick = () => dialog.close();
    const heading = document.createElement('h2');
    heading.textContent = title;
    const details = document.createElement('p');
    details.textContent = message;
    dialog.replaceChildren(close, heading, details);
    dialog.showModal();
}

function followProjectHash() {
    const match = location.hash.match(/^#\/(dashboard|opportunities|opportunity|projects|missions|mission)(?:\/(\d+))?$/);
    if (match) {
        const base = {opportunity:'opportunities',mission:'missions'}[match[1]] || match[1];
        location.replace('/' + base + (match[2] ? '/' + match[2] : ''));
    }
}
window.addEventListener('hashchange', followProjectHash);
followProjectHash();
const catalogFilter = document.querySelector('[data-catalog-filter]');
if (catalogFilter) {
    catalogFilter.addEventListener('change', () => catalogFilter.requestSubmit());
    catalogFilter.querySelector('input[type="search"]').addEventListener('search', () => catalogFilter.requestSubmit());
}
