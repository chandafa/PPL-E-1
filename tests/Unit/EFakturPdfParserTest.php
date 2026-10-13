<?php

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Services\EFaktur\EFakturPdfParser;
use Database\Seeders\Support\FakeEFakturPdf;
use PHPUnit\Framework\TestCase;

class EFakturPdfParserTest extends TestCase
{
    public function test_membaca_semua_data_inti_faktur_keluaran(): void
    {
        $result = (new EFakturPdfParser)->parse(FakeEFakturPdf::make());

        $this->assertSame('010.000-26.12345678', $result->taxInvoiceNumber);
        $this->assertSame('2026-11-01', $result->taxInvoiceDate);
        $this->assertSame('2026-11', $result->taxPeriod);
        $this->assertSame('100000000.00', $result->dpp);
        $this->assertSame('11000000.00', $result->ppn);
        $this->assertSame('0.00', $result->ppnbm);
        $this->assertCount(1, $result->items);
        $this->assertSame([
            'itemCode' => '620100',
            'itemName' => 'Jasa Pengembangan Perangkat Lunak Termin 1',
            'saleReplacementAdvanceTermAmount' => '100000000.00',
            'discountAmount' => '0.00',
            'receivedAdvanceAmount' => '0.00',
        ], $result->items[0]);
    }

    public function test_membaca_banyak_item_potongan_dan_uang_muka(): void
    {
        $pdf = FakeEFakturPdf::make([
            'nsfp' => '010.001-26.00000042',
            'date' => '15 Desember 2026',
            'items' => [
                ['code' => 'A-1', 'name' => 'Lisensi Aplikasi', 'amount' => '60000000.00'],
                ['code' => 'B-2', 'name' => 'Jasa Implementasi', 'amount' => '40000000.50', 'discount' => '500000.00'],
            ],
            'discount' => '500000.00',
            'advance' => '10000000.00',
            'dpp' => '89500000.50',
            'ppn' => '9845000.06',
            'ppnbm' => '1000.00',
        ]);

        $result = (new EFakturPdfParser)->parse($pdf);

        $this->assertSame('010.001-26.00000042', $result->taxInvoiceNumber);
        $this->assertSame('2026-12-15', $result->taxInvoiceDate);
        $this->assertSame('2026-12', $result->taxPeriod);
        $this->assertSame('89500000.50', $result->dpp);
        $this->assertSame('9845000.06', $result->ppn);
        $this->assertSame('1000.00', $result->ppnbm);
        $this->assertCount(2, $result->items);
        $this->assertSame('A-1', $result->items[0]['itemCode']);
        $this->assertSame('60000000.00', $result->items[0]['saleReplacementAdvanceTermAmount']);
        $this->assertSame('10000000.00', $result->items[0]['receivedAdvanceAmount']);
        $this->assertSame('B-2', $result->items[1]['itemCode']);
        $this->assertSame('40000000.50', $result->items[1]['saleReplacementAdvanceTermAmount']);
        $this->assertSame('500000.00', $result->items[1]['discountAmount']);
    }

    public function test_ppnbm_tidak_ada_pada_pdf_dianggap_nol(): void
    {
        $result = (new EFakturPdfParser)->parse(FakeEFakturPdf::make(['ppnbm' => null]));

        $this->assertSame('0.00', $result->ppnbm);
    }

    public function test_pdf_sah_tanpa_data_pajak_ditolak(): void
    {
        $this->expectException(ApiException::class);

        try {
            (new EFakturPdfParser)->parse(FakeEFakturPdf::unrelated());
        } catch (ApiException $e) {
            $this->assertSame(400, $e->status);
            $this->assertSame('EXTRACTION_FAILED', $e->errorCode);
            throw $e;
        }
    }

    public function test_berkas_rusak_ditolak_sebagai_gagal_ekstraksi(): void
    {
        $this->expectException(ApiException::class);

        (new EFakturPdfParser)->parse('%PDF-1.4 ini bukan pdf yang benar');
    }
}
