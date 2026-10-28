<?php

namespace Tests\Feature;

use App\Models\FakturPajak;
use App\Models\PembayaranPajak;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Karakterisasi WAPT: perilaku saat ini, bukan persyaratan menerima berkas palsu. */
class UploadFilenameTamperingTest extends TestCase
{
    public static function filenames(): array
    {
        return [
            'baseline' => ['dokumen.pdf', 'application/pdf'],
            'php' => ['dokumen.php', 'application/x-httpd-php'],
            'double extension' => ['dokumen.pdf.php', 'application/pdf'],
            'mixed case' => ['dokumen.pHp', 'application/octet-stream'],
        ];
    }

    #[DataProvider('filenames')]
    public function test_pdf_asli_diterima_dan_disimpan_dengan_nama_server(string $name, string $mime): void
    {
        $binary = $this->efakturPdf();
        $file = UploadedFile::fake()->createWithContent($name, $binary)->mimeType($mime);

        $response = $this->post('/api/v1/tax-invoices/outgoing', [
            'invoiceId' => 'inv-demo-001',
            'eFakturFile' => $file,
        ], $this->auth());

        $response->assertOk();
        $id = $response->json('data.taxInvoiceId');
        $faktur = FakturPajak::query()->findOrFail($id);
        $this->assertSame("/files/tax/e-faktur/{$id}.pdf", $faktur->pdf_uri);
        $this->assertSame(["tax/e-faktur/{$id}.pdf"], Storage::disk('local')->allFiles());
        $this->assertSame($binary, Storage::disk('local')->get("tax/e-faktur/{$id}.pdf"));
    }

    public function test_kode_php_tanpa_penanda_pdf_ditolak_meski_mime_pdf(): void
    {
        $file = UploadedFile::fake()->createWithContent('dokumen.php', '<?php /* q8v2m6z9r4 */ ?>')
            ->mimeType('application/pdf');

        $this->post('/api/v1/tax-invoices/outgoing', [
            'invoiceId' => 'inv-demo-001', 'eFakturFile' => $file,
        ], $this->auth())->assertStatus(400)->assertJsonPath('errorCode', 'INVALID_FILE');

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('faktur_pajak', 0);
    }

    public function test_penanda_pdf_palsu_gagal_di_parser_faktur(): void
    {
        $this->post('/api/v1/tax-invoices/outgoing', [
            'invoiceId' => 'inv-demo-001',
            'eFakturFile' => $this->pdfUpload($this->fakePdf(), 'dokumen.php'),
        ], $this->auth())->assertStatus(400)->assertJsonPath('errorCode', 'EXTRACTION_FAILED');

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('faktur_pajak', 0);
    }

    public function test_penanda_pdf_palsu_lolos_pada_ebilling_dan_bukti_saat_create(): void
    {
        $this->createFaktur();
        $binary = $this->fakePdf();
        $response = $this->post('/api/v1/tax-payments', [
            'taxPeriod' => '2026-11', 'taxYear' => 2026, 'amountPaid' => '11000000.00',
            'billingId' => 'q8v2m6z9r4', 'ntpn' => 'h7n3w9k2p6',
            'eBillingFile' => $this->pdfUpload($binary, 'ebilling.php'),
            'paymentProofFile' => $this->pdfUpload($binary, 'bukti.pdf.php'),
        ], $this->auth());

        $response->assertOk()->assertJsonPath('data.status', 'CONFIRMED');
        $payment = PembayaranPajak::query()->findOrFail($response->json('data.taxPaymentId'));
        $this->assertStoredAsPdf($payment->e_billing_uri, $binary);
        $this->assertStoredAsPdf($payment->payment_proof_uri, $binary);
    }

    public function test_penanda_pdf_palsu_lolos_pada_bukti_saat_complete(): void
    {
        $this->createFaktur();
        $payment = $this->createPayment('2026-11', '11000000.00');
        $binary = $this->fakePdf();

        $this->patchMultipart('/api/v1/tax-payments/'.$payment['taxPaymentId'], [
            'ntpn' => 'h7n3w9k2p6',
        ], [
            'paymentProofFile' => $this->pdfUpload($binary, 'bukti.php'),
        ])->assertOk()->assertJsonPath('data.status', 'CONFIRMED');

        $stored = PembayaranPajak::query()->findOrFail($payment['taxPaymentId']);
        $this->assertStoredAsPdf($stored->payment_proof_uri, $binary);
    }

    private function fakePdf(): string
    {
        // Inert: tidak ada echo, pemanggilan fungsi, akses file, atau perintah sistem.
        // Penanda sengaja berada dalam komentar, bukan struktur dokumen PDF.
        return '<?php /* q8v2m6z9r4 %PDF-1.7 */ ?>';
    }

    private function assertStoredAsPdf(string $uri, string $binary): void
    {
        $this->assertStringEndsWith('.pdf', $uri);
        $this->assertStringNotContainsString('.php', $uri);
        $this->assertSame($binary, Storage::disk('local')->get(substr($uri, strlen('/files/'))));
    }
}
