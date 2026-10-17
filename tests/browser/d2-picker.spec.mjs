import { test, expect } from '@playwright/test';
import { resolve } from 'node:path';
import { readFileSync } from 'node:fs';
import { chooseInvoice } from './helpers.mjs';
import AxeBuilder from '@axe-core/playwright';

test.beforeEach(async ({ page, request }) => {
    page.on('pageerror', (error) => { throw error; });
    const response = await request.post('/api/dev/f1/login', { data: { akunId: 'AKN-0002', kodePeran: 'Admin' } });
    expect(response.ok()).toBe(true);
    const { data } = await response.json();
    await page.addInitScript((token) => sessionStorage.setItem('e1.accessToken', token), data.accessToken);
    const invoices = JSON.parse(readFileSync(resolve('database/data/d2-invoices.json'), 'utf8'))
        .filter((invoice) => ['inv-demo-001', 'inv-demo-002', 'inv-demo-006'].includes(invoice.invoiceId));
    await page.route('**/api/ui/d2/invoices', (route) => route.fulfill({ json: { data: { invoices } } }));
});

test('katalog langsung tidak menawarkan invoice yang sudah memiliki faktur aktif', async ({ page }) => {
    await page.unroute('**/api/ui/d2/invoices');
    await page.goto('/tax-invoices/create');
    const search = page.getByRole('combobox', { name: 'Invoice D-2', exact: true });
    await expect(search).toBeEnabled();
    await search.fill('inv-demo-001');
    await expect(page.getByRole('option')).toHaveCount(0);
    await expect(page.locator('#extract')).toBeDisabled();
});

test('pencarian menyatu dengan dropdown dan mendukung keyboard', async ({ page }) => {
    await page.goto('/tax-invoices/create');
    const search = page.getByRole('combobox', { name: 'Invoice D-2', exact: true });
    await expect(search).toBeEnabled();
    await search.click();
    await expect(page.getByRole('option').first()).toBeVisible();
    await search.fill('PT LINTAS REL');
    await expect(page.getByRole('option').filter({ hasText: 'inv-demo-002' })).toBeVisible();
    await expect(page.getByRole('option').filter({ hasText: 'inv-demo-001' })).toHaveCount(0);
    await search.fill('ERP 0001');
    await expect(page.getByRole('option')).toHaveCount(1);
    await search.press('ArrowDown');
    await expect(search).toHaveAttribute('aria-activedescendant', 'invoice-option-0');
    await search.press('Enter');
    await expect(page.locator('#invoiceId')).toHaveValue('inv-demo-001');
    await expect(search).toHaveAttribute('aria-expanded', 'false');
    await search.fill('invoice-tidak-ada');
    await expect(page.getByText('Invoice tidak ditemukan.', { exact: false })).toBeVisible();
    await expect(page.locator('#invoiceId')).toHaveValue('');
    await expect(page.locator('#extract')).toBeDisabled();
    expect(await search.evaluate((input) => input.checkValidity())).toBe(false);
    await search.press('Escape');
    await expect(search).toHaveAttribute('aria-expanded', 'false');
    await page.getByRole('button', { name: 'Tampilkan pilihan invoice' }).click();
    await expect(page.getByRole('option').first()).toBeVisible();
    await search.press('ArrowUp');
    await search.press('Enter');
    await expect(page.locator('#invoiceId')).not.toHaveValue('');
});

test('dropdown pencarian dapat dibaca pada layar kecil dan memiliki label aksesibel', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/tax-invoices/create');
    const search = page.getByRole('combobox', { name: 'Invoice D-2', exact: true });
    await expect(search).toBeEnabled();
    await search.fill('inv-demo-002');
    await expect(page.getByRole('option')).toHaveCount(1);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    const results = await new AxeBuilder({ page }).include('#invoice-form').analyze();
    expect(results.violations).toEqual([]);
    await page.screenshot({ path: '/tmp/ppl-e1-invoice-search-mobile.png', fullPage: true });
    await page.getByRole('option').click();
    await expect(page.locator('#invoice-context')).toContainText('INV-2026-0002');
});

test('pilihan D-2 mengisi ringkasan dan pergantian invoice membatalkan pratinjau', async ({ page }) => {
    await page.goto('/tax-invoices/create');
    const picker = page.locator('#invoiceId');
    await expect(picker).toBeEnabled();
    await expect(page.locator('#save-invoice')).toBeDisabled();
    await chooseInvoice(page, 'inv-demo-001');
    await expect(page.locator('#invoice-context')).toContainText('INV-2026-0001');
    await expect(page.locator('#invoice-context')).toContainText('Pengembangan Portal ERP Tahap 1');
    await expect(page.locator('#invoice-context')).toContainText('Belum tersedia');
    await expect(page.locator('#invoice-context')).toContainText('Rp 100.000.000,00');
    await chooseInvoice(page, 'inv-demo-002');
    await expect(page.locator('#invoice-context')).toContainText('PT Lintas Rel Nusantara');
    await expect(page.locator('#invoice-context')).toContainText('Rp 250.000.000,00');
    await expect(page.locator('#invoice-context')).not.toContainText('INV-2026-0001');
    await page.route('**/api/v1/tax-invoices/extractions', (route) => route.fulfill({ json: { data: {
        taxInvoiceNumber: '010.000-26.99999999', taxInvoiceDate: '2026-11-01', taxPeriod: '2026-11',
        dpp: '250000000.00', ppn: '27500000.00', ppnbm: '0.00', items: [],
    } } }));
    await page.locator('#eFakturFile').setInputFiles(resolve('storage/framework/testing/e1-browser-samples/efaktur-inv-demo-002.pdf'));
    await page.locator('#extract').click();
    await expect(page.locator('#save-invoice')).toBeEnabled();
    await chooseInvoice(page, 'inv-demo-006');
    await expect(page.locator('#save-invoice')).toBeDisabled();
    await expect(page.locator('#extraction-preview')).toContainText('PDF belum diperiksa');
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});

test('daftar yang gagal dapat dimuat ulang dan daftar kosong tidak dapat disimpan', async ({ page }) => {
    let first = true;
    await page.route('**/api/ui/d2/invoices', (route) => {
        if (first) { first = false; return route.fulfill({ status: 503, json: { message: 'Layanan sementara gagal' } }); }
        return route.continue();
    });
    await page.goto('/tax-invoices/create');
    await expect(page.locator('#invoice-context')).toContainText('Daftar invoice gagal dimuat');
    await expect(page.locator('#extract')).toBeDisabled();
    await page.locator('#retry-invoices').click();
    await expect(page.locator('#invoiceId')).toBeEnabled();
    await page.unroute('**/api/ui/d2/invoices');
    await page.route('**/api/ui/d2/invoices', (route) => route.fulfill({ json: { data: { invoices: [] } } }));
    await page.reload();
    await expect(page.locator('#invoice-context')).toContainText('Tidak ada invoice tanpa faktur aktif');
    await expect(page.locator('#invoiceId')).toBeDisabled();
    await expect(page.getByRole('combobox', { name: 'Invoice D-2', exact: true })).toBeDisabled();
    await expect(page.locator('#extract')).toBeDisabled();
    await expect(page.locator('#save-invoice')).toBeDisabled();
});

test('invoice pengganti terkunci dan ID tetap terkirim saat penyimpanan gagal', async ({ page }) => {
    await page.route('**/api/v1/tax-invoices/original-picker-test', (route) => route.fulfill({ json: { data: {
        taxInvoice: { invoiceId: 'inv-demo-006', status: 'NORMAL', taxInvoiceNumber: '010.000-26.99999998' },
        replacedByTaxInvoiceId: null,
    } } }));
    let submitted;
    await page.route('**/api/v1/tax-invoices/outgoing', (route) => {
        submitted = route.request().postDataBuffer().toString();
        return route.fulfill({ status: 400, json: { message: 'Simulasi validasi penyimpanan' } });
    });
    await page.goto('/tax-invoices/create?replacesTaxInvoiceId=original-picker-test');
    await expect(page.locator('#invoiceId')).toHaveValue('inv-demo-006');
    await expect(page.locator('#invoiceId')).toBeDisabled();
    await expect(page.getByRole('combobox', { name: 'Invoice D-2', exact: true })).toBeDisabled();
    await page.locator('#eFakturFile').setInputFiles(resolve('storage/framework/testing/e1-browser-samples/efaktur-inv-demo-006-pengganti.pdf'));
    await page.locator('#save-invoice').click();
    await expect(page.locator('#app-alert')).toContainText('Simulasi validasi penyimpanan');
    expect(submitted).toMatch(/name="invoiceId"\r\n\r\ninv-demo-006\r\n/);
    expect(submitted).toContain('original-picker-test');
    await expect(page.locator('#invoiceId')).toBeDisabled();
});
