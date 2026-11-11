import { test, expect } from '@playwright/test';
import { resolve } from 'node:path';
import { readFileSync } from 'node:fs';
import { chooseInvoice } from '../browser/helpers.mjs';

test('dua invoice snapshot SQLite diproses sampai pembayaran Oktober dan November', async ({ page }) => {
    page.on('pageerror', error => { throw error; });
    await page.goto('/login');
    await page.locator('#kodePeran').selectOption('Admin');
    await page.getByRole('button', { name: 'Masuk Akun Demo' }).click();
    await expect(page).toHaveURL(/\/tax-invoices/);
    const folder = resolve('storage/framework/testing/d2-source-browser');
    const manifest = JSON.parse(readFileSync(resolve(folder, 'manifest.json'), 'utf8'));
    await page.goto('/d2-invoices');
    await expect(page.locator('#d2-invoice-list')).toContainText('INV-2026-0002');
    await expect(page.locator('#d2-invoice-list tbody tr')).toHaveCount(2);
    await expect(page.locator('#d2-invoice-list')).not.toContainText('PT Contoh');
    let firstId;
    for (const sample of manifest.filter(row => row.flow === 'create')) {
        await page.goto('/tax-invoices/create');
        await chooseInvoice(page, sample.invoiceId);
        await expect(page.locator('#invoice-context')).toContainText('prj-demo-001');
        await page.locator('#eFakturFile').setInputFiles(resolve(folder, sample.file));
        await page.getByRole('button', { name: 'Periksa PDF', exact: true }).click();
        await expect(page.locator('#save-invoice')).toBeEnabled();
        const saved = page.waitForResponse(response => response.url().endsWith('/api/v1/tax-invoices/outgoing') && response.request().method() === 'POST');
        await page.getByRole('button', { name: 'Simpan Faktur', exact: true }).click();
        const response = await saved;
        expect(response.status()).toBe(200);
        await expect(page).toHaveURL(/\/tax-invoices\/[0-9a-f-]{36}$/);
        firstId ||= new URL(page.url()).pathname.split('/').pop();
    }
    await page.goto('/tax-invoices/' + firstId);
    await page.getByRole('button', { name: 'Catat Pengganti', exact: true }).click();
    await page.locator('#eFakturFile').setInputFiles(resolve(folder, 'efaktur-inv-demo-001-pengganti.pdf'));
    await expect(page.locator('#save-invoice')).toBeEnabled();
    await page.getByRole('button', { name: 'Simpan Faktur', exact: true }).click();
    await expect(page).toHaveURL(/\/tax-invoices\/[0-9a-f-]{36}$/);

    await page.goto('/tax-invoices');
    await page.locator('#project').fill('prj-demo-001');
    await page.locator('#taxPeriod').selectOption('2026-10');
    await page.getByRole('button', { name: 'Cari', exact: true }).click();
    await expect(page.locator('#invoice-list tbody tr')).toHaveCount(2);

    for (const [period, amount, sequence] of [['2026-10', '16500000.00', '001'], ['2026-11', '11000000.00', '002']]) {
        await page.goto('/tax-recaps/' + period + '/payments/create');
        await page.locator('#amountPaid').fill(amount);
        await page.locator('#billingId').fill('BILL-DEMO-' + period.replace('-', '') + '-' + sequence);
        await page.locator('#eBillingFile').setInputFiles(resolve(folder, `e-billing-${period}-${sequence}.pdf`));
        await page.locator('#ntpn').fill('NTPN-DEMO-' + period.replace('-', '') + '-' + sequence);
        await page.locator('#paymentProofFile').setInputFiles(resolve(folder, `bukti-pembayaran-${period}-${sequence}.pdf`));
        await page.getByRole('button', { name: /Simpan Pembayaran/ }).click();
        await expect(page).toHaveURL(new RegExp('/tax-recaps/' + period + '$'));
        await expect(page.locator('#recap-payments')).toContainText('Confirmed');
        const response = await page.request.get('/api/v1/tax-recaps/' + period, {
            headers: { Authorization: 'Bearer ' + await page.evaluate(() => sessionStorage.getItem('e1.accessToken')) },
        });
        const recap = (await response.json()).data.recap;
        expect(recap.totalPpn).toBe(amount);
        expect(recap.paymentDifference).toBe('0.00');
    }
});
