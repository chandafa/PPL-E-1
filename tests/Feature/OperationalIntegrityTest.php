<?php

namespace Tests\Feature;

use App\Services\Invoices\HttpInvoiceProvider;
use App\Services\Invoices\InvoiceProvider;
use App\Services\Tax\FileStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class OperationalIntegrityTest extends TestCase
{
    public function test_pengganti_tidak_dihitung_tetapi_tetap_mengunci_invoice_aktif(): void
    {
        $original = $this->createFaktur();
        $replacement = $this->createFaktur('inv-demo-001', [], $original['taxInvoiceId']);
        $this->getJson('/api/v1/tax-recaps/2026-11', $this->auth())->assertOk()
            ->assertJsonPath('data.recap.invoiceCount', 0)
            ->assertJsonPath('data.recap.totalPpn', '0.00');

        $this->post('/api/v1/tax-invoices/outgoing', [
            'invoiceId' => 'inv-demo-001', 'eFakturFile' => $this->pdfUpload($this->efakturPdf()),
        ], $this->auth())->assertStatus(409)->assertJsonPath('errorCode', 'INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE');

        $this->getJson('/api/v1/tax-invoices/'.$replacement['taxInvoiceId'], $this->auth())
            ->assertOk()->assertJsonPath('data.taxInvoice.status', 'REPLACEMENT');
        $text = (new Parser)->parseContent($this->get('/api/v1/tax-recaps/2026-11/export?format=pdf', $this->auth())->getContent())->getText();
        $this->assertStringContainsString('Pengganti', $text);
        $this->assertStringNotContainsString('Pengganti (terganti)', $text);
    }

    public function test_kegagalan_disk_tidak_membuat_faktur_atau_uri_palsu(): void
    {
        Storage::shouldReceive('disk')->with('local')->andReturn($disk = Mockery::mock());
        $disk->shouldReceive('put')->once()->andReturn(false);
        $this->post('/api/v1/tax-invoices/outgoing', [
            'invoiceId' => 'inv-demo-001', 'eFakturFile' => $this->pdfUpload($this->efakturPdf()),
        ], $this->auth())->assertStatus(500)->assertJsonPath('errorCode', 'INTERNAL_ERROR')->assertJsonPath('data', null);
        $this->assertDatabaseCount('faktur_pajak', 0);
        $this->assertDatabaseCount('riwayat_perubahan_faktur', 0);
    }

    public function test_bukti_gagal_disimpan_membersihkan_ebilling_dan_tidak_mencatat_pembayaran(): void
    {
        $this->createFaktur();
        $before = Storage::disk('local')->allFiles();
        $files = new FileStore;
        $mock = Mockery::mock(FileStore::class)->makePartial();
        $mock->shouldReceive('put')->once()->with('e-billing', Mockery::type('string'), Mockery::type('string'))
            ->andReturnUsing(fn ($dir, $id, $binary) => $files->put($dir, $id, $binary));
        $mock->shouldReceive('put')->once()->with('payment-proof', Mockery::type('string'), Mockery::type('string'))
            ->andThrow(new RuntimeException('Disk gagal'));
        $this->app->instance(FileStore::class, $mock);
        $this->post('/api/v1/tax-payments', [
            'taxPeriod' => '2026-11', 'taxYear' => 2026, 'amountPaid' => '11000000.00',
            'billingId' => 'BILL-FAIL', 'ntpn' => 'NTPN-FAIL',
            'eBillingFile' => $this->pdfUpload($this->simplePdf()),
            'paymentProofFile' => $this->pdfUpload($this->simplePdf()),
        ], $this->auth())->assertStatus(500)->assertJsonPath('errorCode', 'INTERNAL_ERROR');
        $this->assertDatabaseCount('pembayaran_pajak', 0);
        $this->assertSame($before, Storage::disk('local')->allFiles());
        $this->getJson('/api/v1/tax-recaps/2026-11', $this->auth())->assertJsonPath('data.recap.paymentStatus', 'NOT_RECORDED');
    }

    public function test_d2_gagal_atau_identitas_tidak_sesuai_ditolak_tanpa_data_parsial(): void
    {
        $this->app->instance(InvoiceProvider::class, new HttpInvoiceProvider('https://d2.test', '/invoices/{invoiceId}', 3));
        foreach ([
            Http::response(['data' => ['invoiceId' => 'inv-salah']], 200),
            Http::response(['data' => null], 200),
            Http::response('bukan-json', 200),
            Http::response(['data' => ['invoiceId' => 'inv-demo-001', 'projectId' => []]], 200),
            Http::response([], 503),
            Http::failedConnection(),
        ] as $failure) {
            Http::swap(new \Illuminate\Http\Client\Factory);
            Http::fake(['d2.test/*' => $failure]);
            $this->post('/api/v1/tax-invoices/outgoing', [
                'invoiceId' => 'inv-demo-001', 'eFakturFile' => $this->pdfUpload($this->efakturPdf()),
            ], $this->auth())->assertStatus(503)->assertJsonPath('errorCode', 'D2_UNAVAILABLE');
            $this->assertDatabaseCount('faktur_pajak', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }

    public function test_d2_404_dibedakan_dari_gagal_dan_data_valid_diterima(): void
    {
        $provider = new HttpInvoiceProvider('https://d2.test', '/invoices/{invoiceId}', 3);
        Http::fakeSequence('d2.test/*')->push([], 404)
            ->push(['invoiceId' => 'inv-demo-001'])
            ->push(['data' => ['invoiceId' => 'inv-demo-001']]);
        $this->assertNull($provider->find('inv-demo-001'));
        for ($i = 0; $i < 2; $i++) {
            $this->assertSame('inv-demo-001', $provider->find('inv-demo-001')->invoiceId);
        }
    }

    public function test_decimal_maksimum_utuh_pada_json_excel_pdf_dan_id_excel_bukan_formula(): void
    {
        $f = $this->createFaktur('inv-demo-004', ['date' => '1 Desember 2026']);
        $this->patchJson('/api/v1/tax-invoices/'.$f['taxInvoiceId'], [
            'changes' => ['dpp' => '9999999999999999.99', 'ppn' => '1234567890123456.78'],
        ], $this->auth())->assertOk()->assertJsonPath('data.taxInvoice.dpp', '9999999999999999.99');
        $this->createPayment('2026-12', '1234567890123456.78', ['billingId' => '=1+1']);
        $this->getJson('/api/v1/tax-recaps/2026-12', $this->auth())->assertOk()
            ->assertJsonPath('data.recap.totalDpp', '9999999999999999.99')
            ->assertJsonPath('data.recap.paymentDifference', '0.00');
        $path = tempnam(sys_get_temp_dir(), 'e1-xlsx');
        try {
            file_put_contents($path, $this->get('/api/v1/tax-recaps/2026-12/export?format=xlsx', $this->auth())->getContent());
            $book = IOFactory::load($path);
            $this->assertSame('9999999999999999.99', $book->getSheetByName('Faktur')->getCell('G2')->getValue());
            $this->assertSame('1234567890123456.78', $book->getSheetByName('Pembayaran')->getCell('C2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $book->getSheetByName('Pembayaran')->getCell('A2')->getDataType());
            $this->assertSame('=1+1', $book->getSheetByName('Pembayaran')->getCell('A2')->getValue());
        } finally {
            unlink($path);
        }
        $text = (new Parser)->parseContent($this->get('/api/v1/tax-recaps/2026-12/export?format=pdf', $this->auth())->getContent())->getText();
        $this->assertStringContainsString('9.999.999.999.999.999,99', $text);
        $this->assertStringContainsString('1.234.567.890.123.456,78', $text);
    }
}
