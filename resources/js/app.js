import { escapeHtml as e, cents, money, decimal, dateLabel, taxPeriodLabel, statusBadge, safeNext } from './lib.js';
import { enhanceInvoiceSelect } from './invoice-picker.js';

const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
const config = JSON.parse($('#e1-config').textContent);
const page = document.body.dataset.page;
const tokenKey = 'e1.accessToken';
const period = document.body.dataset.period;
const invoiceId = document.body.dataset.invoiceId;
const icon = (name) => `<svg class="icon" aria-hidden="true"><use href="#solar-${name}"></use></svg>`;
const table = (headers, rows, label = 'Data pajak') => `<div class="data-scroll" role="region" aria-label="${e(label)}" tabindex="0"><table class="data-table"><thead><tr>${headers.map((h) => `<th scope="col"${/^(DPP|PPN|PPnBM|Jumlah|Selisih)/.test(h) ? ' class="money"' : ''}>${e(h)}</th>`).join('')}</tr></thead><tbody>${rows.join('')}</tbody></table></div>`;
const td = (value, cls = '') => `<td class="${cls}">${value}</td>`;
const link = (href, text, cls = '') => `<a class="text-link ${cls}" href="${e(href)}">${e(text)}</a>`;
const detailHref = (id) => `/tax-invoices/${encodeURIComponent(id)}`;
const loading = (message) => `<div class="space-y-4 p-5" role="status"><p class="text-sm text-muted">${e(message)}</p><div class="skeleton-bar w-2/3"></div><div class="skeleton-bar"></div><div class="skeleton-bar w-4/5"></div></div>`;
const empty = (title, message) => `<div class="empty-state"><h3>${e(title)}</h3><p>${e(message)}</p></div>`;

function notice(message, variant = 'error', target = '#app-alert') {
    const element = $(target);
    element.textContent = message;
    element.className = `notice ${variant === 'error' ? 'notice-error' : variant === 'success' ? 'notice-success' : ''} ${target === '#app-alert' ? 'mb-5' : ''}`;
    element.hidden = false;
    element.setAttribute('tabindex', '-1');
    if (variant === 'error') element.focus({ preventScroll: true });
}

function flash(message) { sessionStorage.setItem('e1.flash', message); }
function clearNotice(target = '#app-alert') { $(target).hidden = true; }

async function api(path, options = {}) {
    const headers = new Headers(options.headers);
    const token = sessionStorage.getItem(tokenKey);
    if (token) headers.set('Authorization', `Bearer ${token}`);
    headers.set('Accept', 'application/json');
    const body = options.body;
    if (body && !(body instanceof FormData)) headers.set('Content-Type', 'application/json');
    let response;
    try { response = await fetch(path.startsWith('/api/') ? path : `/api/v1${path}`, { ...options, headers, body: body && !(body instanceof FormData) ? JSON.stringify(body) : body }); }
    catch { throw new Error('Server tidak dapat dihubungi. Periksa koneksi lalu coba kembali.'); }
    if (response.status === 401) {
        sessionStorage.removeItem(tokenKey);
        if (page !== 'login') location.assign(`/login?next=${encodeURIComponent(location.pathname + location.search)}`);
    }
    if (response.status === 403 && page !== 'login') {
        $('#page-content').hidden = true;
        $('#access-denied').hidden = false;
    }
    if (!response.ok) {
        const data = await response.json().catch(() => null);
        const error = new Error(data?.message || `Permintaan gagal (HTTP ${response.status}). Coba kembali.`);
        error.code = data?.errorCode;
        error.status = response.status;
        throw error;
    }
    if (options.binary) return response;
    const payload = await response.json();
    return payload.data;
}

async function busy(element, action, label = 'Memproses…') {
    const buttons = element.matches('button') ? [element] : $$('button', element);
    const previous = buttons.map((button) => [button, button.disabled, button.innerHTML]);
    buttons.forEach((button) => { button.disabled = true; });
    const submit = element.matches('button') ? element : $('button[type="submit"]', element);
    if (submit) submit.textContent = label;
    element.setAttribute('aria-busy', 'true');
    try { return await action(); }
    finally { element.removeAttribute('aria-busy'); previous.forEach(([button, disabled, html]) => { button.disabled = disabled; button.innerHTML = html; }); }
}

function validateFile(input, required = true) {
    const file = input.files[0];
    if (!file && !required) return;
    if (!file) throw new Error('Pilih berkas PDF terlebih dahulu.');
    if (!/\.pdf$/i.test(file.name)) throw new Error('Berkas harus berupa PDF.');
    if (file.size > config.uploadMaxKb * 1024) throw new Error(`Ukuran PDF melebihi ${config.uploadMaxKb / 1024} MB.`);
}

function details(values) { return `<dl class="detail-grid">${values.map(([name, value]) => `<div><dt>${e(name)}</dt><dd>${value}</dd></div>`).join('')}</dl>`; }
function itemsTable(items) {
    return table(['Kode', 'Barang / jasa', 'Harga / penggantian / termin', 'Potongan', 'Uang muka diterima'], items.map((item) => `<tr>${td(e(item.itemCode))}${td(e(item.itemName))}${td(money(item.saleReplacementAdvanceTermAmount), 'money')}${td(money(item.discountAmount), 'money')}${td(money(item.receivedAdvanceAmount), 'money')}</tr>`), 'Item barang dan jasa');
}

const dialogTriggers = new WeakMap();
function openDialog(id) {
    const dialog = $(`#${id}`);
    dialogTriggers.set(dialog, document.activeElement);
    if (!dialog.open) dialog.showModal();
}
$$('dialog').forEach((dialog) => dialog.addEventListener('close', () => dialogTriggers.get(dialog)?.focus()));
document.addEventListener('click', (event) => {
    const close = event.target.closest('[data-close-dialog]');
    if (close) close.closest('dialog').close();
});

function configureMenu() {
    const menu = $('#sidebar');
    const open = $('#open-menu');
    const backdrop = $('#sidebar-backdrop');
    const desktop = matchMedia('(min-width: 1024px)');
    menu.inert = !desktop.matches;
    let previous;
    function toggle(show) {
        if (show) previous = document.activeElement;
        menu.classList.toggle('-translate-x-full', !show);
        menu.inert = !show && !desktop.matches;
        backdrop.hidden = !show;
        open.setAttribute('aria-expanded', String(show));
        document.body.style.overflow = show ? 'hidden' : '';
        if (show) { menu.setAttribute('role', 'dialog'); menu.setAttribute('aria-modal', 'true'); $('#close-menu').focus(); }
        else { menu.removeAttribute('role'); menu.removeAttribute('aria-modal'); previous?.focus(); }
    }
    open.onclick = () => toggle(true);
    $('#close-menu').onclick = () => toggle(false);
    backdrop.onclick = () => toggle(false);
    document.addEventListener('keydown', (event) => {
        if (backdrop.hidden) return;
        if (event.key === 'Escape') toggle(false);
        if (event.key === 'Tab') {
            const controls = $$('a, button', menu).filter((element) => element.getClientRects().length);
            const first = controls[0], last = controls.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    desktop.addEventListener('change', () => { if (!backdrop.hidden) toggle(false); menu.inert = !desktop.matches; });
    $('#logout').onclick = () => { sessionStorage.removeItem(tokenKey); location.assign('/login'); };
}

function tokenIdentity(token) {
    try {
        const payload = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
        const data = JSON.parse(new TextDecoder().decode(Uint8Array.from(atob(payload.padEnd(Math.ceil(payload.length / 4) * 4, '=')), (c) => c.charCodeAt(0))));
        return { account: data[config.claimAccount] || data.sub || 'Pengguna F-1', role: data[config.claimRole] || data.role || data.peran || 'Pengguna berwenang' };
    } catch { return { account: 'Pengguna F-1', role: 'Pengguna berwenang' }; }
}

async function loginPage() {
    async function enter(token) {
        sessionStorage.setItem(tokenKey, token.trim().replace(/^Bearer\s+/i, ''));
        try { await api('/tax-invoices?pageSize=1'); }
        catch (error) { sessionStorage.removeItem(tokenKey); throw error; }
        location.assign(safeNext(new URLSearchParams(location.search).get('next')));
    }
    if (config.demoLogin && $('#demo-login')) $('#demo-login').onsubmit = async (event) => {
        event.preventDefault(); clearNotice();
        try { await busy(event.currentTarget, async () => {
            const payload = Object.fromEntries(new FormData($('#demo-login')));
            const data = await api('/api/dev/f1/login', { method: 'POST', body: payload });
            await enter(data.accessToken);
        }, 'Memeriksa akses…'); } catch (error) { notice(error.message); }
    };
}

function invoiceRows(items) {
    return items.map((item) => `<tr>${td(link(detailHref(item.taxInvoiceId), item.taxInvoiceNumber, 'whitespace-nowrap'))}${td(e(dateLabel(item.taxInvoiceDate)), 'whitespace-nowrap')}${td(e(item.invoiceId), 'id-text')}${td(e(item.projectId) || 'Belum tersedia', 'id-text')}${td(e(item.clientId) || 'Belum tersedia', 'id-text')}${td(e(taxPeriodLabel(item.taxPeriod)), 'whitespace-nowrap')}${td(money(item.dpp), 'money')}${td(money(item.ppn), 'money')}${td(statusBadge(item.status, !!item.replacedByTaxInvoiceId))}</tr>`);
}

async function invoicePage() {
    const form = $('#filters');
    const params = new URLSearchParams(location.search);
    const { periods } = await api('/api/ui/tax-recap-periods');
    const selectedPeriod = params.get('taxPeriod');
    if (selectedPeriod && /^[0-9]{4}-(0[1-9]|1[0-2])$/.test(selectedPeriod) && !periods.includes(selectedPeriod)) periods.push(selectedPeriod);
    for (const value of periods.sort().reverse()) $('#taxPeriod', form).add(new Option(taxPeriodLabel(value), value));
    for (const field of $$('input, select', form)) field.value = params.get(field.name) || '';
    let current = Number(params.get('page') || 1);
    if (!Number.isSafeInteger(current) || current < 1) current = 1;
    let request = 0;
    async function load() {
        const generation = ++request;
        clearNotice();
        const query = new URLSearchParams();
        for (const [name, value] of new FormData(form)) if (value.trim()) query.set(name, value.trim());
        query.set('page', String(current)); query.set('pageSize', '20');
        history.replaceState(null, '', `${location.pathname}?${query}`);
        $('#invoice-list').innerHTML = loading('Memuat daftar faktur…');
        try {
            const data = await api(`/tax-invoices?${query}`);
            if (generation !== request) return;
            $('#invoice-count').textContent = `${data.pagination.totalItems} faktur`;
            $('#invoice-list').innerHTML = data.items.length ? table(['NSFP', 'Tanggal e-Faktur', 'Invoice', 'Proyek', 'Klien', 'Masa pajak', 'DPP', 'PPN', 'Status'], invoiceRows(data.items), 'Daftar faktur keluaran') : empty('Data tidak ditemukan', 'Tidak ada faktur yang sesuai. Ubah filter atau catat e-Faktur keluaran.');
            $('[data-page-info]').textContent = `Halaman ${data.pagination.page} dari ${Math.max(1, Math.ceil(data.pagination.totalItems / data.pagination.pageSize))} · ${data.pagination.totalItems} faktur`;
            $('[data-prev]').disabled = current <= 1;
            $('[data-next]').disabled = current * data.pagination.pageSize >= data.pagination.totalItems;
        } catch (error) {
            if (generation !== request) return;
            $('#invoice-list').innerHTML = empty('Daftar faktur gagal dimuat', 'Filter tetap tersimpan. Tekan Cari untuk mencoba kembali.');
            $('[data-prev]').disabled = $('[data-next]').disabled = true;
            notice(error.message);
        }
    }
    form.onsubmit = async (event) => { event.preventDefault(); current = 1; await busy(form, load, 'Mencari…'); };
    $('#reset-filters').onclick = () => { form.reset(); $$('input, select', form).forEach((field) => { field.value = ''; }); current = 1; load(); };
    $('[data-prev]').onclick = () => { current--; load(); };
    $('[data-next]').onclick = () => { current++; load(); };
    await load();
}

function extractionPreview(data) {
    return `${details([['Nomor faktur / NSFP', e(data.taxInvoiceNumber)], ['Tanggal e-Faktur', e(dateLabel(data.taxInvoiceDate))], ['Masa pajak', e(taxPeriodLabel(data.taxPeriod))], ['Status saat disimpan', statusBadge('NORMAL')]])}<div class="data-scroll my-6">${itemsTable(data.items)}</div>${details([['DPP', money(data.dpp)], ['PPN', money(data.ppn)], ['PPnBM', money(data.ppnbm)]])}`;
}

async function createPage() {
    const form = $('#invoice-form'), file = $('#eFakturFile'), invoice = $('#invoiceId'), save = $('#save-invoice');
    const replacesId = new URLSearchParams(location.search).get('replacesTaxInvoiceId');
    let extracted = false, processing = false, revision = 0, replacementReady = false;
    let catalogReady = invoice.tagName !== 'SELECT';
    const catalog = new Map();
    const picker = enhanceInvoiceSelect(invoice);
    $('#file-limit').textContent = `Maksimal ${config.uploadMaxKb / 1024} MB per PDF.`;
    const canSave = () => {
        save.disabled = processing || (replacesId ? !replacementReady || !file.files[0] : !extracted);
        $('#extract').disabled = processing || !catalogReady || !invoice.value;
        picker?.sync();
    };
    function showInvoiceContext() {
        const context = $('#invoice-context');
        if (!context) return;
        const selected = catalog.get(invoice.value);
        context.innerHTML = selected ? details([
            ['ID invoice', e(selected.invoiceId)],
            ['Nomor invoice', e(selected.invoiceNumber || 'Belum tersedia')],
            ['Tanggal invoice', e(dateLabel(selected.invoiceDate))],
            ['Proyek', e(selected.projectName || selected.projectId || 'Belum tersedia')],
            ['Klien', e(selected.clientName || selected.clientId || 'Belum tersedia')],
            ['Status invoice', e(selected.status || 'Belum tersedia')],
            ['DPP invoice', selected.dppAmount == null ? 'Belum tersedia' : money(selected.dppAmount)],
            ['PPN invoice', selected.ppnAmount == null ? 'Belum tersedia' : money(selected.ppnAmount)],
        ]) : replacesId && invoice.value
            ? `<p class="text-sm text-muted">Invoice ${e(invoice.value)} mengikuti faktur asal dan tidak dapat diganti.</p>`
            : '<p class="text-sm text-muted">Pilih invoice untuk melihat proyek, klien, dan nilai tagihan.</p>';
    }
    async function loadInvoiceCatalog() {
        const context = $('#invoice-context'), retry = $('#retry-invoices');
        if (!context) return;
        const previous = invoice.value;
        catalogReady = false; invoice.disabled = true; retry.hidden = true; canSave();
        context.innerHTML = loading('Memuat invoice dummy D-2…');
        try {
            const data = await api('/api/ui/d2/invoices');
            catalog.clear();
            invoice.replaceChildren(new Option('Pilih invoice D-2', ''));
            for (const item of data.invoices) {
                catalog.set(item.invoiceId, item);
                invoice.add(new Option(`${item.invoiceNumber || item.invoiceId} · ${item.projectName || item.projectId || 'Proyek belum tersedia'} · ${item.invoiceId}`, item.invoiceId));
                invoice.options[invoice.options.length - 1].dataset.search = [item.invoiceId, item.invoiceNumber, item.projectId, item.projectName, item.clientId, item.clientName].filter(Boolean).join(' ');
            }
            if (previous && replacesId && !catalog.has(previous)) invoice.add(new Option(previous, previous));
            invoice.value = previous;
            catalogReady = true;
            invoice.disabled = !!replacesId || !catalog.size;
            showInvoiceContext();
            if (!catalog.size) context.innerHTML = '<p class="text-sm text-muted">Tidak ada invoice tanpa faktur aktif. Tambahkan invoice D-2 baru atau batalkan faktur melalui detail jika diperlukan.</p>';
            retry.hidden = catalog.size > 0;
        } catch (error) {
            context.textContent = `Daftar invoice gagal dimuat. ${error.message}`;
            retry.hidden = false;
        } finally { canSave(); }
    }
    function invalidate() {
        revision++; extracted = false; canSave();
        if (!replacesId) $('#extraction-preview').innerHTML = empty('PDF belum diperiksa', 'Periksa kembali PDF setelah mengganti invoice atau berkas.');
    }
    file.onchange = invalidate; invoice.oninput = invalidate;
    invoice.onchange = () => { invalidate(); showInvoiceContext(); };
    if ($('#retry-invoices')) $('#retry-invoices').onclick = () => loadInvoiceCatalog();
    await loadInvoiceCatalog();
    if (replacesId) {
        $('#create-title').textContent = 'Catat Faktur Pengganti';
        $('#extract').hidden = true;
        $('#replacement-info').hidden = false;
        $('#replacement-info').textContent = 'Faktur pengganti wajib menggunakan invoice yang sama. Pratinjau belum tersedia untuk invoice dengan faktur aktif; PDF diperiksa saat penyimpanan.';
        $('#extraction-preview').innerHTML = empty('Hasil tersedia setelah penyimpanan', 'Unggah PDF pengganti. Identitas dan nominal dibaca server dari PDF saat Anda menyimpan.');
        const original = await api(`/tax-invoices/${encodeURIComponent(replacesId)}`);
        if (original.taxInvoice.status === 'CANCELLED' || original.replacedByTaxInvoiceId) throw new Error('Faktur asal sudah dibatalkan atau terganti. Buka faktur yang masih aktif.');
        if (invoice.tagName === 'SELECT') {
            if (![...invoice.options].some((option) => option.value === original.taxInvoice.invoiceId)) invoice.add(new Option(original.taxInvoice.invoiceId, original.taxInvoice.invoiceId));
            invoice.disabled = true;
        } else invoice.readOnly = true;
        invoice.value = original.taxInvoice.invoiceId;
        showInvoiceContext();
        const href = detailHref(replacesId);
        $('#create-back').href = $('#cancel-create').href = href;
        $('#original-reference').hidden = false;
        $('#original-reference').innerHTML = `<p class="text-xs text-muted">Faktur asal</p>${link(href, original.taxInvoice.taxInvoiceNumber)}`;
        replacementReady = true; canSave();
    }
    $('#extract').onclick = async () => {
        clearNotice();
        if (!form.reportValidity()) return;
        try {
            validateFile(file); processing = true; canSave();
            const captured = revision;
            const body = new FormData(form);
            body.set('invoiceId', invoice.value);
            $('#extraction-preview').innerHTML = loading('Membaca identitas dan nilai dari PDF…');
            const data = await busy($('#extract'), () => api('/tax-invoices/extractions', { method: 'POST', body }), 'Memeriksa PDF…');
            if (captured === revision) { extracted = true; $('#extraction-preview').innerHTML = extractionPreview(data); }
        } catch (error) {
            extracted = false;
            $('#extraction-preview').innerHTML = empty('PDF e-Faktur tidak dapat diproses', 'Periksa invoice dan berkas, lalu unggah PDF yang valid. Belum ada faktur yang tersimpan.');
            notice(error.message);
        } finally { processing = false; canSave(); }
    };
    form.onsubmit = async (event) => {
        event.preventDefault(); if (processing || (!replacesId && !extracted) || (replacesId && !replacementReady)) return;
        clearNotice();
        try {
            validateFile(file);
            const body = new FormData(form);
            body.set('invoiceId', invoice.value);
            if (replacesId) body.set('replacesTaxInvoiceId', replacesId);
            processing = true;
            // Freeze the invoice and PDF whose extraction the user reviewed.
            const inputs = [invoice, file].map((input) => [input, input.disabled]);
            inputs.forEach(([input]) => { input.disabled = true; });
            picker?.sync();
            try {
                const data = await busy(form, () => api('/tax-invoices/outgoing', { method: 'POST', body }), 'Menyimpan faktur…');
                flash('Faktur berhasil disimpan.'); location.assign(detailHref(data.taxInvoiceId));
            } finally { inputs.forEach(([input, disabled]) => { input.disabled = disabled; }); }
        } catch (error) { notice(error.message); }
        finally { processing = false; canSave(); }
    };
    canSave();
}

function correctionItem(item = {}) {
    return `<div class="rounded-md border border-border p-4" data-correction-item><div class="grid gap-3 sm:grid-cols-2">${[['itemCode', 'Kode', item.itemCode], ['itemName', 'Nama barang / jasa', item.itemName], ['saleReplacementAdvanceTermAmount', 'Harga / penggantian / termin', item.saleReplacementAdvanceTermAmount ?? '0.00'], ['discountAmount', 'Potongan', item.discountAmount ?? '0.00'], ['receivedAdvanceAmount', 'Uang muka diterima', item.receivedAdvanceAmount ?? '0.00']].map(([name, label, value]) => `<label class="block text-xs font-medium">${label}<input class="field mt-2" data-item-field="${name}" value="${e(value)}" required ${['itemCode', 'itemName'].includes(name) ? '' : 'inputmode="decimal" pattern="[0-9]+([.][0-9]{1,2})?"'}></label>`).join('')}</div><button type="button" class="btn btn-ghost mt-2" data-remove-item>${icon('trash')}Hapus item</button></div>`;
}

const historyLabels = {
    taxInvoiceNumber: 'Nomor faktur / NSFP',
    taxInvoiceDate: 'Tanggal e-Faktur',
    taxPeriod: 'Masa pajak',
    dpp: 'DPP',
    ppn: 'PPN',
    ppnbm: 'PPnBM',
    status: 'Status faktur',
    replacesTaxInvoiceId: 'Faktur pengganti dari',
    replacedByTaxInvoiceId: 'Digantikan oleh',
};
const historyActions = {
    CREATE: 'Pencatatan faktur',
    UPDATE: 'Koreksi faktur',
    STATUS_CHANGE: 'Perubahan status',
    DELETE: 'Penghapusan faktur',
};
const historyActionIcons = {
    CREATE: 'plus',
    UPDATE: 'edit',
    STATUS_CHANGE: 'replace',
    DELETE: 'close',
};

function historyValue(field, value) {
    if (value === null || value === undefined || value === '') return '<span class="text-muted">Tidak ada</span>';
    if (field === 'status') return statusBadge(value);
    if (field === 'taxInvoiceDate') return e(dateLabel(value));
    if (field === 'taxPeriod') return e(taxPeriodLabel(value));
    if (['dpp', 'ppn', 'ppnbm', 'saleReplacementAdvanceTermAmount', 'discountAmount', 'receivedAdvanceAmount'].includes(field)) {
        return /^-?\d+(\.\d{1,2})?$/.test(String(value)) ? money(value) : e(value);
    }
    return e(value);
}

function historyItems(items) {
    if (!Array.isArray(items) || !items.length) return '<p class="text-sm text-muted">Tidak ada item barang atau jasa.</p>';
    return `<ul class="space-y-3">${items.map((item, index) => `<li class="rounded-md border border-border bg-white p-3"><p class="text-sm font-semibold">${e(item.itemName || `Item ${index + 1}`)}</p><p class="mt-1 text-xs text-muted">Kode ${e(item.itemCode || '—')}</p><dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3"><div><dt class="text-xs text-muted">Harga / termin</dt><dd class="mt-1 text-sm font-medium">${historyValue('saleReplacementAdvanceTermAmount', item.saleReplacementAdvanceTermAmount)}</dd></div><div><dt class="text-xs text-muted">Potongan</dt><dd class="mt-1 text-sm font-medium">${historyValue('discountAmount', item.discountAmount)}</dd></div><div><dt class="text-xs text-muted">Uang muka diterima</dt><dd class="mt-1 text-sm font-medium">${historyValue('receivedAdvanceAmount', item.receivedAdvanceAmount)}</dd></div></dl></li>`).join('')}</ul>`;
}

function historySnapshot(snapshot) {
    if (!snapshot || typeof snapshot !== 'object') return '<p class="text-sm text-muted">Belum ada nilai sebelumnya.</p>';
    const fields = Object.entries(snapshot).filter(([field]) => field !== 'items');
    return `<dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">${fields.map(([field, value]) => `<div class="min-w-0"><dt class="text-xs text-muted">${e(historyLabels[field] || field.replace(/([A-Z])/g, ' $1'))}</dt><dd class="mt-1 break-words text-sm font-medium">${historyValue(field, value)}</dd></div>`).join('')}</dl>${Array.isArray(snapshot.items) ? `<div class="${fields.length ? 'mt-5 border-t border-border pt-4' : ''}"><h4 class="mb-3 text-xs font-semibold text-muted">Item barang / jasa (${snapshot.items.length})</h4>${historyItems(snapshot.items)}</div>` : ''}`;
}

function historyChanges(before, after) {
    if (!before) return `<div class="rounded-lg border border-[#d9e3eb] bg-[#f7f9fb] p-4"><h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted">Rincian faktur</h4>${historySnapshot(after)}</div>`;
    const fields = [...new Set([...Object.keys(before || {}), ...Object.keys(after || {})])];
    if (!fields.length) return '<p class="text-sm text-muted">Detail perubahan tidak tersedia.</p>';
    return `<div class="space-y-3">${fields.map((field) => {
        const label = historyLabels[field] || field.replace(/([A-Z])/g, ' $1');
        if (field === 'items') return `<div class="rounded-lg border border-border p-4"><h4 class="mb-3 text-sm font-semibold">Perubahan item barang / jasa</h4><div class="grid gap-4 lg:grid-cols-2"><section><p class="mb-2 text-xs font-semibold text-muted">Sebelum (${Array.isArray(before.items) ? before.items.length : 0} item)</p>${historyItems(before.items)}</section><section><p class="mb-2 text-xs font-semibold text-muted">Sesudah (${Array.isArray(after.items) ? after.items.length : 0} item)</p>${historyItems(after.items)}</section></div></div>`;
        return `<div class="grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:items-center"><h4 class="text-sm font-semibold sm:col-span-3">${e(label)}</h4><div class="min-w-0"><p class="mb-1 text-xs text-muted">Sebelum</p><div class="break-words text-sm">${historyValue(field, before[field])}</div></div><span class="hidden text-muted sm:block" aria-hidden="true">→</span><div class="min-w-0"><p class="mb-1 text-xs text-muted">Sesudah</p><div class="break-words text-sm font-medium">${historyValue(field, after[field])}</div></div></div>`;
    }).join('')}</div>`;
}

function historyEntry(entry) {
    const title = historyActions[entry.action] || entry.action;
    const actionIcon = historyActionIcons[entry.action] || 'document';
    return `<li class="relative pl-8"><span class="absolute left-[-0.625rem] top-2 inline-flex h-8 w-8 items-center justify-center rounded-full border border-border bg-white text-primary shadow-sm" aria-hidden="true">${icon(actionIcon)}</span><details class="group rounded-lg border border-border bg-white"><summary class="flex min-h-16 cursor-pointer list-none flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-lg px-4 py-3 hover:bg-slate-50 focus-visible:outline-offset-[-3px]"><span class="min-w-0"><span class="block text-sm font-semibold">${e(title)}</span><span class="mt-1 block text-xs text-muted">Oleh ${e(entry.actorAkunId)}</span></span><time class="text-xs text-muted">${e(dateLabel(entry.changedAt))}</time></summary><div class="border-t border-border p-4 sm:p-5">${entry.note ? `<p class="mb-4 rounded-md border-l-2 border-primary bg-slate-50 px-3 py-2 text-sm leading-relaxed">${e(entry.note)}</p>` : ''}${historyChanges(entry.beforeSnapshot, entry.afterSnapshot)}</div></details></li>`;
}

async function detailPage() {
    let snapshot;
    const endpoint = `/tax-invoices/${encodeURIComponent(invoiceId)}`;
    async function loadDetail() {
        snapshot = await api(endpoint);
        const f = snapshot.taxInvoice;
        $('#detail-status').innerHTML = statusBadge(f.status, !!snapshot.replacedByTaxInvoiceId);
        const active = f.status !== 'CANCELLED';
        $('#detail-actions').hidden = !active;
        $('#replace-invoice').disabled = !!snapshot.replacedByTaxInvoiceId;
        $('#detail-data').innerHTML = `<div class="grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]"><section class="panel"><div class="panel-heading"><h2 class="panel-title">Identitas faktur</h2></div><div class="panel-body">${details([['Nomor faktur / NSFP', e(f.taxInvoiceNumber)], ['Tanggal e-Faktur', e(dateLabel(f.taxInvoiceDate))], ['Masa pajak', link(`/tax-recaps/${f.taxPeriod}`, taxPeriodLabel(f.taxPeriod))], ['Invoice D-2', `<span class="id-text">${e(f.invoiceId)}</span>`], ['Proyek', `<span class="id-text">${e(f.projectId) || 'Belum tersedia'}</span>`], ['Klien', `<span class="id-text">${e(f.clientId) || 'Belum tersedia'}</span>`], ['Dicatat oleh', e(f.createdByAkunId)], ['Waktu pencatatan', e(dateLabel(f.createdAt))]])}<div class="mt-5 space-y-3 border-t border-border pt-4">${snapshot.replacesTaxInvoiceId ? `<p class="text-sm">Faktur asal: ${link(detailHref(snapshot.replacesTaxInvoiceId), 'Buka faktur asal')}</p>` : ''}${snapshot.replacedByTaxInvoiceId ? `<p class="text-sm">Digantikan oleh: ${link(detailHref(snapshot.replacedByTaxInvoiceId), 'Buka faktur pengganti')}</p>` : ''}<p class="text-xs text-muted">PDF tersimpan <span class="mt-1 block break-all">${e(f.pdfUri)}</span></p><p class="field-help">Berkas tercatat; akses pembukaan PDF belum tersedia.</p></div></div></section><section class="panel"><div class="panel-heading"><h2 class="panel-title">Nilai pajak</h2></div><div class="panel-body">${details([['DPP', money(f.dpp)], ['PPN', money(f.ppn)], ['PPnBM', money(f.ppnbm)]])}</div></section></div><section class="panel mt-6"><div class="panel-heading"><h2 class="panel-title">Item barang / jasa</h2></div>${itemsTable(snapshot.items)}</section>`;
    }
    async function loadHistory() {
        try {
            const data = await api(`${endpoint}/history`);
            $('#invoice-history').innerHTML = data.history.length ? `<ol class="space-y-7 p-5 sm:p-6">${data.history.map(historyEntry).join('')}</ol>` : empty('Belum ada riwayat', 'Perubahan faktur akan tercatat di sini.');
        } catch (error) { $('#invoice-history').innerHTML = empty('Riwayat gagal dimuat', error.message); }
    }
    $('#replace-invoice').onclick = () => location.assign(`/tax-invoices/create?replacesTaxInvoiceId=${encodeURIComponent(invoiceId)}`);
    $('#correct-invoice').onclick = () => {
        const f = snapshot.taxInvoice;
        for (const name of ['taxInvoiceNumber', 'taxInvoiceDate', 'taxPeriod', 'dpp', 'ppn', 'ppnbm']) $(`[name="${name}"]`, $('#correction-form')).value = f[name] ?? '0.00';
        $('#correction-items').innerHTML = snapshot.items.map(correctionItem).join('');
        $('#note').value = ''; clearNotice('#correction-error'); openDialog('correction-dialog');
    };
    $('#add-correction-item').onclick = () => $('#correction-items').insertAdjacentHTML('beforeend', correctionItem());
    $('#correction-items').onclick = (event) => { if (event.target.closest('[data-remove-item]')) event.target.closest('[data-correction-item]').remove(); };
    $('#cancel-invoice').onclick = () => { $('#cancelNote').value = ''; clearNotice('#cancellation-error'); openDialog('cancellation-dialog'); };
    async function update(form, changes, note, dialog, errorTarget) {
        clearNotice(errorTarget);
        try {
            await busy(form, () => api(endpoint, { method: 'PATCH', body: { changes, note: note || null } }), 'Menyimpan perubahan…');
            $(`#${dialog}`).close(); notice('Perubahan faktur berhasil disimpan.', 'success');
            await Promise.all([loadDetail(), loadHistory()]);
        } catch (error) { notice(error.message, 'error', errorTarget); }
    }
    $('#correction-form').onsubmit = async (event) => {
        event.preventDefault(); const form = event.currentTarget;
        const data = Object.fromEntries(new FormData(form));
        const changes = Object.fromEntries(Object.entries(data).filter(([key]) => key !== 'note'));
        changes.items = $$('[data-correction-item]').map((row) => Object.fromEntries($$('[data-item-field]', row).map((input) => [input.dataset.itemField, input.value])));
        if (!changes.items.length) { notice('Faktur wajib memiliki minimal satu item.', 'error', '#correction-error'); return; }
        await update(form, changes, data.note, 'correction-dialog', '#correction-error');
    };
    $('#cancellation-form').onsubmit = async (event) => { event.preventDefault(); await update(event.currentTarget, { status: 'CANCELLED' }, $('#cancelNote').value, 'cancellation-dialog', '#cancellation-error'); };
    // Kontrol siap sebelum detail terlihat, meskipun riwayat masih dimuat.
    await Promise.all([loadDetail(), loadHistory()]);
}

async function allInvoices(taxPeriod) {
    const items = []; let number = 1, total;
    do {
        const data = await api(`/tax-invoices?${new URLSearchParams({ taxPeriod, page: String(number), pageSize: '100' })}`);
        items.push(...data.items); total = data.pagination.totalItems; number++;
        if (!data.items.length) break;
    } while (items.length < total);
    return new Map(items.map((item) => [item.taxInvoiceId, item]));
}

function recapSummary(recap) {
    return `<dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><div class="metric"><dt>Faktur dihitung</dt><dd>${recap.invoiceCount}</dd></div><div class="metric"><dt>Total DPP</dt><dd>${money(recap.totalDpp)}</dd></div><div class="metric"><dt>Total PPN</dt><dd>${money(recap.totalPpn)}</dd></div><div class="metric"><dt>Total PPnBM</dt><dd>${money(recap.totalPpnbm)}</dd></div></dl><div class="panel mt-3 flex flex-wrap items-center justify-between gap-5 p-5"><div><p class="mb-2 text-xs text-muted">Status pembayaran</p>${statusBadge(recap.paymentStatus)}</div><div><p class="text-xs text-muted">Jumlah setor</p><p class="mt-2 font-semibold tabular-nums">${money(recap.amountPaid)}</p></div><div><p class="text-xs text-muted">Selisih (setor − PPN)</p><p class="mt-2 font-semibold tabular-nums">${money(recap.paymentDifference)}</p></div></div>`;
}

function recapRows(invoices, references, interactive = true) {
    return invoices.map((item) => {
        const reference = references.get(item.taxInvoiceId);
        return `<tr>${td(interactive ? link(detailHref(item.taxInvoiceId), item.taxInvoiceNumber, 'whitespace-nowrap') : e(item.taxInvoiceNumber), 'whitespace-nowrap')}${td(e(reference ? dateLabel(reference.taxInvoiceDate) : 'Belum tersedia'), 'whitespace-nowrap')}${td(e(item.invoiceId), 'id-text')}${td(e(item.projectId) || 'Belum tersedia', 'id-text')}${td(e(item.clientId) || 'Belum tersedia', 'id-text')}${td(statusBadge(item.status, !!reference?.replacedByTaxInvoiceId))}${td(money(item.dpp), 'money')}${td(money(item.ppn), 'money')}${td(money(item.ppnbm), 'money')}${td(item.isCounted ? 'Ya' : 'Tidak')}${interactive ? td(link(detailHref(item.taxInvoiceId), 'Detail')) : ''}</tr>`;
    });
}

function paymentRows(payments, taxPeriod, interactive = true) {
    return payments.map((item) => `<tr>${td(e(item.billingId))}${td(e(item.ntpn) || 'Belum diisi')}${td(money(item.amountPaid), 'money')}${td(statusBadge(item.status))}${td(e(dateLabel(item.createdAt)), 'whitespace-nowrap')}${interactive ? td(item.status === 'INCOMPLETE' ? link(`/tax-recaps/${taxPeriod}/payments/create?paymentId=${encodeURIComponent(item.taxPaymentId)}`, 'Lengkapi') : 'Lengkap') : ''}</tr>`);
}

async function recapPage() {
    $('#period-form').onsubmit = (event) => { event.preventDefault(); location.assign(`/tax-recaps/${$('#selectedPeriod').value}`); };
    const { periods } = await api('/api/ui/tax-recap-periods');
    const select = $('#selectedPeriod');
    select.replaceChildren(...periods.map((value) => new Option(taxPeriodLabel(value), value)));
    if (!periods.length) {
        select.add(new Option('Belum ada masa pajak', ''));
    } else {
        select.disabled = false;
        $('#period-form button[type="submit"]').disabled = false;
        if (periods.includes(period)) {
            select.value = period;
        } else {
            const placeholder = new Option('Pilih masa pajak tersedia', '', true, true);
            placeholder.disabled = true;
            select.add(placeholder, 0);
        }
    }
    const [data, references] = await Promise.all([api(`/tax-recaps/${period}`), allInvoices(period)]);
    $('#recap-summary').innerHTML = recapSummary(data.recap);
    $('#recap-count').textContent = `${data.invoices.length} tercatat · ${data.recap.invoiceCount} dihitung`;
    const headers = ['NSFP', 'Tanggal', 'Invoice', 'Proyek', 'Klien', 'Status', 'DPP', 'PPN', 'PPnBM', 'Dihitung'];
    $('#recap-invoices').innerHTML = data.invoices.length ? table([...headers, 'Detail'], recapRows(data.invoices, references), 'Faktur masa pajak') : empty('Rekap masa pajak kosong', 'Belum ada faktur pada periode ini. Pilih masa pajak lain atau catat faktur keluaran.');
    $('#recap-payments').innerHTML = data.payments.length ? table(['ID Billing', 'NTPN', 'Jumlah setor', 'Status', 'Dicatat', 'Tindakan'], paymentRows(data.payments, period), 'Pembayaran masa pajak') : empty('Pembayaran belum dicatat', 'Catat pembayaran setelah faktur periode ini tersedia. NTPN dan bukti pembayaran dapat dilengkapi kemudian.');
    const paymentLink = $(`a[href="/tax-recaps/${period}/payments/create"]`, $('#page-content'));
    if (!data.invoices.length) { paymentLink.setAttribute('aria-disabled', 'true'); paymentLink.classList.add('opacity-60'); paymentLink.onclick = (event) => { event.preventDefault(); notice('Catat faktur untuk periode ini sebelum mencatat pembayaran.', 'info'); }; }
    $('#open-export').disabled = false;
    $('#open-export').onclick = () => {
        $('#export-preview').innerHTML = `${recapSummary(data.recap)}<h3 class="mb-3 mt-5 text-sm font-semibold">Faktur</h3>${data.invoices.length ? table(headers, recapRows(data.invoices, references, false), 'Pratinjau faktur ekspor') : '<p class="text-sm text-muted">Tidak ada faktur pada periode ini.</p>'}<h3 class="mb-3 mt-5 text-sm font-semibold">Pembayaran</h3>${data.payments.length ? table(['ID Billing', 'NTPN', 'Jumlah setor', 'Status', 'Dicatat'], paymentRows(data.payments, period, false), 'Pratinjau pembayaran ekspor') : '<p class="text-sm text-muted">Belum ada pembayaran.</p>'}`;
        clearNotice('#export-error'); resetPreview(); openDialog('export-dialog');
    };
    let pdfUrl;
    let previewGeneration = 0;
    const previewButton = $('#preview-pdf');
    const previewFormat = $('#export-format');
    function updatePreviewLabel() {
        previewButton.innerHTML = `${icon('document')}Pratinjau ${previewFormat.value === 'xlsx' ? 'Excel' : 'PDF'}`;
    }
    function resetPreview() {
        previewGeneration++;
        $('#export-preview').hidden = true;
        $('#pdf-preview').hidden = true;
        $('#pdf-preview iframe').removeAttribute('src');
        if (pdfUrl) URL.revokeObjectURL(pdfUrl);
        pdfUrl = null;
        updatePreviewLabel();
    }
    updatePreviewLabel();
    previewFormat.onchange = () => { resetPreview(); clearNotice('#export-error'); };
    $('#preview-pdf').onclick = async () => {
        clearNotice('#export-error');
        if (previewFormat.value === 'xlsx') {
            $('#export-preview').hidden = false;
            $('#export-preview').scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            return;
        }
        const generation = ++previewGeneration;
        try { await busy($('#preview-pdf'), async () => {
            const response = await api(`/tax-recaps/${period}/export?format=pdf`, { binary: true });
            const blob = await response.blob();
            if (generation !== previewGeneration) return;
            if (pdfUrl) URL.revokeObjectURL(pdfUrl);
            pdfUrl = URL.createObjectURL(blob);
            $('#pdf-preview iframe').src = pdfUrl; $('#pdf-preview').hidden = false;
        }, 'Menyiapkan PDF…'); } catch (error) { if (generation === previewGeneration) notice(error.message, 'error', '#export-error'); }
        finally { updatePreviewLabel(); }
    };
    $('#export-dialog').addEventListener('close', resetPreview);
    $('#download-export').onclick = async () => {
        clearNotice('#export-error');
        try { await busy($('#download-export'), async () => {
            const format = $('#export-format').value;
            const response = await api(`/tax-recaps/${period}/export?format=${format}`, { binary: true });
            const url = URL.createObjectURL(await response.blob());
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = /filename="?([^";]+)"?/.exec(response.headers.get('Content-Disposition') || '')?.[1] || `rekap-pajak-${period}.${format}`;
            anchor.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
        }, 'Menyiapkan unduhan…'); } catch (error) { notice(error.message, 'error', '#export-error'); }
    };
}

async function paymentPage() {
    const paymentId = new URLSearchParams(location.search).get('paymentId');
    const data = await api(`/tax-recaps/${period}`);
    const existing = paymentId ? data.payments.find((payment) => payment.taxPaymentId === paymentId) : null;
    if (paymentId && (!existing || existing.status !== 'INCOMPLETE')) throw new Error('Pembayaran Draft tidak ditemukan pada periode ini. Kembali ke rekap untuk memeriksa status terbaru.');
    $('#payment-summary').innerHTML = details([['Total PPN', money(data.recap.totalPpn)], ['Jumlah setor tercatat', money(data.recap.amountPaid)], ['Status pembayaran', statusBadge(data.recap.paymentStatus)]]);
    const projects = [...new Set(data.invoices.map((item) => item.projectId).filter(Boolean))];
    $('#payment-projects').innerHTML = projects.length ? `<ul class="space-y-3">${projects.map((project) => `<li class="break-all">${e(project)}</li>`).join('')}</ul>` : '<p>Belum ada proyek pada periode ini.</p>';
    $('#payment-upload-limit').textContent = `Berkas PDF, maksimal ${config.uploadMaxKb / 1024} MB per berkas.`;
    const form = $('#payment-form');
    function newDifference() {
        const amount = existing ? 0n : cents($('#amountPaid').value || '0');
        return cents(data.recap.amountPaid) + amount - cents(data.recap.totalPpn);
    }
    function updateDifference() {
        const output = $('#payment-difference');
        const warning = $('#payment-overpaid');
        try {
            const difference = newDifference();
            const overpaid = !existing && difference > 0n;
            output.textContent = money(decimal(difference));
            output.classList.toggle('text-[#942722]', overpaid);
            warning.hidden = !overpaid;
            if (overpaid) {
                const remaining = cents(data.recap.totalPpn) - cents(data.recap.amountPaid);
                warning.textContent = `Jumlah setor melebihi total PPN. Maksimal yang boleh disetor: ${money(decimal(remaining > 0n ? remaining : 0n))}.`;
            }
            $('#save-payment').disabled = overpaid;
        } catch {
            output.textContent = 'Periksa format jumlah setor';
            output.classList.remove('text-[#942722]');
            warning.hidden = true;
            $('#save-payment').disabled = false;
        }
    }
    $('#amountPaid').oninput = updateDifference;
    if (existing) {
        $('#payment-title').textContent = 'Lengkapi Pembayaran Pajak';
        $('#payment-form-title').textContent = 'Lengkapi NTPN dan bukti pembayaran';
        $('#amountPaid').value = existing.amountPaid; $('#amountPaid').readOnly = true;
        $('#billingId').value = existing.billingId; $('#billingId').readOnly = true;
        $('#ntpn').value = existing.ntpn || ''; $('#ntpn').required = true;
        $('#paymentProofFile').required = true; $('#eBillingFile').required = false;
        $('#ebilling-field').hidden = true;
        $('#save-payment').innerHTML = `${icon('check')}Konfirmasi Pembayaran`;
    }
    $('#payment-fields').disabled = !data.invoices.length;
    if (!data.invoices.length) notice('Belum ada faktur pada masa pajak ini. Catat faktur atau pilih periode lain melalui rekap.', 'info');
    updateDifference();
    form.onsubmit = async (event) => {
        event.preventDefault(); clearNotice();
        try {
            if (!existing && newDifference() > 0n) throw new Error('Pembayaran tidak dapat disimpan karena jumlah setor melebihi total PPN (selisih plus). Sesuaikan jumlah setor agar selisih menjadi 0 atau minus.');
            if (!existing) validateFile($('#eBillingFile'));
            validateFile($('#paymentProofFile'), !!existing);
            const body = existing ? new FormData() : new FormData(form);
            if (!existing) body.set('taxPeriod', period);
            if (existing) { body.set('ntpn', $('#ntpn').value); body.set('paymentProofFile', $('#paymentProofFile').files[0]); }
            else if (!$('#paymentProofFile').files.length) body.delete('paymentProofFile');
            await busy(form, () => api(existing ? `/tax-payments/${encodeURIComponent(paymentId)}` : '/tax-payments', { method: existing ? 'PATCH' : 'POST', body }), 'Menyimpan pembayaran…');
            flash(existing ? 'Pembayaran berhasil dikonfirmasi.' : 'Pembayaran berhasil disimpan.');
            location.assign(`/tax-recaps/${period}`);
        } catch (error) { notice(error.message); }
    };
}

async function start() {
    if (page === 'login') { await loginPage(); return; }
    configureMenu();
    const token = sessionStorage.getItem(tokenKey);
    if (!token) { location.replace(`/login?next=${encodeURIComponent(location.pathname + location.search)}`); return; }
    try {
        await api('/tax-invoices?pageSize=1');
        const identity = tokenIdentity(token);
        $('[data-account]').textContent = identity.account; $('[data-role]').textContent = identity.role;
        $('#page-content').hidden = false;
        const message = sessionStorage.getItem('e1.flash');
        if (message) { sessionStorage.removeItem('e1.flash'); notice(message, 'success'); }
        await ({ invoices: invoicePage, create: createPage, detail: detailPage, recap: recapPage, payment: paymentPage }[page])();
    } catch (error) {
        notice(error.message);
        if (![401, 403].includes(error.status)) {
            $$('#page-content [role="status"]').forEach((element) => { element.innerHTML = empty('Data gagal dimuat', 'Muat ulang halaman untuk mencoba kembali.'); });
            const retry = document.createElement('button');
            retry.className = 'btn btn-secondary mt-4'; retry.textContent = 'Coba Lagi';
            retry.onclick = () => location.reload();
            $('#app-alert').append(document.createElement('br'), retry);
        }
    }
    finally { $('#auth-pending').hidden = true; }
}

start().catch((error) => notice(error.message));
