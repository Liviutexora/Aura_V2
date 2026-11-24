'use strict';

(() => {
    const form = document.querySelector('[data-real-plan-form]');
    if (form) {
        form.querySelectorAll('[data-unlimited-for]').forEach(checkbox => {
            const input = document.getElementById(checkbox.dataset.unlimitedFor);
            const updateLimit = () => {
                input.disabled = checkbox.checked;
                input.required = !checkbox.checked;
            };
            checkbox.addEventListener('change', updateLimit);
            updateLimit();
        });
    }
    const page = document.getElementById('plans-page');
    if (!page) {
        return;
    }

    const body = page.querySelector('#table-body');
    const rows = Array.from(body.querySelectorAll('[data-plan-row]'));
    const search = page.querySelector('[data-plans-search]');
    const status = page.querySelector('[data-plans-status]');
    const sort = page.querySelector('[data-plans-sort]');
    const previous = page.querySelector('[data-plans-previous]');
    const next = page.querySelector('[data-plans-next]');
    let pageNumber = 1;
    let direction = 1;

    function value(row, column) {
        const raw = row.querySelector('[data-sort-' + column + ']')?.getAttribute('data-sort-' + column) ?? '';
        if (column === 'price' || column === 'limits' || column === 'interval') {
            return raw === 'unlimited' ? Infinity : Number(raw);
        }
        return raw;
    }

    function renderPlans() {
        const query = search.value.trim().toLocaleLowerCase('ro');
        const matches = rows.filter(row => (
            (row.textContent + ' ' + row.dataset.reference).toLocaleLowerCase('ro').includes(query)
            && (!status.value || value(row, 'status') === status.value)
        ));
        if (sort.value) {
            matches.sort((first, second) => {
                const a = value(first, sort.value);
                const b = value(second, sort.value);
                const comparison = typeof a === 'number'
                    ? (a === b ? 0 : (a < b ? -1 : 1))
                    : a.localeCompare(b, 'ro');
                return comparison * direction;
            });
        }
        const pageCount = Math.max(1, Math.ceil(matches.length / 6));
        pageNumber = Math.max(1, Math.min(pageNumber, pageCount));
        rows.forEach(row => { row.hidden = true; });
        matches.forEach((row, index) => {
            row.hidden = index < (pageNumber - 1) * 6 || index >= pageNumber * 6;
            body.appendChild(row);
        });
        page.querySelector('[data-plans-count]').textContent = matches.length + ' rezultate · Pagina ' + pageNumber + ' / ' + pageCount;
        previous.disabled = pageNumber === 1;
        next.disabled = pageNumber === pageCount;
        page.querySelector('#table-empty').hidden = matches.length !== 0;
    }

    function resetPage() {
        pageNumber = 1;
        renderPlans();
    }

    search.addEventListener('input', resetPage);
    status.addEventListener('change', resetPage);
    sort.addEventListener('change', resetPage);
    page.querySelector('[data-plans-reverse]').addEventListener('click', () => {
        direction *= -1;
        renderPlans();
    });
    page.querySelectorAll('[data-plans-column]').forEach(button => {
        button.addEventListener('click', () => {
            sort.value = button.dataset.plansColumn;
            resetPage();
        });
    });
    previous.addEventListener('click', () => { pageNumber--; renderPlans(); });
    next.addEventListener('click', () => { pageNumber++; renderPlans(); });
    page.querySelectorAll('[data-plan-preview]').forEach(button => {
        button.addEventListener('click', event => {
            event.preventDefault();
            window.info('Planuri de abonament', 'Această acțiune va fi conectată ulterior. Datele planurilor nu au fost modificate.');
        });
    });
    renderPlans();
})();
