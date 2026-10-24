<?php

namespace Tests\Feature;

use App\Models\FakturPajak;
use App\Models\RiwayatPerubahanFaktur;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** API-KE01-02: simpan faktur keluaran atau faktur pengganti. */
class TaxInvoiceCreateTest extends TestCase
{
    private const URI = '/api/v1/tax-invoices/outgoing';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Tests\Support\InvoiceScenarioSeeder::class);
    }

    public function test_faktur_keluaran_tersimpan_dengan_riwayat_dan_rekap(): void
    {
        $response = $this->post(self::URI, [
            'invoiceId' => 'inv-demo-001',
            'eFakturFile' => $this->pdfUpload($this->efakturPdf(['nsfp' => '010.000-26.12345678'])),
        ], $this->auth('Admin', 'AKN-0002'));

        $response->assertOk()
            ->assertJsonPath('code', '200')
            ->assertJsonPath('message', 'Faktur tersimpan')
            ->assertJsonPath('data.invoiceId', 'inv-demo-001')
            ->assertJsonPath('data.status', 'NORMAL')
            ->assertJsonPath('data.replacesTaxInvoiceId', null)
            ->assertJsonPath('data.taxInvoiceNumber', '010.000-26.12345678')
            ->assertJsonPath('data.taxInvoiceDate', '2026-11-01')
            ->assertJsonPath('data.taxPeriod', '2026-11')
            ->assertJsonPath('data.dpp', '100000000.00')
            ->assertJsonPath('data.ppn', '11000000.00')
            ->assertJsonPath('data.ppnbm', '0.00')
            ->assertJsonPath('data.createdByAkunId', 'AKN-0002')
            ->assertJsonCount(1, 'data.items');

        $data = $response->json('data');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $data['taxInvoiceId']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $data['createdAt']);

        // data rujukan dari invoice D-2
        $faktur = FakturPajak::query()->findOrFail($data['taxInvoiceId']);
        $this->assertSame('OUTGOING', $faktur->type);
        $this->assertSame('prj-demo-001', $faktur->project_id);
        $this->assertNull($faktur->vendor_name);

        // PDF tersimpan
        $this->assertStringStartsWith('/files/tax/e-faktur/', $faktur->pdf_uri);
        Storage::disk('local')->assertExists(ltrim(substr($faktur->pdf_uri, strlen('/files/')), '/'));

        // riwayat CREATE
        $history = RiwayatPerubahanFaktur::query()->where('tax_invoice_id', $faktur->tax_invoice_id)->get();
        $this->assertCount(1, $history);
        $this->assertSame('CREATE', $history[0]->action);
        $this->assertSame('AKN-0002', $history[0]->actor_akun_id);
        $this->assertNull($history[0]->before_snapshot);
        $this->assertSame('010.000-26.12345678', $history[0]->after_snapshot['taxInvoiceNumber']);

        // rekap masa pajak dibentuk
        $this->assertDatabaseHas('rekap_pajak_bulanan', [
            'tax_period' => '2026-11', 'invoice_count' => 1, 'total_ppn' => '11000000.00', 'payment_status' => 'NOT_RECORDED',
        ]);
    }

    public function test_faktur_pengganti_menautkan_dan_menonaktifkan_faktur_asal(): void
    {
        $original = $this->createFaktur('inv-demo-001', ['nsfp' => '010.000-26.00000001']);

        $replacement = $this->createFaktur('inv-demo-001', [
            'nsfp' => '010.001-26.00000002', 'date' => '10 November 2026', 'dpp' => '90000000.00', 'ppn' => '9900000.00',
            'items' => [['code' => '620100', 'name' => 'Jasa Pengembangan (koreksi)', 'amount' => '90000000.00']],
        ], $original['taxInvoiceId']);

        $this->assertSame('REPLACEMENT', $replacement['status']);
        $this->assertSame($original['taxInvoiceId'], $replacement['replacesTaxInvoiceId']);

        // asal terganti dan tertaut; hanya pengganti yang dihitung
        $this->getJson('/api/v1/tax-invoices/'.$original['taxInvoiceId'], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.replacedByTaxInvoiceId', $replacement['taxInvoiceId']);

        $this->assertDatabaseHas('rekap_pajak_bulanan', [
            'tax_period' => '2026-11', 'invoice_count' => 1, 'total_dpp' => '90000000.00', 'total_ppn' => '9900000.00',
        ]);

        // riwayat faktur asal mencatat penggantian
        $originalHistory = RiwayatPerubahanFaktur::query()->where('tax_invoice_id', $original['taxInvoiceId'])->orderBy('changed_at')->get();
        $this->assertSame(['CREATE', 'STATUS_CHANGE'], $originalHistory->pluck('action')->all());
        $this->assertSame($replacement['taxInvoiceId'], $originalHistory[1]->after_snapshot['replacedByTaxInvoiceId']);
    }

    public function test_pengganti_harus_memakai_invoice_yang_sama_dengan_asal(): void
    {
        $original = $this->createFaktur('inv-demo-001');

        $this->post(self::URI, [
            'invoiceId' => 'inv-demo-002',
            'eFakturFile' => $this->pdfUpload($this->efakturPdf()),
            'replacesTaxInvoiceId' => $original['taxInvoiceId'],
        ], $this->auth())
            ->assertStatus(400)
            ->assertJsonPath('errorCode', 'REPLACEMENT_INVOICE_MISMATCH');
    }

    public function test_faktur_asal_tidak_dikenal_404(): void
    {
        foreach (['0191f3a0-7c1e-7a52-9d3b-4f6e2a1b8c99', 'bukan-uuid'] as $id) {
            $this->post(self::URI, [
                'invoiceId' => 'inv-demo-001',
                'eFakturFile' => $this->pdfUpload($this->efakturPdf()),
                'replacesTaxInvoiceId' => $id,
            ], $this->auth())
                ->assertStatus(404)->assertJsonPath('errorCode', 'TAX_INVOICE_NOT_FOUND');
        }
    }

    public function test_faktur_asal_yang_sudah_diganti_tidak_dapat_diganti_lagi(): void
    {
        $original = $this->createFaktur('inv-demo-001');
        $this->createFaktur('inv-demo-001', [], $original['taxInvoiceId']);

        $this->post(self::URI, [
            'invoiceId' => 'inv-demo-001',
            'eFakturFile' => $this->pdfUpload($this->efakturPdf()),
            'replacesTaxInvoiceId' => $original['taxInvoiceId'],
        ], $this->auth())
            ->assertStatus(409)->assertJsonPath('errorCode', 'INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE');
    }

    public function test_faktur_asal_yang_dibatalkan_tidak_dapat_diganti(): void
    {
        $original = $this->createFaktur('inv-demo-001');
        $this->patchJson('/api/v1/tax-invoices/'.$original['taxInvoiceId'], [
            'changes' => ['status' => 'CANCELLED'], 'note' => 'salah invoice',
        ], $this->auth())->assertOk();

        $this->post(self::URI, [
            'invoiceId' => 'inv-demo-001',
            'eFakturFile' => $this->pdfUpload($this->efakturPdf()),
            'replacesTaxInvoiceId' => $original['taxInvoiceId'],
        ], $this->auth())
            ->assertStatus(409)->assertJsonPath('errorCode', 'TAX_INVOICE_ALREADY_CANCELLED');
    }

    public function test_invoice_dengan_faktur_aktif_tidak_dapat_dicatat_ulang_tanpa_pengganti(): void
    {
        $this->createFaktur('inv-demo-001');

        $this->post(self::URI, [
            'invoiceId' => 'inv-demo-001',
            'eFakturFile' => $this->pdfUpload($this->efakturPdf()),
        ], $this->auth())
            ->assertStatus(409)->assertJsonPath('errorCode', 'INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE');

        $this->assertSame(1, FakturPajak::query()->count());
    }

    public function test_invoice_boleh_dicatat_lagi_setelah_fakturnya_dibatalkan(): void
    {
        $first = $this->createFaktur('inv-demo-001');
        $this->patchJson('/api/v1/tax-invoices/'.$first['taxInvoiceId'], ['changes' => ['status' => 'CANCELLED']], $this->auth())->assertOk();

        $second = $this->createFaktur('inv-demo-001');

        $this->assertSame('NORMAL', $second['status']);
        $this->assertSame(2, FakturPajak::query()->where('invoice_id', 'inv-demo-001')->count());
    }

    public function test_nsfp_ganda_409_dan_berkas_tidak_tertinggal(): void
    {
        $this->createFaktur('inv-demo-001', ['nsfp' => '010.000-26.00000555']);
        $filesBefore = count(Storage::disk('local')->allFiles());

        $this->post(self::URI, [
            'invoiceId' => 'inv-demo-002',
            'eFakturFile' => $this->pdfUpload($this->efakturPdf(['nsfp' => '010.000-26.00000555'])),
        ], $this->auth())
            ->assertStatus(409)->assertJsonPath('errorCode', 'DUPLICATE_NSFP');

        $this->assertSame($filesBefore, count(Storage::disk('local')->allFiles()));
        $this->assertSame(1, FakturPajak::query()->count());
    }

    public function test_invoice_tidak_dikenal_dan_berkas_salah(): void
    {
        $this->post(self::URI, [
            'invoiceId' => 'inv-tidak-ada',
            'eFakturFile' => $this->pdfUpload($this->efakturPdf()),
        ], $this->auth())->assertStatus(404)->assertJsonPath('errorCode', 'INVOICE_NOT_FOUND');

        $this->post(self::URI, [
            'invoiceId' => 'inv-demo-001',
            'eFakturFile' => $this->pdfUpload($this->simplePdf()),
        ], $this->auth())->assertStatus(400)->assertJsonPath('errorCode', 'EXTRACTION_FAILED');

        $this->assertSame(0, FakturPajak::query()->count());
    }

    public function test_banyak_item_dan_urutannya_dipertahankan(): void
    {
        $data = $this->createFaktur('inv-demo-002', [
            'items' => [
                ['code' => 'A-1', 'name' => 'Lisensi Aplikasi', 'amount' => '150000000.00'],
                ['code' => 'B-2', 'name' => 'Jasa Implementasi', 'amount' => '100000000.00'],
            ],
            'dpp' => '250000000.00', 'ppn' => '27500000.00',
        ]);

        $this->assertSame(['A-1', 'B-2'], array_column($data['items'], 'itemCode'));
    }
}
