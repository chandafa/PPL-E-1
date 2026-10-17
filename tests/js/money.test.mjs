import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../../resources/js/lib.js', import.meta.url), 'utf8');
const { cents, money, decimal, escapeHtml, safeNext, dateLabel, taxPeriodLabel } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

test('nominal maksimum dan selisih tetap presisi tanpa floating point', () => {
    assert.equal(money('9999999999999999.99'), 'Rp 9.999.999.999.999.999,99');
    assert.equal(decimal(cents('9999999999999999.99') - cents('9999999999999999.98')), '0.01');
    assert.equal(money('-600000.00'), '−Rp 600.000,00');
    assert.equal(money('0'), 'Rp 0,00');
    for (const invalid of ['1.000,00', 'NaN', '1.001', '1e10', '']) assert.throws(() => cents(invalid));
});

test('data API di-escape dan redirect hanya menuju route UI lokal', () => {
    assert.equal(escapeHtml('<img onerror="alert(1)">'), '&lt;img onerror=&quot;alert(1)&quot;&gt;');
    assert.equal(safeNext('/tax-recaps/2026-11'), '/tax-recaps/2026-11');
    for (const invalid of ['//evil.test', 'https://evil.test', '/tax-invoices\\evil.test', '/docs', null]) assert.equal(safeNext(invalid), '/tax-invoices');
});

test('tanggal Indonesia memakai bulan lengkap dan konversi WIB melewati pergantian tahun', () => {
    assert.equal(taxPeriodLabel('2026-01'), 'Januari 2026');
    assert.equal(taxPeriodLabel('2026-10'), 'Oktober 2026');
    assert.equal(dateLabel('2026-01-10'), '10 Januari 2026');
    assert.equal(dateLabel('2025-12-31T17:30:00Z'), '1 Januari 2026, 00.30 WIB');
    assert.equal(dateLabel('2026-01-10T03:15:00Z'), '10 Januari 2026, 10.15 WIB');
    assert.equal(dateLabel(null), 'Belum tersedia');
    assert.equal(dateLabel('bukan-tanggal'), 'bukan-tanggal');
});
