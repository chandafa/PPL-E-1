<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\Support\OpenApiContract;
use Tests\TestCase;

/**
 * Uji kontrak: respons API nyata dan seluruh examples/*.json harus valid terhadap
 * skema pada openapi.yaml, sehingga dokumen kontrak dan implementasi tidak menyimpang.
 */
class OpenApiContractTest extends TestCase
{
    private const OPERATIONS = [
        '01' => 'extractTaxInvoice',
        '02' => 'createOutgoingTaxInvoice',
        '03' => 'listTaxInvoices',
        '04' => 'getTaxInvoice',
        '05' => 'updateTaxInvoice',
        '06' => 'getTaxInvoiceHistory',
        '07' => 'getTaxRecap',
        '08' => 'createTaxPayment',
        '09' => 'exportTaxRecap',
        '10' => 'getTaxSummary',
        '11' => 'completeTaxPayment',
    ];

    private OpenApiContract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contract = new OpenApiContract;
    }

    private function assertContract(TestResponse $response, string $operationId): void
    {
        $status = $response->getStatusCode();

        $this->assertTrue(
            $this->contract->hasResponse($operationId, $status),
            "Respons {$status} tidak didefinisikan pada openapi.yaml untuk {$operationId}: ".substr((string) $response->getContent(), 0, 200),
        );

        $body = json_decode((string) $response->getContent(), false);
        $violations = $this->contract->validateResponse($operationId, $status, $body);

        $this->assertSame([], $violations, "{$operationId} {$status} melanggar kontrak: ".substr((string) $response->getContent(), 0, 400));

        if ($status >= 400) {
            $this->assertSame((string) $status, $body->code, 'code harus sama dengan status HTTP');
        }
    }

    public function test_dokumen_memuat_sebelas_operasi(): void
    {
        $this->assertEqualsCanonicalizing(array_values(self::OPERATIONS), $this->contract->operationIds());
    }

    public function test_nominal_json_decimal_presisi_tanpa_pemisah_ribuan(): void
    {
        $this->assertSame([], $this->contract->validateAgainstSchema('MoneyInput', '11000000.00'));
        $this->assertSame([], $this->contract->validateAgainstSchema('Money', '11000000.00'));
        $this->assertSame([], $this->contract->validateAgainstSchema('SignedMoney', '-600000.00'));
        $this->assertSame([], $this->contract->validateAgainstSchema('Money', '9999999999999999.99'));
        $this->assertNotSame([], $this->contract->validateAgainstSchema('Money', '11.000.000,00'));
        $this->assertNotSame([], $this->contract->validateAgainstSchema('MoneyInput', '11.000.000,00'));
    }

    public function test_seluruh_contoh_json_valid_terhadap_skema(): void
    {
        $checked = 0;

        foreach (glob(base_path('examples/api-ke01-*.json')) as $file) {
            preg_match('/api-ke01-(\d{2})-(success|error)\.json$/', $file, $m);
            $operationId = self::OPERATIONS[$m[1]];
            $body = json_decode((string) file_get_contents($file), false, flags: JSON_THROW_ON_ERROR);

            $status = $m[2] === 'success' ? 200 : (int) $body->code;
            $violations = $this->contract->validateResponse($operationId, $status, $body);

            $this->assertSame([], $violations, basename($file).' tidak sesuai skema');
            $checked++;
        }

        $this->assertSame(21, $checked, '11 contoh sukses (kecuali 09 biner) + 11 contoh gagal');
    }

    public function test_respons_nyata_semua_operasi_sesuai_kontrak(): void
    {
        $h = $this->auth();
        $pdf = fn (string $name = 'f.pdf') => $this->pdfUpload($this->efakturPdf(['nsfp' => '010.000-26.12345678']), $name);

        // --- API-KE01-01
        $this->assertContract($this->post('/api/v1/tax-invoices/extractions', ['invoiceId' => 'inv-demo-001', 'eFakturFile' => $pdf()], $h), 'extractTaxInvoice');
        $this->assertContract($this->post('/api/v1/tax-invoices/extractions', ['invoiceId' => 'inv-demo-001', 'eFakturFile' => UploadedFile::fake()->createWithContent('x.pdf', 'teks')], $h), 'extractTaxInvoice');
        $this->assertContract($this->post('/api/v1/tax-invoices/extractions', ['invoiceId' => 'inv-demo-001', 'eFakturFile' => $this->pdfUpload($this->simplePdf())], $h), 'extractTaxInvoice');
        $this->assertContract($this->post('/api/v1/tax-invoices/extractions', ['invoiceId' => 'tidak-ada', 'eFakturFile' => $pdf()], $h), 'extractTaxInvoice');
        $this->assertContract($this->post('/api/v1/tax-invoices/extractions', ['eFakturFile' => $pdf()], $h), 'extractTaxInvoice');
        $this->assertContract($this->post('/api/v1/tax-invoices/extractions', [], ['Accept' => 'application/json']), 'extractTaxInvoice');             // 401
        $this->assertContract($this->post('/api/v1/tax-invoices/extractions', [], $this->auth('Finance')), 'extractTaxInvoice');                         // 403

        // --- API-KE01-02
        $created = $this->post('/api/v1/tax-invoices/outgoing', ['invoiceId' => 'inv-demo-001', 'eFakturFile' => $pdf()], $h);
        $this->assertContract($created, 'createOutgoingTaxInvoice');
        $taxInvoiceId = $created->json('data.taxInvoiceId');

        $this->assertContract($this->post('/api/v1/tax-invoices/extractions', ['invoiceId' => 'inv-demo-001', 'eFakturFile' => $pdf()], $h), 'extractTaxInvoice');   // 409
        $this->assertContract($this->post('/api/v1/tax-invoices/outgoing', ['invoiceId' => 'inv-demo-001', 'eFakturFile' => $pdf()], $h), 'createOutgoingTaxInvoice');  // 409
        $this->assertContract($this->post('/api/v1/tax-invoices/outgoing', ['invoiceId' => 'inv-demo-002', 'eFakturFile' => $pdf(), 'replacesTaxInvoiceId' => $taxInvoiceId], $h), 'createOutgoingTaxInvoice'); // 400
        $this->assertContract($this->post('/api/v1/tax-invoices/outgoing', ['invoiceId' => 'inv-demo-001', 'eFakturFile' => $pdf(), 'replacesTaxInvoiceId' => '0191f3a0-7c1e-7a52-9d3b-4f6e2a1b8c99'], $h), 'createOutgoingTaxInvoice'); // 404

        $replacement = $this->post('/api/v1/tax-invoices/outgoing', [
            'invoiceId' => 'inv-demo-001',
            'eFakturFile' => $this->pdfUpload($this->efakturPdf(['nsfp' => '010.001-26.00000002', 'dpp' => '90000000.00', 'ppn' => '9900000.00'])),
            'replacesTaxInvoiceId' => $taxInvoiceId,
        ], $h);
        $this->assertContract($replacement, 'createOutgoingTaxInvoice');
        $replacementId = $replacement->json('data.taxInvoiceId');

        // --- API-KE01-03
        $this->assertContract($this->getJson('/api/v1/tax-invoices?taxPeriod=2026-11', $h), 'listTaxInvoices');
        $this->assertContract($this->getJson('/api/v1/tax-invoices?status=NORMAL&pageSize=1', $h), 'listTaxInvoices');
        $this->assertContract($this->getJson('/api/v1/tax-invoices?taxPeriod=2026-13', $h), 'listTaxInvoices');

        // --- API-KE01-04
        $this->assertContract($this->getJson('/api/v1/tax-invoices/'.$taxInvoiceId, $h), 'getTaxInvoice');
        $this->assertContract($this->getJson('/api/v1/tax-invoices/'.$replacementId, $h), 'getTaxInvoice');
        $this->assertContract($this->getJson('/api/v1/tax-invoices/0191f3a0-7c1e-7a52-9d3b-4f6e2a1b8c99', $h), 'getTaxInvoice');

        // --- API-KE01-05
        $this->assertContract($this->patchJson('/api/v1/tax-invoices/'.$replacementId, ['changes' => ['taxInvoiceDate' => '2026-11-10'], 'note' => 'koreksi'], $h), 'updateTaxInvoice');
        $this->assertContract($this->patchJson('/api/v1/tax-invoices/'.$replacementId, ['changes' => ['invoiceId' => 'x']], $h), 'updateTaxInvoice');
        $this->assertContract($this->patchJson('/api/v1/tax-invoices/0191f3a0-7c1e-7a52-9d3b-4f6e2a1b8c99', ['changes' => ['dpp' => '1.00']], $h), 'updateTaxInvoice');

        // --- API-KE01-06
        $this->assertContract($this->getJson('/api/v1/tax-invoices/'.$replacementId.'/history', $h), 'getTaxInvoiceHistory');
        $this->assertContract($this->getJson('/api/v1/tax-invoices/0191f3a0-7c1e-7a52-9d3b-4f6e2a1b8c99/history', $h), 'getTaxInvoiceHistory');

        // --- API-KE01-07
        $this->assertContract($this->getJson('/api/v1/tax-recaps/2026-11', $h), 'getTaxRecap');
        $this->assertContract($this->getJson('/api/v1/tax-recaps/2026-01', $h), 'getTaxRecap');
        $this->assertContract($this->getJson('/api/v1/tax-recaps/2026-13', $h), 'getTaxRecap');

        // --- API-KE01-08
        $payment = $this->post('/api/v1/tax-payments', [
            'taxPeriod' => '2026-11', 'taxYear' => 2026, 'amountPaid' => '9900000.00', 'billingId' => 'BILL-202611-001',
            'eBillingFile' => $this->pdfUpload($this->simplePdf()),
        ], $h);
        $this->assertContract($payment, 'createTaxPayment');
        $paymentId = $payment->json('data.taxPaymentId');

        $this->assertContract($this->post('/api/v1/tax-payments', ['taxPeriod' => '2026-11', 'taxYear' => 2026, 'amountPaid' => '-1', 'billingId' => 'B', 'eBillingFile' => $this->pdfUpload($this->simplePdf())], $h), 'createTaxPayment');
        $this->assertContract($this->post('/api/v1/tax-payments', ['taxPeriod' => '2030-01', 'taxYear' => 2030, 'amountPaid' => '1.00', 'billingId' => 'B', 'eBillingFile' => $this->pdfUpload($this->simplePdf())], $h), 'createTaxPayment');

        // --- API-KE01-11
        $this->assertContract($this->patchMultipart('/api/v1/tax-payments/'.$paymentId, [], [], $h), 'completeTaxPayment');
        $this->assertContract($this->patchMultipart('/api/v1/tax-payments/0191f3a0-7c1e-7a52-9d3b-4f6e2a1b8c99', ['ntpn' => 'N'], ['paymentProofFile' => $this->pdfUpload($this->simplePdf())], $h), 'completeTaxPayment');
        $this->assertContract($this->patchMultipart('/api/v1/tax-payments/'.$paymentId, ['ntpn' => 'NTPN-ABC123'], ['paymentProofFile' => $this->pdfUpload($this->simplePdf())], $h), 'completeTaxPayment');

        // --- API-KE01-09 (biner; hanya galat yang JSON)
        $this->assertContract($this->get('/api/v1/tax-recaps/2026-11/export?format=csv', $h), 'exportTaxRecap');
        $this->assertContract($this->get('/api/v1/tax-recaps/2026-13/export?format=xlsx', $h), 'exportTaxRecap');
        $this->assertContract($this->get('/api/v1/tax-recaps/2026-11/export?format=xlsx', ['Authorization' => 'Bearer salah', 'Accept' => 'application/json']), 'exportTaxRecap');

        // --- API-KE01-10
        $this->assertContract($this->getJson('/api/v1/tax/summary?taxPeriod=2026-11', $h), 'getTaxSummary');
        $this->assertContract($this->getJson('/api/v1/tax/summary?taxPeriod=2026-11&projectId=prj-demo-001', $h), 'getTaxSummary');
        $this->assertContract($this->getJson('/api/v1/tax/summary?taxPeriod=2026-13', $h), 'getTaxSummary');
        $this->assertContract($this->getJson('/api/v1/tax/summary?taxPeriod=2026-11&projectId=tidak-ada', $h), 'getTaxSummary');
        $this->assertContract($this->getJson('/api/v1/tax/summary?taxPeriod=2026-11', $this->auth('Finance')), 'getTaxSummary');
    }
}
