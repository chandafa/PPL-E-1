import { test, expect } from '@playwright/test';
import { resolve } from 'node:path';
import { writeFileSync } from 'node:fs';
import AxeBuilder from '@axe-core/playwright';
import { chooseInvoice } from './helpers.mjs';

async function login(page, role = 'Admin') {
    await page.goto('/login');
    await page.locator('#kodePeran').selectOption(role);
    await page.getByRole('button', { name: 'Masuk Akun Demo' }).click();
    await expect(page).toHaveURL(/\/tax-invoices/);
    await expect(page.locator('#invoice-list tbody tr').first()).toBeVisible();
}

test.beforeEach(async ({ page }) => {
    page.on('pageerror', (error) => { throw error; });
});

test('akses, filter, pagination, empty/error, dan login demo', async ({ page }) => {
    await page.goto('/tax-invoices');
    await expect(page).toHaveURL(/\/login\?next=/);
    await expect(page.locator('#demo-login')).toBeVisible();
    await expect(page.locator('#token-login')).toHaveCount(0);
    await expect(page.locator('#accessToken')).toHaveCount(0);
    await login(page);
    await expect(page.locator('#invoice-list tbody tr').first().locator('td').nth(5)).toHaveText(/^(Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember) [0-9]{4}$/);
    await page.locator('#taxInvoiceNumber').fill('tidak-ada');
    await page.getByRole('button', { name: 'Cari', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Data tidak ditemukan' })).toBeVisible();
    await page.reload();
    await expect(page.locator('#taxInvoiceNumber')).toHaveValue('tidak-ada');
    await page.getByRole('button', { name: 'Reset', exact: true }).click();
    await expect(page.locator('#invoice-list tbody tr')).toHaveCount(20);
    await page.locator('#project').fill('prj-demo-002');
    await expect(page.locator('#taxPeriod option[value="2026-11"]')).toHaveText('November 2026');
    await page.locator('#taxPeriod').selectOption('2026-11');
    await page.locator('#status').selectOption('REPLACEMENT');
    await page.getByRole('button', { name: 'Cari', exact: true }).click();
    await expect(page.locator('#invoice-list tbody tr')).toHaveCount(1);
    await page.reload();
    await expect(page.locator('#taxPeriod')).toHaveValue('2026-11');
    await expect(page.locator('#invoice-list tbody tr')).toHaveCount(1);
    await page.locator('#client').fill('klien-tidak-ada');
    await page.getByRole('button', { name: 'Cari', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Data tidak ditemukan' })).toBeVisible();
    await page.getByRole('button', { name: 'Reset', exact: true }).click();
    await expect(page.locator('#taxPeriod')).toHaveValue('');
    await expect(page.locator('#invoice-list tbody tr')).toHaveCount(20);
    await expect(page.locator('[data-page-info]')).toContainText('32 faktur');
    await page.getByRole('button', { name: 'Halaman berikutnya' }).click();
    await expect(page.locator('#invoice-list tbody tr')).toHaveCount(12);
    await expect(page).toHaveURL(/page=2/);
    await page.getByRole('button', { name: 'Halaman sebelumnya' }).click();
    await expect(page.locator('#invoice-list tbody tr')).toHaveCount(20);
    await page.route('**/api/v1/tax-invoices?**', (route) => route.fulfill({ status: 503, json: { code: '503', message: 'Server pengujian tidak tersedia', data: null, errorCode: 'TEST' } }));
    await page.getByRole('button', { name: 'Cari', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Daftar faktur gagal dimuat' })).toBeVisible();
    await page.unroute('**/api/v1/tax-invoices?**');
    await page.getByRole('button', { name: 'Cari', exact: true }).click();
    await expect(page.locator('#invoice-list tbody tr')).toHaveCount(20);
    await page.getByRole('button', { name: 'Keluar', exact: true }).click();
    await expect(page).toHaveURL(/\/login$/);
    expect(await page.evaluate(() => sessionStorage.getItem('e1.accessToken'))).toBeNull();
    await page.getByRole('button', { name: 'Masuk Akun Demo' }).click();
    await expect(page.locator('#invoice-list tbody tr')).toHaveCount(20);
});

test('rekap, pratinjau, unduhan, detail, koreksi, dan pembayaran Draft', async ({ page }) => {
    await login(page, 'COO');
    await page.goto('/tax-recaps/2026-11');
    await expect(page.locator('#recap-summary')).toContainText('Rp 46.200.000,00');
    await expect(page.locator('#recap-invoices')).toContainText('November 2026');
    await expect(page.locator('#recap-invoices tbody tr').first().locator('td').nth(1)).toHaveText(/^[0-9]{1,2} November 2026$/);
    await expect(page.locator('#recap-payments tbody tr').first().locator('td').nth(4)).toHaveText(/ WIB$/);
    await page.getByRole('button', { name: 'Ekspor', exact: true }).click();
    await page.getByRole('button', { name: 'Pratinjau Excel', exact: true }).click();
    await expect(page.locator('#export-preview')).toContainText('Faktur dihitung');
    await expect(page.locator('#export-preview')).toBeVisible();
    await expect(page.locator('#pdf-preview')).toBeHidden();
    await page.locator('#export-format').selectOption('pdf');
    await expect(page.locator('#export-preview')).toBeHidden();
    await page.getByRole('button', { name: 'Pratinjau PDF' }).click();
    await expect(page.locator('#pdf-preview iframe')).toHaveAttribute('src', /^blob:/);
    for (const format of ['xlsx', 'pdf']) {
        await page.locator('#export-format').selectOption(format);
        await expect(page.locator('#preview-pdf')).toHaveText(`Pratinjau ${format === 'xlsx' ? 'Excel' : 'PDF'}`);
        const download = page.waitForEvent('download');
        await page.getByRole('button', { name: 'Unduh Rekap' }).click();
        expect((await download).suggestedFilename()).toBe(`rekap-pajak-2026-11.${format}`);
    }
    await page.keyboard.press('Escape');
    await expect(page.locator('#export-dialog')).not.toBeVisible();
    await expect(page.getByRole('button', { name: 'Ekspor', exact: true })).toBeFocused();
    await page.getByRole('link', { name: 'Lengkapi', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Lengkapi Pembayaran Pajak' })).toBeVisible();
    await page.locator('#ntpn').fill('NTPN-UI-VERIFIED');
    await page.locator('#paymentProofFile').setInputFiles(resolve('storage/framework/testing/e1-browser-samples/bukti-pembayaran.pdf'));
    await page.getByRole('button', { name: 'Konfirmasi Pembayaran' }).click();
    await expect(page).toHaveURL(/\/tax-recaps\/2026-11$/);
    await expect(page.locator('#recap-payments')).toContainText('Terkonfirmasi');
    await page.locator('#recap-invoices').getByRole('link', { name: 'Detail', exact: true }).first().click();
    await expect(page.locator('#invoice-history')).toContainText('Pencatatan faktur');
    await page.getByRole('button', { name: 'Koreksi Faktur', exact: true }).click();
    await page.locator('#note').fill('Pemeriksaan koreksi melalui UI');
    await page.locator('#correction-form [name="ppnbm"]').fill('1000.00');
    await page.getByRole('button', { name: 'Simpan Koreksi', exact: true }).click();
    await expect(page.locator('#correction-dialog')).not.toBeVisible();
    await expect(page.locator('#invoice-history')).toContainText('Pemeriksaan koreksi melalui UI');
    await page.getByText('Lihat nilai sebelum dan sesudah', { exact: true }).last().click();
    await expect(page.locator('#invoice-history pre').last()).toBeVisible();
    await page.goto('/tax-recaps/2026-12/payments/create');
    await expect(page.locator('#taxPeriod')).toHaveValue('Desember 2026');
    await expect(page.locator('#payment-fields')).not.toHaveAttribute('disabled');
    await page.locator('#amountPaid').fill('1000000.00');
    await page.locator('#billingId').fill('BILL-UI-DRAFT');
    await page.locator('#eBillingFile').setInputFiles(resolve('storage/framework/testing/e1-browser-samples/e-billing.pdf'));
    await page.getByRole('button', { name: 'Simpan Pembayaran', exact: true }).click();
    await expect(page).toHaveURL(/\/tax-recaps\/2026-12$/);
    await expect(page.locator('#recap-payments')).toContainText('BILL-UI-DRAFT');
    await expect(page.locator('#recap-payments')).toContainText('Draft / Belum Konfirmasi');
});

test('pencatatan PDF, konflik, pembatalan, dan faktur pengganti', async ({ page }) => {
    await login(page, 'CEO');
    await page.getByRole('link', { name: 'Catat e-Faktur Keluaran', exact: true }).click();
    await chooseInvoice(page, 'inv-demo-004');
    await expect(page.locator('#invoice-context')).toContainText('Pemeliharaan Aplikasi Keuangan');
    await page.locator('#eFakturFile').setInputFiles(resolve('storage/framework/testing/e1-browser-samples/bukan-pdf.txt'));
    await page.getByRole('button', { name: 'Periksa PDF', exact: true }).click();
    await expect(page.locator('#app-alert')).toContainText('Berkas harus berupa PDF');
    await page.locator('#eFakturFile').setInputFiles(resolve('storage/framework/testing/e1-browser-samples/efaktur-inv-demo-004.pdf'));
    await page.getByRole('button', { name: 'Periksa PDF', exact: true }).click();
    await expect(page.locator('#app-alert')).toBeVisible();
    await expect(page.locator('#save-invoice')).toBeDisabled();
    await page.getByRole('combobox', { name: 'Invoice D-2', exact: true }).fill('inv-demo-001');
    await expect(page.getByRole('option')).toHaveCount(0);
    await expect(page.locator('#extract')).toBeDisabled();
    await chooseInvoice(page, 'inv-demo-006');
    await page.locator('#eFakturFile').setInputFiles(resolve('storage/framework/testing/e1-browser-samples/efaktur-inv-demo-006.pdf'));
    await page.getByRole('button', { name: 'Periksa PDF', exact: true }).click();
    await expect(page.locator('#save-invoice')).toBeEnabled();
    await page.locator('#eFakturFile').setInputFiles(resolve('storage/framework/testing/e1-browser-samples/efaktur-inv-demo-006-pengganti.pdf'));
    await expect(page.locator('#save-invoice')).toBeDisabled();
    await page.locator('#eFakturFile').setInputFiles(resolve('storage/framework/testing/e1-browser-samples/efaktur-inv-demo-006.pdf'));
    await page.getByRole('button', { name: 'Periksa PDF', exact: true }).click();
    await expect(page.locator('#save-invoice')).toBeEnabled();
    await page.getByRole('button', { name: 'Simpan Faktur', exact: true }).click();
    await expect(page).toHaveURL(/\/tax-invoices\/[a-f0-9-]+$/);
    await expect(page.locator('#detail-data')).toContainText('inv-demo-006');
    await page.getByRole('button', { name: 'Catat Pengganti', exact: true }).click();
    await expect(page.locator('#replacement-info')).toBeVisible();
    await expect(page.locator('#invoiceId')).toBeDisabled();
    await expect(page.locator('#invoiceId')).toHaveValue('inv-demo-006');
    await page.locator('#eFakturFile').setInputFiles(resolve('storage/framework/testing/e1-browser-samples/efaktur-inv-demo-006-pengganti.pdf'));
    await page.getByRole('button', { name: 'Simpan Faktur', exact: true }).click();
    await expect(page.locator('#detail-status')).toContainText('Pengganti');
    const originalHref = await page.getByRole('link', { name: 'Buka faktur asal', exact: true }).getAttribute('href');
    await Promise.all([page.waitForURL(`**${originalHref}`), page.getByRole('link', { name: 'Buka faktur asal', exact: true }).click()]);
    await expect(page.locator('#detail-status')).toContainText('Terganti');
    await page.getByRole('link', { name: 'Buka faktur pengganti', exact: true }).click();
    await page.getByRole('button', { name: 'Batalkan', exact: true }).click();
    await page.locator('#cancelNote').fill('Pengujian pembatalan melalui UI');
    await page.getByRole('button', { name: 'Ya, Batalkan Faktur' }).click();
    await expect(page.locator('#detail-status')).toContainText('Dibatalkan');
});

test('mobile, tablet, keyboard, periode kosong, dan respons akses ditolak', async ({ page }) => {
    await login(page);
    for (const width of [360, 768, 1024, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/tax-recaps/2026-11');
        await expect(page.locator('#recap-summary')).toContainText('Total PPN');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.screenshot({ path: `test-results/recap-${width}.png`, fullPage: true });
        if (width < 1024) {
            await page.getByRole('button', { name: 'Menu', exact: true }).click();
            await expect(page.locator('#sidebar')).toHaveAttribute('aria-modal', 'true');
            await page.keyboard.press('Escape');
            await expect(page.getByRole('button', { name: 'Menu', exact: true })).toBeFocused();
        }
    }
    await page.goto('/tax-recaps/2026-01');
    await expect(page.getByRole('heading', { name: 'Rekap masa pajak kosong' })).toBeVisible();
    await page.goto('/tax-recaps/2026-01/payments/create');
    await expect(page.locator('#payment-fields')).toHaveAttribute('disabled', '');
    await expect(page.locator('#save-payment')).toBeDisabled();
    await page.route('**/api/v1/tax-invoices?**', (route) => route.fulfill({ status: 403, json: { message: 'Akses ditolak', data: null } }));
    await page.goto('/tax-invoices');
    await expect(page.locator('#access-denied')).toBeVisible();
    await expect(page.locator('#page-content')).not.toBeVisible();
});

test('aksesibilitas enam layar, dialog, navigasi keyboard, dan zoom 200%', async ({ page }) => {
    const scans = [];
    async function scan(name) {
        const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
        scans.push({ screen: name, violations: result.violations, passes: result.passes.map((rule) => rule.id) });
        writeFileSync('test-results/accessibility.json', JSON.stringify(scans, null, 2));
        expect(result.violations, name).toEqual([]);
    }
    await page.goto('/login');
    await scan('login');
    await login(page);
    for (const [route, name] of [['/tax-invoices', 'daftar'], ['/tax-invoices/create', 'pencatatan'], ['/tax-recaps/2026-11', 'rekap'], ['/tax-recaps/2026-12/payments/create', 'pembayaran']]) {
        await page.goto(route);
        await expect(page.locator('#page-content')).toBeVisible();
        await expect(page.locator('#page-content .skeleton-bar:visible')).toHaveCount(0);
        await scan(name);
        if (name === 'rekap') {
            await page.getByRole('button', { name: 'Ekspor', exact: true }).click();
            await scan('dialog-ekspor');
            await page.getByRole('button', { name: 'Tutup Ekspor Rekap' }).click();
            await page.locator('#recap-invoices').getByRole('link', { name: 'Detail', exact: true }).first().click();
            await expect(page.locator('#invoice-history')).toContainText('Pencatatan faktur');
            await scan('detail');
            await page.getByRole('button', { name: 'Koreksi Faktur', exact: true }).click();
            await scan('dialog-koreksi');
            const count = await page.locator('[data-correction-item]').count();
            await page.getByRole('button', { name: 'Tambah Item', exact: true }).click();
            await expect(page.locator('[data-correction-item]')).toHaveCount(count + 1);
            await page.getByRole('button', { name: 'Hapus item', exact: true }).last().click();
            await expect(page.locator('[data-correction-item]')).toHaveCount(count);
            await page.locator('#correction-dialog').getByRole('button', { name: 'Batal', exact: true }).click();
            await page.getByRole('button', { name: 'Batalkan', exact: true }).click();
            await scan('dialog-pembatalan');
            await page.locator('#cancellation-dialog').getByRole('button', { name: 'Kembali', exact: true }).click();
        }
    }
    await page.setViewportSize({ width: 768, height: 900 });
    await page.goto('/tax-invoices');
    await expect(page.locator('#invoice-list tbody tr').first()).toBeVisible();
    await page.evaluate(() => { document.documentElement.style.zoom = '2'; });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: 'test-results/zoom-200.png', fullPage: true });
    await page.getByRole('button', { name: 'Menu', exact: true }).click();
    await scan('menu-mobile-zoom');
    await page.getByRole('button', { name: 'Tutup menu' }).click();
    await page.keyboard.press('Tab');
    expect(await page.evaluate(() => document.activeElement.matches('a,button,input,select,textarea,[tabindex]'))).toBe(true);
});

test('token tidak sah dan peran tanpa akses ditolak oleh API sebenarnya', async ({ page, request }) => {
    const response = await request.post('/api/dev/f1/login', { data: { akunId: 'AKN-UI-PM', kodePeran: 'PM' } });
    const { data } = await response.json();
    await page.goto('/login');
    await page.route('**/api/dev/f1/login', (route) => route.fulfill({ json: { data } }));
    await page.getByRole('button', { name: 'Masuk Akun Demo' }).click();
    await expect(page.locator('#app-alert')).toContainText('akses tidak cukup');
    expect(await page.evaluate(() => sessionStorage.getItem('e1.accessToken'))).toBeNull();
    await page.evaluate(() => sessionStorage.setItem('e1.accessToken', 'token-tidak-sah'));
    await page.goto('/tax-invoices');
    await expect(page).toHaveURL(/\/login\?next=/);
    expect(await page.evaluate(() => sessionStorage.getItem('e1.accessToken'))).toBeNull();
});

test('dropdown masa pajak menyembunyikan periode kosong dan menangani daftar kosong', async ({ page }) => {
    await login(page);
    await page.goto('/tax-recaps/2026-11');
    await expect(page.locator('#selectedPeriod')).toBeEnabled();
    await expect(page.locator('#selectedPeriod option[value="2026-11"]')).toHaveText('November 2026');
    await expect(page.locator('#selectedPeriod option[value="2026-01"]')).toHaveCount(0);
    await page.goto('/tax-recaps/2026-01');
    await expect(page).toHaveURL(/\/tax-recaps\/2026-01$/);
    await expect(page.getByRole('heading', { name: 'Rekap masa pajak kosong' })).toBeVisible();
    await expect(page.locator('#recap-summary')).toContainText('Rp 0,00');
    await expect(page.locator('#selectedPeriod')).toHaveValue('');
    await expect(page.locator('#selectedPeriod option[value="2026-01"]')).toHaveCount(0);
    await page.locator('#selectedPeriod').selectOption('2026-11');
    await page.getByRole('button', { name: 'Tampilkan Rekap', exact: true }).click();
    await expect(page).toHaveURL(/\/tax-recaps\/2026-11$/);
    await expect(page.locator('#selectedPeriod')).toHaveValue('2026-11');
    await page.route('**/api/ui/tax-recap-periods', (route) => route.fulfill({ json: { data: { periods: [] } } }));
    await page.goto('/tax-recaps/2026-01');
    await expect(page.locator('#selectedPeriod')).toBeDisabled();
    await expect(page.locator('#selectedPeriod option')).toHaveText('Belum ada masa pajak');
    await expect(page.getByRole('button', { name: 'Tampilkan Rekap', exact: true })).toBeDisabled();
    await expect(page.getByRole('heading', { name: 'Rekap masa pajak kosong' })).toBeVisible();
});

test('kontrol navigasi, validasi wajib, dan tombol kembali', async ({ page }) => {
    await login(page);
    await page.getByRole('link', { name: 'Catat e-Faktur Keluaran', exact: true }).click();
    await expect(page.locator('#invoiceId')).toBeEnabled();
    await expect(page.getByRole('button', { name: 'Periksa PDF', exact: true })).toBeDisabled();
    expect(await page.locator('#invoice-search').evaluate((input) => input.validity.valueMissing)).toBe(true);
    await page.locator('#cancel-create').click();
    await expect(page).toHaveURL(/\/tax-invoices/);
    await page.getByRole('link', { name: 'Catat e-Faktur Keluaran', exact: true }).click();
    await page.locator('#create-back').click();
    await page.locator('#sidebar').getByRole('link', { name: 'Rekap Masa Pajak', exact: true }).click();
    await expect(page.locator('#selectedPeriod')).toBeEnabled();
    await page.locator('#selectedPeriod').selectOption('2026-11');
    await page.getByRole('button', { name: 'Tampilkan Rekap', exact: true }).click();
    await expect(page).toHaveURL(/\/tax-recaps\/2026-11$/);
    await expect(page.locator('#selectedPeriod')).toHaveValue('2026-11');
    await expect(page.locator('#recap-summary')).toContainText('Total PPN');
    await page.getByRole('link', { name: 'Catat Pembayaran', exact: true }).click();
    await expect(page.locator('#payment-fields')).not.toHaveAttribute('disabled');
    await page.locator('#payment-form').getByRole('link', { name: 'Batal', exact: true }).click();
    await page.locator('#sidebar').getByRole('link', { name: 'Pembayaran Pajak', exact: true }).click();
    await page.getByRole('link', { name: 'Kembali ke Rekap', exact: true }).click();
    await page.locator('#topbar').getByRole('link', { name: 'Pajak proyek', exact: true }).click();
    await expect(page.locator('#invoice-list tbody tr').first()).toBeVisible();
    await page.locator('#sidebar > a').click();
    await expect(page).toHaveURL(/\/tax-invoices/);
});
