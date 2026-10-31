import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';

const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(path.join(repo, 'package.json'));
const { chromium } = require('@playwright/test');
const runtime = JSON.parse(fs.readFileSync(path.join(repo, 'storage/app/private/integration/current.json'), 'utf8'));
const relative = `docs/integrasi/bukti/${new Date().toISOString().replace(/[:.]/g, '-')}`;
const out = path.join(repo, relative);
fs.mkdirSync(out, { recursive: true });
const evidence = { startedAtUtc: new Date().toISOString(), mode: 'HTTP ke server demo resmi F-1 dan backend database D-2; D-1 internal D-2 masih mock', steps: [], checks: [], stateChecks: [], browser: [] };
const redact = value => {
    if (Array.isArray(value)) return value.map(redact);
    if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).map(([key, v]) => [key, /password|accessToken|authorization|jwtSecret/i.test(key) ? '[REDACTED]' : redact(v)]));
    return typeof value === 'string' ? value.replace(/eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/g, '[JWT REDACTED]') : value;
};
function check(name, actual) { assert.ok(actual, name); evidence.checks.push({ name, result: 'PASS' }); }
function snapshot() { return JSON.parse(execFileSync('php', ['scripts/snapshot-integration.php'], { cwd: repo, encoding: 'utf8' })); }
function sameState(id, before) {
    const after = snapshot();
    check(`${id}: database, audit dan berkas tidak berubah`, JSON.stringify(before) === JSON.stringify(after));
    evidence.stateChecks.push({ id, before, after, unchanged: true });
}
async function req(id, base, url, options = {}) {
    const { expected = 200, token, body, form, observation = false, headers = {}, method = 'GET' } = options;
    const requestId = `E1-P6-${id}-${crypto.randomUUID()}`;
    const requestHeaders = { Accept: 'application/json', 'X-Request-ID': requestId, ...headers };
    if (token) requestHeaders.Authorization = `Bearer ${token}`;
    if (body) requestHeaders['Content-Type'] = 'application/json';
    const startedAtUtc = new Date().toISOString();
    const response = await fetch(base + url, { method, headers: requestHeaders, body: form || (body ? JSON.stringify(body) : undefined), redirect: 'manual', signal: AbortSignal.timeout(15000) });
    const contentType = response.headers.get('content-type') || '';
    let data;
    if (contentType.includes('json')) data = await response.json();
    else {
        const bytes = Buffer.from(await response.arrayBuffer());
        const file = `${id}.${contentType.includes('pdf') ? 'pdf' : contentType.includes('spreadsheet') ? 'xlsx' : 'bin'}`;
        fs.writeFileSync(path.join(out, file), bytes);
        data = { file, bytes: bytes.length, sha256: crypto.createHash('sha256').update(bytes).digest('hex'), contentType };
    }
    evidence.steps.push({ id, requestId, startedAtUtc, finishedAtUtc: new Date().toISOString(), request: { method, url: base + url, headers: redact(requestHeaders), body: redact(body || (form ? Object.fromEntries([...form].map(([k, v]) => [k, typeof v === 'string' ? v : { file: v.name, bytes: v.size }])) : null)) }, response: { status: response.status, body: redact(data) }, expectedStatus: expected, expectationMet: response.status === expected, observation });
    if (!observation) check(`${id}: HTTP ${expected}`, response.status === expected);
    return { data, status: response.status };
}
function form(fields, files = {}) {
    const result = new FormData();
    for (const [key, value] of Object.entries(fields)) result.set(key, value);
    for (const [key, file] of Object.entries(files)) result.set(key, new Blob([fs.readFileSync(path.join(out, 'input', file))], { type: 'application/pdf' }), file);
    return result;
}
function stop(service) {
    execFileSync('powershell', ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', 'scripts/stop-integration.ps1', '-Service', service], { cwd: repo, encoding: 'utf8' });
}
let browser;
try {
    const login = await req('F1-01', runtime.f1BaseUrl, '/auth/login', { method: 'POST', body: { emailOrUsername: 'super.admin', password: 'Password123!' } });
    const admin = login.data.accessToken;
    const claims = JSON.parse(Buffer.from(admin.split('.')[1], 'base64url'));
    evidence.claimsObserved = { accountId: claims.accountId, roleCode: claims.roleCode, expPresent: Number.isInteger(claims.exp), issPresent: !!claims.iss, audPresent: !!claims.aud };
    check('F1-01: JWT tiga segmen memiliki exp dan accountId/roleCode', admin.split('.').length === 3 && claims.exp && claims.accountId && claims.roleCode === 'ADMIN');
    await req('F1-02', runtime.e1BaseUrl, '/api/auth/f1/login', { method: 'POST', body: { emailOrUsername: 'super.admin', password: 'salah' }, expected: 401 });
    await req('F1-03', runtime.e1BaseUrl, '/api/auth/f1/login', { method: 'POST', body: { emailOrUsername: 'amaliyah', password: 'Password123!' }, expected: 403 });
    await req('F1-04', runtime.e1BaseUrl, '/api/auth/f1/login', { method: 'POST', body: { emailOrUsername: 'icha.aprilia', password: 'Password123!' }, expected: 403 });
    const employeeLogin = await req('F1-05', runtime.e1BaseUrl, '/api/auth/f1/login', { method: 'POST', body: { emailOrUsername: 'dwi.saputra', password: 'Password123!' } });
    const employee = employeeLogin.data.data.accessToken;
    for (const [id, token, status] of [['A-01', null, 401], ['A-02', admin + 'x', 401], ['A-03', employee, 403]]) {
        const before = snapshot();
        await req(id, runtime.e1BaseUrl, '/api/v1/tax-invoices', { token, expected: status });
        sameState(id, before);
    }
    const jwt = require(path.join(repo, '../_integrasi/F1/node_modules/jsonwebtoken'));
    const expired = jwt.sign({ accountId: claims.accountId, roleCode: 'ADMIN' }, runtime.jwtSecret, { algorithm: 'HS256', expiresIn: -60 });
    await req('A-04', runtime.e1BaseUrl, '/api/v1/tax-invoices', { token: expired, expected: 401 });

    const invoice = await req('D2-01', runtime.d2BaseUrl, '/api/v1/invoices/inv-demo-sample', { token: admin });
    check('D2-01: proyek nested dan tanggal invoice contoh', invoice.data.data.terkait.projectId === 'prj-demo-001' && invoice.data.data.invoiceDate === '2026-10-11');
    await req('D2-02', runtime.d2BaseUrl, '/api/v1/invoices/inv-tidak-ada', { token: admin, expected: 404 });
    await req('D2-03', runtime.d2BaseUrl, '/api/v1/invoices/inv-demo-sample', { expected: 401 });
    await req('D2-04', runtime.d2BaseUrl, '/api/v1/invoices/inv-demo-sample', { token: 'arbitrary-token', expected: 401, observation: true });
    await req('D2-05', runtime.d2BaseUrl, '/api/v1/invoices/inv-demo-sample', { token: expired, expected: 401, observation: true });
    await req('D2-06', runtime.d2BaseUrl, '/api/v1/invoices/inv-demo-sample', { token: employee, expected: 403, observation: true });
    await req('D2-07', runtime.d2BaseUrl, '/api/v1/invoices', { token: admin, expected: 405, observation: true });
    const created = await req('D2-08', runtime.d2BaseUrl, '/api/v1/invoices', { method: 'POST', token: admin, body: { terminId: 'trm-demo-001', invoiceDate: '2026-11-01', dppAmount: '100000000.00' }, expected: 201 });
    const reloaded = await req('D2-09', runtime.d2BaseUrl, '/api/v1/invoices/' + created.data.data.invoiceId, { token: admin });
    check('D2-09: invoice baru benar-benar tersimpan dan nominal konsisten', reloaded.data.data.dppAmount === created.data.data.dppAmount && reloaded.data.data.invoiceDate === '2026-11-01');
    await req('D2-10', runtime.d2BaseUrl, '/api/v1/invoices', { method: 'POST', token: admin, body: { terminId: 'trm-demo-001', invoiceDate: '2026-11-01', dppAmount: '100000000.00' }, expected: 409 });
    fs.mkdirSync(path.join(out, 'input'));
    fs.writeFileSync(path.join(out, 'input/invoice-d2.json'), JSON.stringify(invoice.data, null, 2));
    execFileSync('php', ['scripts/generate-integration-samples.php', path.join(out, 'input/invoice-d2.json'), path.join(out, 'input')], { cwd: repo });

    const edge = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
    browser = await chromium.launch({ headless: true,
        ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH }
            : fs.existsSync(edge) ? { executablePath: edge } : {}),
    });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    await page.route('**/api/**', async route => route.continue({ headers: { ...route.request().headers(), 'x-request-id': `E1-P6-UI-${crypto.randomUUID()}` } }));
    await page.goto(runtime.e1BaseUrl + '/login');
    check('UI: login demo E-1 tidak tampil pada profil HTTP', await page.locator('#demo-login').count() === 0);
    await page.locator('[name=emailOrUsername]').fill('super.admin');
    await page.locator('[name=password]').fill('Password123!');
    const loginWait = page.waitForResponse(r => r.url().endsWith('/api/auth/f1/login'));
    await page.locator('#f1-login button[type=submit]').click();
    const browserLogin = await loginWait;
    check('UI: login melalui F-1 berhasil', browserLogin.status() === 200);
    await page.waitForURL(u => u.pathname === '/tax-invoices');
    const beforePreview = snapshot();
    await page.goto(runtime.e1BaseUrl + '/tax-invoices/create');
    await page.locator('[name=invoiceId]').fill('inv-demo-sample');
    await page.locator('[name=eFakturFile]').setInputFiles(path.join(out, 'input/efaktur.pdf'));
    const previewWait = page.waitForResponse(r => r.url().endsWith('/api/v1/tax-invoices/extractions'));
    await page.locator('#extract').click();
    const preview = await previewWait;
    const previewData = await preview.json();
    evidence.steps.push({ id: 'UI-01', requestId: preview.request().headers()['x-request-id'], request: { method: 'POST', url: preview.url(), body: { invoiceId: 'inv-demo-sample', eFakturFile: 'input/efaktur.pdf' } }, response: { status: preview.status(), body: previewData } });
    check('UI-01: ekstraksi sesuai tanggal Oktober, PPN dan PPnBM jasa', preview.status() === 200 && previewData.data.taxPeriod === '2026-10' && previewData.data.ppn === '11000000.00' && previewData.data.ppnbm === '0.00');
    sameState('UI-01', beforePreview);
    await page.locator('#save-invoice:enabled').waitFor();
    await page.screenshot({ path: path.join(out, 'UI-01-pratinjau.png'), fullPage: true });
    const saveWait = page.waitForResponse(r => r.url().endsWith('/api/v1/tax-invoices/outgoing'));
    await page.locator('#save-invoice').click();
    const saved = await saveWait;
    const savedData = await saved.json();
    evidence.steps.push({ id: 'UI-02', requestId: saved.request().headers()['x-request-id'], request: { method: 'POST', url: saved.url(), body: { invoiceId: 'inv-demo-sample', eFakturFile: 'input/efaktur.pdf' } }, response: { status: saved.status(), body: savedData } });
    const id = savedData.data.taxInvoiceId;
    evidence.taxInvoiceId = id;
    evidence.accountId = claims.accountId;
    check('UI-02: tersimpan dengan identitas asli F-1 dan invoice D-2', saved.status() === 200 && savedData.data.createdByAkunId === claims.accountId && savedData.data.invoiceId === 'inv-demo-sample');
    await page.waitForURL(u => u.pathname === '/tax-invoices/' + id);
    await page.waitForFunction(() => !document.querySelector('#page-content').hidden && document.body.innerText.includes('prj-demo-001'));
    await page.locator('#auth-pending').waitFor({ state: 'hidden' });
    await page.screenshot({ path: path.join(out, 'UI-02-tersimpan.png'), fullPage: true });
    evidence.browser.push({ id: 'UI-01', screenshot: 'UI-01-pratinjau.png', result: 'PASS' }, { id: 'UI-02', screenshot: 'UI-02-tersimpan.png', result: 'PASS' });
    await req('E1-03', runtime.e1BaseUrl, '/api/v1/tax-invoices?project=prj-demo-001', { token: admin });
    const detail = await req('E1-04', runtime.e1BaseUrl, '/api/v1/tax-invoices/' + id, { token: admin });
    check('E1-04: relasi invoice/proyek utuh; klien yang tidak dikirim tetap null', detail.data.data.taxInvoice.invoiceId === 'inv-demo-sample' && detail.data.data.taxInvoice.projectId === 'prj-demo-001' && detail.data.data.taxInvoice.clientId === null);
    const beforeDuplicate = snapshot();
    await req('V-01', runtime.e1BaseUrl, '/api/v1/tax-invoices/outgoing', { method: 'POST', token: admin, form: form({ invoiceId: 'inv-demo-sample' }, { eFakturFile: 'efaktur.pdf' }), expected: 409 });
    sameState('V-01', beforeDuplicate);
    const beforeMissing = snapshot();
    await req('V-02', runtime.e1BaseUrl, '/api/v1/tax-invoices/outgoing', { method: 'POST', token: admin, form: form({ invoiceId: 'inv-tidak-ada' }, { eFakturFile: 'efaktur.pdf' }), expected: 404 });
    sameState('V-02', beforeMissing);
    const items = detail.data.data.items.map(({ itemCode, itemName, saleReplacementAdvanceTermAmount, discountAmount, receivedAdvanceAmount }) => ({ itemCode, itemName: itemName + ' (koreksi uraian)', saleReplacementAdvanceTermAmount, discountAmount, receivedAdvanceAmount }));
    await req('E1-05', runtime.e1BaseUrl, '/api/v1/tax-invoices/' + id, { method: 'PATCH', token: admin, body: { changes: { items }, note: 'Koreksi uraian jasa pada uji integrasi lokal' } });
    const history = await req('E1-06', runtime.e1BaseUrl, '/api/v1/tax-invoices/' + id + '/history', { token: admin });
    check('E1-06: riwayat CREATE dan UPDATE tercatat', JSON.stringify(history.data).includes('UPDATE') && JSON.stringify(history.data).includes(claims.accountId));
    const recap = await req('E1-07', runtime.e1BaseUrl, '/api/v1/tax-recaps/2026-10', { token: admin });
    check('E1-07: total PPN sesuai faktur', recap.data.data.recap.totalPpn === '11000000.00');
    const payment = await req('E1-08', runtime.e1BaseUrl, '/api/v1/tax-payments', { method: 'POST', token: admin, form: form({ taxPeriod: '2026-10', taxYear: '2026', amountPaid: '5500000.00', billingId: 'BILL-INTEGRASI-001' }, { eBillingFile: 'e-billing.pdf' }) });
    evidence.paymentId = payment.data.data.taxPaymentId;
    check('E1-08: pembayaran awal Draft', payment.data.data.status === 'INCOMPLETE');
    const complete = await req('E1-11', runtime.e1BaseUrl, '/api/v1/tax-payments/' + evidence.paymentId, { method: 'PATCH', token: admin, form: form({ ntpn: 'NTPN-INTEGRASI-001' }, { paymentProofFile: 'bukti-pembayaran.pdf' }) });
    check('E1-11: pembayaran menjadi CONFIRMED', complete.data.data.status === 'CONFIRMED');
    const beforePaymentError = snapshot();
    await req('V-03', runtime.e1BaseUrl, '/api/v1/tax-payments', { method: 'POST', token: admin, form: form({ taxPeriod: '2026-10', taxYear: '2026', amountPaid: '5500000.00', billingId: 'BILL-INTEGRASI-002', ntpn: 'NTPN-INTEGRASI-001' }, { eBillingFile: 'e-billing.pdf', paymentProofFile: 'bukti-pembayaran.pdf' }), expected: 409 });
    sameState('V-03', beforePaymentError);
    await req('V-04', runtime.e1BaseUrl, '/api/v1/tax-payments', { method: 'POST', token: admin, form: form({ taxPeriod: '2026-10', taxYear: '2026', amountPaid: '5500000.01', billingId: 'BILL-INTEGRASI-003' }, { eBillingFile: 'e-billing.pdf' }), expected: 400 });
    sameState('V-04', beforePaymentError);
    await req('E1-09-PDF', runtime.e1BaseUrl, '/api/v1/tax-recaps/2026-10/export?format=pdf', { token: admin });
    await req('E1-09-XLSX', runtime.e1BaseUrl, '/api/v1/tax-recaps/2026-10/export?format=xlsx', { token: admin });
    const summary = await req('E1-10', runtime.e1BaseUrl, '/api/v1/tax/summary?taxPeriod=2026-10&projectId=prj-demo-001', { token: admin });
    check('E1-10: agregat proyek cocok dan tidak membuka rincian pembayaran', summary.data.data.invoiceCount === 1 && summary.data.data.totalPpn === '11000000.00' && !JSON.stringify(summary.data).includes('NTPN'));

    const account = await req('F1-GAP-01', runtime.f1BaseUrl, '/accounts', { method: 'POST', body: { employeeId: '01929b4a-5f10-7a22-9d44-3c8f1b2e7a90', roleCode: 'EMPLOYEE', username: `e1.test.${Date.now()}` }, expected: 401, observation: true });
    await req('F1-GAP-02', runtime.f1BaseUrl, '/accounts/' + account.data.accountId, { method: 'PUT', body: { status: 'inactive' }, expected: 401, observation: true });
    // Mengubah akun pegawai fiktif dalam server demo milik runtime ini saja.
    await req('F1-STATUS-SETUP', runtime.f1BaseUrl, '/accounts/' + employeeLogin.data.data.accountId, { method: 'PUT', token: admin, body: { status: 'inactive' } });
    await req('A-05', runtime.e1BaseUrl, '/api/v1/tax-invoices', { token: employee, expected: 403 });
    await req('F1-STATUS-RESTORE', runtime.f1BaseUrl, '/accounts/' + employeeLogin.data.data.accountId, { method: 'PUT', token: admin, body: { status: 'active' } });
    const beforeOutage = snapshot();
    stop('d2');
    await page.goto(runtime.e1BaseUrl + '/tax-invoices/create');
    await page.locator('[name=invoiceId]').fill(created.data.data.invoiceId);
    await page.locator('[name=eFakturFile]').setInputFiles(path.join(out, 'input/efaktur.pdf'));
    const failWait = page.waitForResponse(r => r.url().endsWith('/api/v1/tax-invoices/extractions'));
    await page.locator('#extract').click();
    const failed = await failWait;
    evidence.steps.push({ id: 'UI-03', requestId: failed.request().headers()['x-request-id'], request: { method: 'POST', url: failed.url() }, response: { status: failed.status(), body: await failed.json() } });
    check('UI-03: D-2 putus ditangani 503 dan Simpan dinonaktifkan', failed.status() === 503 && await page.locator('#save-invoice').isDisabled());
    await page.locator('#app-alert:not([hidden])').waitFor();
    await page.screenshot({ path: path.join(out, 'UI-03-d2-putus.png'), fullPage: true });
    evidence.browser.push({ id: 'UI-03', screenshot: 'UI-03-d2-putus.png', result: 'PASS' });
    sameState('UI-03', beforeOutage);
    await browser.close(); browser = null;
    stop('f1');
    await req('A-06', runtime.e1BaseUrl, '/api/v1/tax-invoices', { token: admin, expected: 503 });
    sameState('A-06', beforeOutage);
    evidence.finalState = snapshot();
    evidence.result = 'PASS';
} catch (error) {
    evidence.result = 'FAIL'; evidence.error = redact(error.message);
    process.exitCode = 1;
} finally {
    if (browser) await browser.close();
    for (const name of ['f1', 'd2']) {
        const file = path.join(runtime.directory, `${name}-requests.ndjson`);
        if (fs.existsSync(file)) fs.copyFileSync(file, path.join(out, `${name}-requests.ndjson`));
    }
    evidence.finishedAtUtc = new Date().toISOString();
    fs.writeFileSync(path.join(out, 'bukti-http.json'), JSON.stringify(redact(evidence), null, 2) + '\n');
    fs.writeFileSync(path.join(repo, 'docs/integrasi/hasil-terbaru.json'), JSON.stringify({ directory: relative, result: evidence.result, steps: evidence.steps.length, assertions: evidence.checks.length, contractGaps: evidence.steps.filter(x => x.observation && !x.expectationMet).map(x => x.id) }, null, 2) + '\n');
    console.log(JSON.stringify({ result: evidence.result, requests: evidence.steps.length, assertions: evidence.checks.length, evidence: relative, error: evidence.error }));
}
