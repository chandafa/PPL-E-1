import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { chooseInvoice } from './helpers.mjs';

test('PDF hasil generator diperiksa server nyata dan tanggal sesuai invoice di desktop serta mobile', async ({ page, request }) => {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const login = await request.post('/api/dev/f1/login', { data: { akunId: 'AKN-0002', kodePeran: 'Admin' } });
    expect(login.ok()).toBe(true);
    const { data } = await login.json();
    await page.addInitScript((token) => sessionStorage.setItem('e1.accessToken', token), data.accessToken);
    const folder = resolve('storage/framework/testing/e1-browser-samples');
    const manifest = JSON.parse(readFileSync(resolve(folder, 'manifest.json'), 'utf8'));
    const selected = ['inv-demo-007', 'inv-manual-001', 'inv-manual-ppnbm-001', 'inv-manual-jasa-001'];
    const historicalManual = manifest.find((sample) => sample.invoiceId.startsWith('inv-flow-') && sample.flow === 'create');
    if (historicalManual) selected.push(historicalManual.invoiceId);
    for (const id of selected) {
        const sample = manifest.find((row) => row.invoiceId === id && row.flow === 'create');
        expect(sample, 'PDF untuk ' + id).toBeTruthy();
        const date = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' })
            .format(new Date(sample.invoiceDate + 'T12:00:00Z'));
        await page.goto('/tax-invoices/create');
        await chooseInvoice(page, id);
        await expect(page.locator('#invoice-context')).toContainText(date);
        await page.locator('#eFakturFile').setInputFiles(resolve(folder, sample.file));
        const response = page.waitForResponse((result) => result.url().endsWith('/api/v1/tax-invoices/extractions') && result.request().method() === 'POST');
        await page.getByRole('button', { name: 'Periksa PDF', exact: true }).click();
        const extracted = await response;
        expect(extracted.status()).toBe(200);
        const { data: parsed } = await extracted.json();
        expect(parsed.taxInvoiceDate).toBe(sample.invoiceDate);
        expect(parsed.taxPeriod).toBe(sample.taxPeriod);
        expect(parsed.taxInvoiceNumber).toBe(sample.nsfp);
        expect(parsed.ppn).toBe(sample.ppn);
        expect(parsed.ppnbm).toBe(sample.ppnbm);
        if (id === 'inv-manual-ppnbm-001') {
            expect(parsed.ppnbm).toBe('750000000.00');
            await expect(page.locator('#invoice-context')).toContainText('Simulasi Produksi Yacht Privat');
            await expect(page.locator('#extraction-preview')).toContainText('Rp 750.000.000,00');
        }
        if (id === 'inv-manual-jasa-001') expect(parsed.ppnbm).toBe('0.00');
        await expect(page.locator('#extraction-preview')).toContainText(date);
        await expect(page.locator('#save-invoice')).toBeEnabled();
    }
    await page.screenshot({ path: test.info().outputPath('samples-desktop.png'), fullPage: true, animations: 'disabled' });
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator('#extraction-preview')).toBeVisible();
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await expect.poll(() => page.locator('#sidebar').evaluate((menu) => menu.getBoundingClientRect().right)).toBeLessThanOrEqual(0);
    await page.screenshot({ path: test.info().outputPath('samples-mobile.png'), fullPage: true, animations: 'disabled' });
    expect(errors).toEqual([]);
});

test('kontrol koreksi siap ketika detail tampil walaupun riwayat belum menjawab', async ({ page, request }) => {
    const login = await request.post('/api/dev/f1/login', { data: { akunId: 'AKN-0002', kodePeran: 'Admin' } });
    const { data } = await login.json();
    await page.addInitScript((token) => sessionStorage.setItem('e1.accessToken', token), data.accessToken);
    const response = await request.get('/api/v1/tax-invoices?taxInvoiceNumber=010.000-26.12345678', {
        headers: { Authorization: 'Bearer ' + data.accessToken },
    });
    const id = (await response.json()).data.items[0].taxInvoiceId;
    let release;
    const gate = new Promise((resolve) => { release = resolve; });
    await page.route('**/api/v1/tax-invoices/' + id + '/history', async (route) => {
        await gate;
        await route.continue();
    });
    try {
        await page.goto('/tax-invoices/' + id);
        await expect(page.locator('#detail-actions')).toBeVisible();
        await page.getByRole('button', { name: 'Koreksi Faktur', exact: true }).click();
        await expect(page.locator('#correction-dialog')).toBeVisible();
        await page.keyboard.press('Escape');
    } finally {
        release();
    }
});
