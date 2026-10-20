<?php

namespace Tests\Feature;

use App\Models\RiwayatPerubahanFaktur;
use Database\Seeders\Support\DemoDataset;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Smalot\PdfParser\Parser;
use Tests\Support\TaxPdf;
use Tests\TestCase;

class TaxAmountsTest extends TestCase
{
    public function test_produk_dan_jasa_satu_proyek_tidak_saling_mewarisi_ppnbm_dan_rekap_mengikuti_siklus_faktur(): void
    {
        $fixtures = DemoDataset::invoices(includeManual: true);
        $goods = $this->createFaktur('inv-manual-ppnbm-001', $fixtures['inv-manual-ppnbm-001']['pdf']);
        $service = $this->createFaktur('inv-manual-jasa-001', $fixtures['inv-manual-jasa-001']['pdf']);
        $this->assertSame('750000000.00', $goods['ppnbm']);
        $this->assertSame('0.00', $service['ppnbm']);
        $this->assertSame('YACHT-01', $goods['items'][0]['itemCode']);
        $this->assertSame('JASA-01', $service['items'][0]['itemCode']);
        $summary = '/api/v1/tax/summary?taxPeriod=2026-10&projectId=prj-simulasi-yacht-001';
        $this->getJson($summary, $this->auth())->assertOk()
            ->assertJsonPath('data.invoiceCount', 2)
            ->assertJsonPath('data.totalPpn', '121100000.00')
            ->assertJsonPath('data.totalPpnbm', '750000000.00');
        $this->getJson('/api/v1/tax/summary?taxPeriod=2026-10&projectId=prj-demo-001', $this->auth())
            ->assertOk()->assertJsonPath('data.totalPpnbm', '0.00');
        $uri = '/api/v1/tax-invoices/'.$goods['taxInvoiceId'];
        $corrected = $this->patchJson($uri, [
            'changes' => ['ppn' => '120000000.25', 'ppnbm' => '750000000.75'], 'note' => 'Uji koreksi nominal dokumen',
        ], $this->auth())->assertOk();
        $audit = RiwayatPerubahanFaktur::findOrFail($corrected->json('data.auditId'));
        $this->assertSame('750000000.00', $audit->before_snapshot['ppnbm']);
        $this->assertSame('750000000.75', $audit->after_snapshot['ppnbm']);
        $this->createPayment('2026-10', '120000000.00');
        $this->getJson('/api/v1/tax-recaps/2026-10', $this->auth())->assertOk()
            ->assertJsonPath('data.recap.totalPpn', '121100000.25')
            ->assertJsonPath('data.recap.totalPpnbm', '750000000.75')
            ->assertJsonPath('data.recap.paymentDifference', '-1100000.25');

        $replacement = $this->createFaktur('inv-manual-ppnbm-001', [
            'date' => '2 Desember 2026', 'dpp' => '900000000.00',
            'ppn' => '108000000.00', 'ppnbm' => '675000000.00',
            'items' => [['code' => 'YACHT-01', 'name' => 'Yacht privat produksi sendiri (pengganti simulasi)', 'amount' => '900000000.00']],
        ], $goods['taxInvoiceId']);
        $this->getJson('/api/v1/tax-recaps/2026-10', $this->auth())->assertOk()
            ->assertJsonPath('data.recap.totalPpn', '1100000.00')
            ->assertJsonPath('data.recap.totalPpnbm', '0.00');
        $this->getJson('/api/v1/tax-recaps/2026-12', $this->auth())->assertOk()
            ->assertJsonPath('data.recap.totalPpn', '108000000.00')
            ->assertJsonPath('data.recap.totalPpnbm', '675000000.00')
            ->assertJsonPath('data.invoices.0.isCounted', true);
        $this->getJson('/api/v1/tax/summary?taxPeriod=2026-12&projectId=prj-simulasi-yacht-001', $this->auth())
            ->assertOk()->assertJsonPath('data.totalPpnbm', '675000000.00');

        $path = tempnam(sys_get_temp_dir(), 'e1tax');
        try {
            $xlsx = $this->get('/api/v1/tax-recaps/2026-12/export?format=xlsx', $this->auth())->assertOk();
            file_put_contents($path, $xlsx->getContent());
            $book = IOFactory::load($path);
            $sheet = $book->getActiveSheet();
            $summaryRows = array_column($sheet->toArray(), 1, 0);
            $this->assertSame('108000000.00', $summaryRows['Total PPN']);
            $this->assertSame('675000000.00', $summaryRows['Total PPnBM']);
            $this->assertSame('675000000.00', $sheet->getCell('I17')->getValue());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $pdf = $this->get('/api/v1/tax-recaps/2026-12/export?format=pdf', $this->auth())->assertOk();
        $text = (new Parser)->parseContent($pdf->getContent())->getText();
        $this->assertStringContainsString('Rp 108.000.000,00', $text);
        $this->assertStringContainsString('Rp 675.000.000,00', $text);
        $this->patchJson('/api/v1/tax-invoices/'.$replacement['taxInvoiceId'], ['changes' => ['status' => 'CANCELLED']], $this->auth())->assertOk();
        $this->getJson('/api/v1/tax-recaps/2026-12', $this->auth())->assertOk()
            ->assertJsonPath('data.recap.totalPpn', '0.00')->assertJsonPath('data.recap.totalPpnbm', '0.00');
        $this->getJson($summary, $this->auth())->assertOk()
            ->assertJsonPath('data.totalPpn', '121100000.25')->assertJsonPath('data.totalPpnbm', '750000000.75');
        $this->patchJson($uri, ['changes' => ['status' => 'CANCELLED']], $this->auth())->assertOk();
        $this->getJson($summary, $this->auth())->assertOk()
            ->assertJsonPath('data.invoiceCount', 1)
            ->assertJsonPath('data.totalPpn', '1100000.00')->assertJsonPath('data.totalPpnbm', '0.00');
    }

    public static function invalidTaxAmounts(): array
    {
        return [
            'DPP negatif' => [['dpp' => '-100.000.000,00']],
            'PPN negatif' => [['ppn' => '-12.000.000,00']],
            'PPnBM negatif' => [['ppnbm' => '-75.000.000,00']],
            'PPnBM tidak terbaca' => [['ppnbm' => 'tidak terbaca']],
            'PPnBM melebihi kapasitas' => [['ppnbm' => '10.000.000.000.000.000,00']],
            'PPnBM tiga angka desimal' => [['ppnbm' => '75.000.000,001']],
            'PPN pemisah rusak' => [['ppn' => '12.00.0']],
            'PPN berakhiran teks' => [['ppn' => '12000abc']],
        ];
    }

    #[DataProvider('invalidTaxAmounts')]
    public function test_nominal_pajak_rusak_ditolak_sebelum_pratinjau_atau_penyimpanan(array $amounts): void
    {
        $binary = TaxPdf::make($amounts);
        foreach (['extractions', 'outgoing'] as $action) {
            $this->post('/api/v1/tax-invoices/'.$action, [
                'invoiceId' => 'inv-demo-001', 'eFakturFile' => $this->pdfUpload($binary),
            ], $this->auth())->assertStatus(400)->assertJsonPath('errorCode', 'EXTRACTION_FAILED');
        }
        foreach (['faktur_pajak', 'item_faktur', 'riwayat_perubahan_faktur', 'rekap_pajak_bulanan'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
