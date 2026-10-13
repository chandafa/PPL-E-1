<?php

namespace Tests;

use Database\Seeders\Support\FakeEFakturPdf;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    private static int $nsfpCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    // ----- token F-1 ---------------------------------------------------

    /** @param array<string, mixed> $claims menimpa klaim bawaan (mis. ['exp' => time() - 10]) */
    protected function token(string $role = 'Admin', string $akunId = 'AKN-0002', array $claims = []): string
    {
        $now = time();

        return JWT::encode(array_merge([
            'iss' => 'f1-identity',
            'aud' => 'erp-anaheim',
            'iat' => $now,
            'exp' => $now + 3600,
            'akunId' => $akunId,
            'kodePeran' => $role,
        ], $claims), (string) config('e1.f1.secret'), 'HS256');
    }

    /** @return array<string, string> */
    protected function auth(string $role = 'Admin', string $akunId = 'AKN-0002'): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token($role, $akunId),
            'Accept' => 'application/json',
        ];
    }

    // ----- berkas ------------------------------------------------------

    protected function pdfUpload(string $binary, string $name = 'dokumen.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $binary);
    }

    /** PDF e-Faktur fiktif dengan NSFP unik per pemanggilan (kecuali ditimpa). */
    protected function efakturPdf(array $options = []): string
    {
        $options += ['nsfp' => sprintf('010.000-26.%08d', ++self::$nsfpCounter)];

        return FakeEFakturPdf::make($options);
    }

    /** PDF sah apa adanya (e-Billing/bukti); konstan sehingga dibuat sekali. */
    protected function simplePdf(): string
    {
        static $pdf = null;

        return $pdf ??= FakeEFakturPdf::unrelated();
    }

    // ----- alur umum ---------------------------------------------------

    /**
     * Catat faktur lewat API-KE01-02 dan kembalikan `data` respons.
     *
     * @param  array<string, mixed>  $pdf  opsi FakeEFakturPdf (nsfp, date, dpp, ppn, ...)
     * @return array<string, mixed>
     */
    protected function createFaktur(string $invoiceId = 'inv-demo-001', array $pdf = [], ?string $replaces = null, string $role = 'Admin'): array
    {
        $payload = [
            'invoiceId' => $invoiceId,
            'eFakturFile' => $this->pdfUpload($this->efakturPdf($pdf), 'efaktur.pdf'),
        ];
        if ($replaces !== null) {
            $payload['replacesTaxInvoiceId'] = $replaces;
        }

        $response = $this->post('/api/v1/tax-invoices/outgoing', $payload, $this->auth($role));
        $response->assertOk();

        return $response->json('data');
    }

    /** Catat pembayaran lewat API-KE01-08 dan kembalikan `data` respons. */
    protected function createPayment(string $taxPeriod, string $amount, array $extra = []): array
    {
        $payload = array_merge([
            'taxPeriod' => $taxPeriod,
            'taxYear' => (int) substr($taxPeriod, 0, 4),
            'amountPaid' => $amount,
            'billingId' => 'BILL-'.str_replace('-', '', $taxPeriod).'-001',
            'eBillingFile' => $this->pdfUpload($this->simplePdf(), 'ebilling.pdf'),
        ], $extra);

        $response = $this->post('/api/v1/tax-payments', $payload, $this->auth());
        $response->assertOk();

        return $response->json('data');
    }

    /** PATCH multipart (klien uji tidak mengirim multipart untuk PATCH secara bawaan). */
    protected function patchMultipart(string $uri, array $fields, array $files = [], ?array $headers = null)
    {
        return $this->call(
            'PATCH',
            $uri,
            $fields,
            [],
            $files,
            $this->transformHeadersToServerVars($headers ?? $this->auth()),
        );
    }
}
