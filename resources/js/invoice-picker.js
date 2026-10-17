export function enhanceInvoiceSelect(select) {
    if (select.tagName !== 'SELECT') return null;

    const wrapper = document.createElement('div');
    wrapper.className = 'relative';
    const input = document.createElement('input');
    input.id = 'invoice-search';
    input.className = 'field pr-11';
    input.placeholder = 'Cari nomor, ID invoice, proyek, atau klien';
    input.autocomplete = 'off';
    input.required = select.required;
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', 'invoice-options');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-describedby', 'invoiceId-help invoice-search-status');
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'absolute right-0 top-0 flex h-11 w-11 items-center justify-center text-primary disabled:opacity-60';
    toggle.setAttribute('aria-label', 'Tampilkan pilihan invoice');
    toggle.setAttribute('aria-controls', 'invoice-options');
    toggle.tabIndex = -1;
    toggle.textContent = '▾';
    const list = document.createElement('div');
    list.id = 'invoice-options';
    list.className = 'absolute left-0 right-0 z-20 mt-1 max-h-64 overflow-y-auto rounded-md border border-border bg-white p-1 shadow-md';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', 'Pilihan invoice D-2');
    list.hidden = true;
    const status = document.createElement('p');
    status.id = 'invoice-search-status';
    status.className = 'field-help';
    status.setAttribute('role', 'status');
    wrapper.append(input, toggle, list);
    select.before(wrapper, status);
    document.querySelector(`label[for="${select.id}"]`).htmlFor = input.id;
    select.hidden = true;
    select.required = false;
    let lastValue, matches = [], active = -1;

    function close() {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    }
    function highlight(index) {
        active = index;
        [...list.querySelectorAll('[role="option"]')].forEach((option, i) => {
            option.classList.toggle('bg-slate-100', i === active);
            option.setAttribute('aria-selected', String(matches[i]?.value === select.value));
        });
        if (active >= 0) {
            input.setAttribute('aria-activedescendant', list.children[active].id);
            list.children[active].scrollIntoView({ block: 'nearest' });
        } else input.removeAttribute('aria-activedescendant');
    }
    function choose(option) {
        select.value = option.value;
        lastValue = option.value;
        input.value = option.text;
        input.setCustomValidity('');
        select.dispatchEvent(new Event('change', { bubbles: true }));
        close();
        input.focus();
    }
    function open(filter = '') {
        if (input.disabled) return;
        const terms = filter.trim().toLocaleLowerCase('id').split(/\s+/).filter(Boolean);
        matches = [...select.options].filter((option) => option.value && !option.disabled && terms.every((term) => (option.dataset.search || option.text).toLocaleLowerCase('id').includes(term)));
        list.replaceChildren();
        matches.forEach((option, index) => {
            const row = document.createElement('div');
            row.id = `invoice-option-${index}`;
            row.className = 'min-h-11 cursor-pointer whitespace-normal break-words rounded px-3 py-2 text-sm hover:bg-slate-100';
            row.setAttribute('role', 'option');
            row.textContent = option.text;
            row.addEventListener('pointerdown', (event) => { event.preventDefault(); choose(option); });
            list.append(row);
        });
        if (!matches.length) {
            const empty = document.createElement('p');
            empty.className = 'px-3 py-3 text-sm text-muted';
            empty.textContent = 'Invoice tidak ditemukan. Coba nomor, proyek, atau klien lain.';
            list.append(empty);
        }
        status.textContent = `${matches.length} invoice ditemukan.`;
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        highlight(-1);
    }
    input.addEventListener('focus', () => open(select.value ? '' : input.value));
    input.addEventListener('click', () => { if (list.hidden) open(select.value ? '' : input.value); });
    input.addEventListener('input', () => {
        select.value = ''; lastValue = '';
        input.setCustomValidity(input.value ? 'Pilih invoice dari daftar hasil pencarian.' : '');
        select.dispatchEvent(new Event('change', { bubbles: true }));
        open(input.value);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (event.key === 'Tab') close();
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (list.hidden) open(select.value ? '' : input.value);
            if (matches.length) highlight(active < 0 ? (event.key === 'ArrowDown' ? 0 : matches.length - 1) : (active + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length);
        }
        if (event.key === 'Enter' && !list.hidden) {
            event.preventDefault();
            if (matches.length) choose(matches[active < 0 ? 0 : active]);
        }
    });
    toggle.addEventListener('pointerdown', (event) => event.preventDefault());
    toggle.addEventListener('click', () => {
        if (!list.hidden) close();
        else { input.focus(); open(); }
    });
    document.addEventListener('pointerdown', (event) => { if (!wrapper.contains(event.target)) close(); });
    input.addEventListener('blur', close);

    return {
        sync() {
            input.disabled = toggle.disabled = select.disabled;
            if (lastValue !== select.value) {
                input.value = select.value ? select.selectedOptions[0]?.text || select.value : '';
                lastValue = select.value;
                input.setCustomValidity('');
            }
            if (input.disabled) close();
        },
    };
}
