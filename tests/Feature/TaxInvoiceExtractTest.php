<?php

namespace Tests\Feature;

use App\Models\FakturPajak;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** API-KE01-01: pratinjau ekstraksi PDF e-Faktur (tidak menyimpan). */
class TaxInvoiceExtractTest extends TestCase
{
    private const URI = '/api/v1/tax-invoices/extractions';

    public function test_ekstraksi_berhasil_dan_tidak_menyimpan_apa_pun(): void
    {
        $response = $this->post(self::URI, [
            'invoiceId' => 'inv-demo-001',
            'eFakturFile' => $this->pdfUpload($this->efakturPdf(['nsfp' => '010.000-26.12345678'])),
        ], $this->auth());

        $response->assertOk()->assertExactJson([
            'code' => '200',
            'message' => 'Hasil ekstraksi (belum tersimpan)',
            'data' => [
                'invoiceId' => 'inv-demo-001',
                'taxInvoiceNumber' => '010.000-26.12345678',
                'taxInvoiceDate' => '2026-11-01',
                'taxPeriod' => '2026-11',
                'dpp' => '100000000.00',
                'ppn' => '11000000.00',
                'ppnbm' => '0.00',
                'items' => [[
                    'itemCode' => '620100',
                    'itemName' => 'Jasa Pengembangan Perangkat Lunak Termin 1',
                    'saleReplacementAdvanceTermAmount' => '100000000.00',
                    'discountAmount' => '0.00',
                    'receivedAdvanceAmount' => '0.00',
                ]],
            ],
        ]);

        $this->assertSame(0, FakturPajak::query()->count());
        $this->assertDatabaseCount('riwayat_perubahan_faktur', 0);
        $this->assertDatabaseCount('rekap_pajak_bulanan', 0);
    }

    public function test_berkas_bukan_pdf_ditolak_invalid_file(): void
    {
        $this->post(self::URI, [
            'invoiceId' => 'inv-demo-001',
            'eFakturFile' => UploadedFile::fake()->createWithContent('faktur.pdf', 'ini teks biasa, bukan pdf'),
        ], $this->auth())
            ->assertStatus(400)
            ->assertExactJson([
                'code' => '400',
                'message' => 'Berkas harus berformat PDF',
                'data' => null,
                'errorCode' => 'INVALID_FILE',
            ]);
    }

    public function test_berkas_tidak_dikirim_ditolak_invalid_file(): void
    {
        $this->post(self::URI, ['invoiceId' => 'inv-demo-001'], $this->auth())
            ->assertStatus(400)->assertJsonPath('errorCode', 'INVALID_FILE');
    }

    public function test_berkas_melebihi_batas_5_mb_ditolak(): void
    {
        $this->post(self::URI, [
            'invoiceId' => 'inv-demo-001',
            'eFakturFile' => UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf'),
        ], $this->auth())
            ->assertStatus(400)->assertJsonPath('errorCode', 'INVALID_FILE');
    }

    public function test_pdf_sah_tanpa_data_pajak_gagal_ekstraksi(): void
    {
        $this->post(self::URI, [
            'invoiceId' => 'inv-demo-001',
            'eFakturFile' => $this->pdfUpload($this->simplePdf()),
        ], $this->auth())
            ->assertStatus(400)
            ->assertJsonPath('errorCode', 'EXTRACTION_FAILED')
            ->assertJsonPath('message', 'PDF e-Faktur tidak dapat diproses');
    }

    public function test_invoice_id_kosong_ditolak(): void
    {
        $this->post(self::URI, [
            'eFakturFile' => $this->pdfUpload($this->efakturPdf()),
        ], $this->auth())
            ->assertStatus(400)->assertJsonPath('errorCode', 'INVALID_INVOICE_ID');
    }

    public function test_invoice_tidak_dikenal_d2_404(): void
    {
        $this->post(self::URI, [
            'invoiceId' => 'inv-tidak-ada',
            'eFakturFile' => $this->pdfUpload($this->efakturPdf()),
        ], $this->auth())
            ->assertStatus(404)
            ->assertJsonPath('code', '404')
            ->assertJsonPath('errorCode', 'INVOICE_NOT_FOUND');
    }

    public function test_invoice_yang_sudah_memiliki_faktur_aktif_409(): void
    {
        $this->createFaktur('inv-demo-001');

        $this->post(self::URI, [
            'invoiceId' => 'inv-demo-001',
            'eFakturFile' => $this->pdfUpload($this->efakturPdf()),
        ], $this->auth())
            ->assertStatus(409)
            ->assertJsonPath('errorCode', 'INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE')
            ->assertJsonPath('message', 'Invoice ini sudah memiliki faktur aktif');
    }

    public function test_nsfp_yang_sudah_terdaftar_409(): void
    {
        $this->createFaktur('inv-demo-001', ['nsfp' => '010.000-26.00000777']);

        $this->post(self::URI, [
            'invoiceId' => 'inv-demo-002',
            'eFakturFile' => $this->pdfUpload($this->efakturPdf(['nsfp' => '010.000-26.00000777'])),
        ], $this->auth())
            ->assertStatus(409)
            ->assertJsonPath('errorCode', 'DUPLICATE_NSFP')
            ->assertJsonPath('message', 'NSFP sudah terdaftar');
    }

    public function test_peran_tidak_berwenang_403_tanpa_menyentuh_data(): void
    {
        $this->post(self::URI, [
            'invoiceId' => 'inv-demo-001',
            'eFakturFile' => $this->pdfUpload($this->efakturPdf()),
        ], $this->auth('Finance'))
            ->assertStatus(403)->assertJsonPath('errorCode', 'FORBIDDEN');
    }
}
