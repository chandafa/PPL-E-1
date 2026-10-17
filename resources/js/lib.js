export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
}

export function cents(value) {
    if (!/^-?\d+(\.\d{1,2})?$/.test(String(value))) throw new Error('Masukkan nominal tanpa pemisah ribuan, maksimal dua angka desimal.');
    const text = String(value);
    const negative = text.startsWith('-');
    const [whole, fraction = ''] = text.replace('-', '').split('.');
    return (BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'))) * (negative ? -1n : 1n);
}

export function money(value = '0.00') {
    const amount = cents(value ?? '0.00');
    const absolute = amount < 0n ? -amount : amount;
    const integer = (absolute / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return `${amount < 0n ? '−' : ''}Rp ${integer},${(absolute % 100n).toString().padStart(2, '0')}`;
}

export function decimal(value) {
    const amount = typeof value === 'bigint' ? value : cents(value);
    const absolute = amount < 0n ? -amount : amount;
    return `${amount < 0n ? '-' : ''}${absolute / 100n}.${String(absolute % 100n).padStart(2, '0')}`;
}

export function dateLabel(value) {
    if (!value) return 'Belum tersedia';
    const parsed = new Date(value.length === 10 ? `${value}T00:00:00+07:00` : value);
    if (Number.isNaN(parsed.getTime())) return value;
    const date = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Asia/Jakarta' }).format(parsed);
    if (value.length === 10) return date;
    const time = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: 'Asia/Jakarta' }).format(parsed);
    return `${date}, ${time} WIB`;
}

export function taxPeriodLabel(value) {
    if (!value) return 'Belum tersedia';
    if (!/^[0-9]{4}-(0[1-9]|1[0-2])$/.test(value)) return value;
    return new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${value}-01T00:00:00Z`));
}

export function statusBadge(status, replaced = false) {
    const statuses = { NORMAL: ['Normal', 'normal'], REPLACEMENT: ['Pengganti', 'replacement'], CANCELLED: ['Dibatalkan', 'cancelled'], INCOMPLETE: ['Draft / Belum Konfirmasi', 'incomplete'], CONFIRMED: ['Terkonfirmasi', 'confirmed'], NOT_RECORDED: ['Belum dicatat', 'normal'] };
    const [label, style] = statuses[status] || [String(status), 'normal'];
    return `<span class="status status-${style}">${escapeHtml(label)}</span>${replaced ? ' <span class="status status-replaced">Terganti</span>' : ''}`;
}

export function safeNext(value) {
    return typeof value === 'string' && /^\/(tax-invoices|tax-recaps)(\/|\?|$)/.test(value) && !/[\\\r\n]/.test(value) ? value : '/tax-invoices';
}
