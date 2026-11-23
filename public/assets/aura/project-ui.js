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

const catalogFilter = document.querySelector('[data-catalog-filter]');
if (catalogFilter) {
    catalogFilter.addEventListener('change', () => catalogFilter.requestSubmit());
    catalogFilter.querySelector('input[type="search"]').addEventListener('search', () => catalogFilter.requestSubmit());
}
