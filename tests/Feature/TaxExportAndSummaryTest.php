<?php

namespace Tests\Feature;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

/** API-KE01-09 (ekspor rekap) dan API-KE01-10 (ringkasan pajak untuk modul lain). */
class TaxExportAndSummaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createFaktur('inv-demo-001', ['nsfp' => '010.000-26.00000601']);                                                               // prj-demo-001
        $this->createFaktur('inv-demo-002', ['nsfp' => '010.000-26.00000602', 'dpp' => '250000000.00', 'ppn' => '27500000.00']);            // prj-demo-002
        $cancelled = $this->createFaktur('inv-demo-003', ['nsfp' => '010.000-26.00000603', 'dpp' => '75000000.00', 'ppn' => '8250000.00']);  // prj-demo-002
        $this->patchJson('/api/v1/tax-invoices/'.$cancelled['taxInvoiceId'], ['changes' => ['status' => 'CANCELLED']], $this->auth())->assertOk();
    }

    // ---------------- API-KE01-09 ----------------

    public function test_ekspor_excel_identik_dengan_rekap(): void
    {
        $response = $this->get('/api/v1/tax-recaps/2026-11/export?format=xlsx', $this->auth());

        $response->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="rekap-pajak-2026-11.xlsx"', $response->headers->get('Content-Disposition'));

        $path = tempnam(sys_get_temp_dir(), 'rekap').'.xlsx';
        file_put_contents($path, $response->getContent());
        $book = IOFactory::load($path);
        unlink($path);

        $this->assertSame(['Rekap Pajak'], $book->getSheetNames());
        $sheet = $book->getActiveSheet();
        $rows = $sheet->toArray(null, true, false);
        $this->assertSame('Rekap Pajak Masa November 2026', $rows[0][0]);
        $this->assertSame('landscape', $sheet->getPageSetup()->getOrientation());
        $this->assertSame(1, $sheet->getPageSetup()->getFitToWidth());

        $summary = [];
        foreach ($rows as $row) {
            $summary[$row[0]] = $row[1];
        }
        $this->assertSame('350000000.00', $summary['Total DPP']);
        $this->assertSame('38500000.00', $summary['Total PPN']);
        $this->assertSame('2', (string) $summary['Jumlah faktur dihitung']);
        $this->assertSame('Belum dicatat', $summary['Status pembayaran']);
        $this->assertSame('November 2026', $summary['Masa pajak']);
        $this->assertStringContainsString(' WIB', $summary['Dihitung pada']);

        $invoiceHeader = array_search('NSFP', array_column($rows, 0), true);
        $invoices = array_slice($rows, $invoiceHeader, 4);
        $this->assertCount(4, $invoices);   // judul kolom + 3 faktur (termasuk yang dibatalkan)
        $this->assertSame('Dihitung', $invoices[0][9]);
        $this->assertSame('1 November 2026', $invoices[1][1]);
        $this->assertSame(['Ya', 'Ya', 'Tidak'], array_column(array_slice($invoices, 1), 9));
        $this->assertGreaterThan($invoiceHeader + 3, array_search('Pembayaran', array_column($rows, 0), true));
        $this->assertContains('Belum ada pembayaran dicatat.', array_column($rows, 0));
    }

    public function test_ekspor_pdf(): void
    {
        $response = $this->get('/api/v1/tax-recaps/2026-11/export?format=pdf', $this->auth());

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="rekap-pajak-2026-11.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_ekspor_pdf_memuat_angka_yang_sama_dengan_rekap(): void
    {
        $content = $this->get('/api/v1/tax-recaps/2026-11/export?format=pdf', $this->auth())->getContent();
        $text = (new Parser)->parseContent($content)->getText();

        $this->assertStringContainsString('Rekap Pajak Masa November 2026', $text);
        $this->assertStringContainsString('1 November 2026', $text);
        $this->assertStringContainsString('WIB', $text);
        $this->assertStringContainsString('Rp 38.500.000,00', $text);   // total PPN faktur aktif
        $this->assertStringContainsString('Dibatalkan', $text);
        $this->assertStringContainsString('010.000-26.00000603', $text);
    }

    public function test_format_selain_xlsx_pdf_ditolak(): void
    {
        foreach (['csv', 'XLSX', 'docx', ''] as $format) {
            $this->get('/api/v1/tax-recaps/2026-11/export?format='.$format, $this->auth())
                ->assertStatus(400)
                ->assertExactJson([
                    'code' => '400',
                    'message' => 'Format ekspor hanya xlsx atau pdf',
                    'data' => null,
                    'errorCode' => 'INVALID_FORMAT',
                ]);
        }

        $this->get('/api/v1/tax-recaps/2026-11/export', $this->auth())->assertStatus(400)->assertJsonPath('errorCode', 'INVALID_FORMAT');
    }

    public function test_masa_pajak_tidak_valid_pada_ekspor_400(): void
    {
        $this->get('/api/v1/tax-recaps/2026-13/export?format=pdf', $this->auth())
            ->assertStatus(400)->assertJsonPath('errorCode', 'INVALID_TAX_PERIOD');
    }

    public function test_ekspor_masa_pajak_kosong_menghasilkan_berkas_kosong_bukan_error(): void
    {
        $this->get('/api/v1/tax-recaps/2026-01/export?format=xlsx', $this->auth())->assertOk();
        $this->get('/api/v1/tax-recaps/2026-01/export?format=pdf', $this->auth())->assertOk();
    }

    public function test_ekspor_ditolak_untuk_peran_tidak_berwenang_tanpa_menghasilkan_berkas(): void
    {
        $response = $this->get('/api/v1/tax-recaps/2026-11/export?format=pdf', $this->auth('Karyawan'));

        $response->assertStatus(403)->assertJsonPath('errorCode', 'FORBIDDEN');
        $this->assertNotSame('application/pdf', $response->headers->get('Content-Type'));
    }

    // ---------------- API-KE01-10 ----------------

    public function test_ringkasan_per_masa_pajak_hanya_faktur_aktif(): void
    {
        $this->getJson('/api/v1/tax/summary?taxPeriod=2026-11', $this->auth())
            ->assertOk()
            ->assertExactJson([
                'code' => '200',
                'message' => 'Ringkasan pajak',
                'data' => [
                    'taxPeriod' => '2026-11',
                    'invoiceCount' => 2,
                    'totalDpp' => '350000000.00',
                    'totalPpn' => '38500000.00',
                    'totalPpnbm' => '0.00',
                    'paymentStatus' => 'NOT_RECORDED',
                ],
            ]);
    }

    public function test_ringkasan_per_proyek(): void
    {
        $this->getJson('/api/v1/tax/summary?taxPeriod=2026-11&projectId=prj-demo-002', $this->auth())
            ->assertOk()
            ->assertJsonPath('data.invoiceCount', 1)         // faktur prj-demo-002 yang dibatalkan tidak dihitung
            ->assertJsonPath('data.totalDpp', '250000000.00')
            ->assertJsonPath('data.totalPpn', '27500000.00');

        $this->getJson('/api/v1/tax/summary?taxPeriod=2026-11&projectId=prj-demo-001', $this->auth())
            ->assertOk()->assertJsonPath('data.totalPpn', '11000000.00');
    }

    public function test_ringkasan_mengikuti_status_pembayaran_dan_tidak_membocorkan_data_internal(): void
    {
        $this->createPayment('2026-11', '38500000.00', ['ntpn' => 'NTPN-RAHASIA', 'paymentProofFile' => $this->pdfUpload($this->simplePdf())]);

        $response = $this->getJson('/api/v1/tax/summary?taxPeriod=2026-11', $this->auth())->assertOk();

        $response->assertJsonPath('data.paymentStatus', 'CONFIRMED');
        $this->assertEqualsCanonicalizing(
            ['taxPeriod', 'invoiceCount', 'totalDpp', 'totalPpn', 'totalPpnbm', 'paymentStatus'],
            array_keys($response->json('data')),
        );
        $this->assertStringNotContainsString('NTPN-RAHASIA', $response->getContent());
        $this->assertStringNotContainsString('BILL-', $response->getContent());
        $this->assertStringNotContainsString('/files/', $response->getContent());
    }

    public function test_masa_pajak_tanpa_data_menghasilkan_nol_bukan_angka_karangan(): void
    {
        $this->getJson('/api/v1/tax/summary?taxPeriod=2025-01', $this->auth())
            ->assertOk()
            ->assertJsonPath('data.invoiceCount', 0)
            ->assertJsonPath('data.totalDpp', '0.00')
            ->assertJsonPath('data.paymentStatus', 'NOT_RECORDED');
    }

    public function test_parameter_tidak_valid_400(): void
    {
        foreach (['', '?taxPeriod=2026-13', '?taxPeriod=November'] as $query) {
            $this->getJson('/api/v1/tax/summary'.$query, $this->auth())
                ->assertStatus(400)->assertJsonPath('errorCode', 'INVALID_TAX_PERIOD');
        }

        foreach (['prj-tidak-ada', 'prj demo', '../etc', str_repeat('a', 101)] as $project) {
            $this->getJson('/api/v1/tax/summary?'.http_build_query(['taxPeriod' => '2026-11', 'projectId' => $project]), $this->auth())
                ->assertStatus(400)
                ->assertJsonPath('errorCode', 'INVALID_PROJECT_ID')
                ->assertJsonPath('message', 'projectId tidak valid');
        }
    }

    public function test_konsumen_tidak_berwenang_403_dan_peran_layanan_diizinkan(): void
    {
        $this->getJson('/api/v1/tax/summary?taxPeriod=2026-11', $this->auth('Karyawan'))
            ->assertStatus(403)
            ->assertExactJson([
                'code' => '403',
                'message' => 'Pengguna sah, akses tidak cukup',
                'data' => null,
                'errorCode' => 'FORBIDDEN',
            ]);

        $this->getJson('/api/v1/tax/summary?taxPeriod=2026-11', $this->auth('LayananE2'))->assertOk();
        $this->getJson('/api/v1/tax/summary?taxPeriod=2026-11')->assertStatus(401);
    }
}
