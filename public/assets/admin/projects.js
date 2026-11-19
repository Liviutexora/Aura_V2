'use strict';

function info(title, message) {
    const dialog = document.getElementById('admin-dialog');
    const heading = document.createElement('h2');
    const paragraph = document.createElement('p');
    const close = document.createElement('button');
    heading.textContent = title;
    paragraph.textContent = message;
    close.textContent = 'Închide';
    close.className = 'btn admin-btn secondary';
    close.onclick = () => dialog.close();
    dialog.replaceChildren(heading, paragraph, close);
    dialog.showModal();
}

function alerts() {
    location.assign('/admin/verification?status=pending');
}

function toggleProjectMenu() {
    const existing = document.querySelector('.profile-menu');
    const toggle = document.querySelector('.profile-toggle');
    toggle.setAttribute('aria-expanded', String(!existing));
    if (existing) { existing.remove(); return; }
    const menu = document.createElement('div');
    menu.className = 'profile-menu';
    menu.innerHTML = '<a href="/admin/settings">Profil administrator</a><a href="/dashboard">Dashboard utilizator</a>';
    document.body.append(menu);
}

function globalSearch(value) {
    const results = document.getElementById('global-results');
    results.replaceChildren();
    if (!value.trim()) return;
    const list = document.createElement('div');
    list.className = 'search-results';
    for (const [path,label] of Object.entries({users:'Utilizatori',opportunities:'Oportunități',missions:'Misiuni'})) {
        const link = document.createElement('a');
        link.href = '/admin/' + path + '?q=' + encodeURIComponent(value.trim());
        link.textContent = label + ' · ' + value.trim();
        list.append(link);
    }
    results.append(list);
}

const builder = document.getElementById('step-builder');
if (builder) {
    const list = builder.querySelector('[data-step-list]');
    const template = builder.querySelector('[data-new-step]');
    function numberSteps() {
        Array.from(list.children).forEach((row, index) => {
            row.querySelector('[data-step-number]').textContent = String(index + 1);
            for (const field of row.querySelectorAll('[name]')) {
                field.name = field.name.replace(/^steps\[[^\]]*\]/, 'steps[' + index + ']');
            }
            for (const field of row.querySelectorAll('[id^="step-"]')) {
                const label = field.parentElement.querySelector('label[for]');
                field.id = 'step-' + index + '-' + field.name.split('[').pop().replace(']', '');
                if (label) label.htmlFor = field.id;
            }
            row.querySelector('[data-step-action="up"]').disabled = index === 0;
            row.querySelector('[data-step-action="down"]').disabled = index === list.children.length - 1;
        });
    }
    builder.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.hasAttribute('data-add-step')) {
            list.append(template.content.cloneNode(true));
            numberSteps();
            list.lastElementChild.querySelector('input[name$="[title]"]').focus();
            return;
        }
        const row = button.closest('[data-step-row]');
        if (!row) return;
        switch (button.dataset.stepAction) {
            case 'up': if (row.previousElementSibling) list.insertBefore(row,row.previousElementSibling); break;
            case 'down': if (row.nextElementSibling) list.insertBefore(row.nextElementSibling,row); break;
            case 'remove': row.remove(); break;
            case 'settings': row.querySelector('dialog').showModal(); break;
            case 'close': row.querySelector('dialog').close(); break;
        }
        numberSteps();
    });
    numberSteps();
}
const offer = document.querySelector('[data-allocation-offer]');
const templates = document.querySelector('[data-allocation-template]');
if (offer && templates) {
    function filterTemplates() {
        for (const option of templates.querySelectorAll('[data-offer]')) {
            option.hidden = option.disabled = option.dataset.offer !== offer.value;
        }
        if (templates.selectedOptions[0]?.disabled) templates.value = '';
    }
    offer.addEventListener('change', filterTemplates);
    filterTemplates();
}
const tableFilter = document.getElementById('project-filter');
if (tableFilter) {
    tableFilter.querySelector('input[type="search"]').addEventListener('change', () => tableFilter.requestSubmit());
}

document.addEventListener('click', async event => {
    const link = event.target.closest('[data-project-review]');
    if (!link || event.ctrlKey || event.metaKey) return;
    event.preventDefault();
    try {
        const response = await fetch(link.href, {headers: {Accept:'text/html'}});
        if (!response.ok || response.redirected) { location.assign(response.url || link.href); return; }
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const content = page.querySelector('[data-review-content]');
        if (!content) { location.assign(link.href); return; }
        const dialog = document.getElementById('admin-dialog');
        const heading = document.createElement('h2');
        heading.textContent = 'Verificare misiune';
        const close = document.createElement('button');
        close.className = 'dialog-close';
        close.textContent = '✕';
        close.onclick = () => dialog.close();
        dialog.className = 'admin-dialog';
        dialog.replaceChildren(close,heading,content);
        dialog.showModal();
    } catch {
        location.assign(link.href);
    }
});
document.addEventListener('click', event => {
    const page = event.target.closest('[data-page-url]');
    if (page?.dataset.pageUrl && !page.disabled) location.assign(page.dataset.pageUrl);
    const header = event.target.closest('[data-column-sort]');
    if (header && tableFilter) tableFilter.elements.sort.value = header.dataset.columnSort;
});
