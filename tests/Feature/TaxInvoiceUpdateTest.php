<?php

namespace Tests\Feature;

use App\Models\FakturPajak;
use App\Models\RiwayatPerubahanFaktur;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** API-KE01-05: koreksi atau batalkan faktur dengan riwayat. */
class TaxInvoiceUpdateTest extends TestCase
{
    private function koreksi(string $id, array $body, string $role = 'Admin', string $akun = 'AKN-0002')
    {
        return $this->patchJson('/api/v1/tax-invoices/'.$id, $body, $this->auth($role, $akun));
    }

    public function test_koreksi_tanggal_tersimpan_dengan_riwayat_sebelum_sesudah(): void
    {
        $faktur = $this->createFaktur('inv-demo-001');

        $response = $this->koreksi($faktur['taxInvoiceId'], [
            'changes' => ['taxInvoiceDate' => '2026-11-10'],
            'note' => 'Koreksi tanggal faktur',
        ], 'COO', 'AKN-0005');

        $response->assertOk()
            ->assertJsonPath('code', '200')
            ->assertJsonPath('message', 'Faktur diperbarui')
            ->assertJsonPath('data.taxInvoice.taxInvoiceDate', '2026-11-10')
            ->assertJsonPath('data.taxInvoice.status', 'NORMAL');

        $audit = RiwayatPerubahanFaktur::query()->findOrFail($response->json('data.auditId'));
        $this->assertSame('UPDATE', $audit->action);
        $this->assertSame('AKN-0005', $audit->actor_akun_id);
        $this->assertSame(['taxInvoiceDate' => '2026-11-01'], $audit->before_snapshot);
        $this->assertSame(['taxInvoiceDate' => '2026-11-10'], $audit->after_snapshot);
        $this->assertSame('Koreksi tanggal faktur', $audit->note);
    }

    public function test_koreksi_nominal_memperbarui_rekap(): void
    {
        $faktur = $this->createFaktur('inv-demo-001');

        $this->koreksi($faktur['taxInvoiceId'], ['changes' => ['dpp' => '90000000.00', 'ppn' => '9900000', 'ppnbm' => '1500.5']])
            ->assertOk()
            ->assertJsonPath('data.taxInvoice.dpp', '90000000.00')
            ->assertJsonPath('data.taxInvoice.ppn', '9900000.00')
            ->assertJsonPath('data.taxInvoice.ppnbm', '1500.50');

        $this->getJson('/api/v1/tax-recaps/2026-11', $this->auth())
            ->assertOk()
            ->assertJsonPath('data.recap.totalDpp', '90000000.00')
            ->assertJsonPath('data.recap.totalPpn', '9900000.00')
            ->assertJsonPath('data.recap.totalPpnbm', '1500.50');
    }

    public function test_koreksi_masa_pajak_menghitung_ulang_kedua_periode(): void
    {
        $faktur = $this->createFaktur('inv-demo-001');

        $this->koreksi($faktur['taxInvoiceId'], ['changes' => ['taxInvoiceDate' => '2026-12-01']])->assertOk()
            ->assertJsonPath('data.taxInvoice.taxPeriod', '2026-12');

        $this->assertDatabaseHas('rekap_pajak_bulanan', ['tax_period' => '2026-11', 'invoice_count' => 0, 'total_ppn' => '0.00']);
        $this->assertDatabaseHas('rekap_pajak_bulanan', ['tax_period' => '2026-12', 'invoice_count' => 1, 'total_ppn' => '11000000.00']);
    }

    public function test_masa_pajak_tidak_boleh_berbeda_dari_tanggal_faktur(): void
    {
        $faktur = $this->createFaktur();
        foreach ([['taxPeriod' => '2026-12'], ['taxInvoiceDate' => '2026-12-01', 'taxPeriod' => '2026-11']] as $changes) {
            $this->koreksi($faktur['taxInvoiceId'], ['changes' => $changes])
                ->assertBadRequest()->assertJsonPath('errorCode', 'INVALID_CHANGE');
        }
        $this->assertDatabaseHas('faktur_pajak', ['tax_invoice_id' => $faktur['taxInvoiceId'], 'tax_period' => '2026-11']);
        $this->assertSame(1, RiwayatPerubahanFaktur::where('tax_invoice_id', $faktur['taxInvoiceId'])->count());
    }

    public function test_koreksi_lintas_tahun_mencatat_periode_otomatis_dan_menjaga_pembayaran_lama(): void
    {
        $faktur = $this->createFaktur();
        $this->post('/api/v1/tax-payments', [
            'taxPeriod' => '2026-11', 'taxYear' => 2026, 'amountPaid' => '11000000.00',
            'billingId' => 'BILL-KOREKSI', 'eBillingFile' => $this->pdfUpload($this->simplePdf()),
        ], $this->auth())->assertOk();
        $response = $this->koreksi($faktur['taxInvoiceId'], ['changes' => ['taxInvoiceDate' => '2027-01-02']])->assertOk()
            ->assertJsonPath('data.taxInvoice.taxPeriod', '2027-01');
        $audit = RiwayatPerubahanFaktur::findOrFail($response->json('data.auditId'));
        $this->assertSame('2026-11', $audit->before_snapshot['taxPeriod']);
        $this->assertSame('2027-01', $audit->after_snapshot['taxPeriod']);
        $this->assertDatabaseHas('rekap_pajak_bulanan', ['tax_period' => '2026-11', 'invoice_count' => 0, 'total_ppn' => '0.00', 'amount_paid' => '11000000.00']);
        $this->assertDatabaseHas('rekap_pajak_bulanan', ['tax_period' => '2027-01', 'invoice_count' => 1, 'total_ppn' => '11000000.00', 'amount_paid' => '0.00']);
    }

    public function test_audit_periode_lama_hanya_melaporkan_tanpa_mengubah_data(): void
    {
        $faktur = $this->createFaktur();
        $this->artisan('tax:audit-periods')->assertSuccessful();
        DB::table('faktur_pajak')->where('tax_invoice_id', $faktur['taxInvoiceId'])->update(['tax_period' => '2026-12']);
        $this->artisan('tax:audit-periods')->expectsOutputToContain('Ditemukan 1 faktur')->assertFailed();
        $this->assertDatabaseHas('faktur_pajak', ['tax_invoice_id' => $faktur['taxInvoiceId'], 'tax_period' => '2026-12']);
    }

    public function test_koreksi_item_mengganti_seluruh_daftar_dan_tercatat(): void
    {
        $faktur = $this->createFaktur('inv-demo-001');

        $response = $this->koreksi($faktur['taxInvoiceId'], ['changes' => ['items' => [
            ['itemCode' => 'X-1', 'itemName' => 'Jasa A', 'saleReplacementAdvanceTermAmount' => '60000000.00'],
            ['itemCode' => 'X-2', 'itemName' => 'Jasa B', 'saleReplacementAdvanceTermAmount' => '40000000.00', 'discountAmount' => '1000.00'],
        ]]])->assertOk();

        $detail = $this->getJson('/api/v1/tax-invoices/'.$faktur['taxInvoiceId'], $this->auth())->json('data.items');
        $this->assertSame(['X-1', 'X-2'], array_column($detail, 'itemCode'));
        $this->assertSame('0.00', $detail[0]['discountAmount']);
        $this->assertSame('1000.00', $detail[1]['discountAmount']);

        $audit = RiwayatPerubahanFaktur::query()->findOrFail($response->json('data.auditId'));
        $this->assertSame('620100', $audit->before_snapshot['items'][0]['itemCode']);
        $this->assertSame('X-1', $audit->after_snapshot['items'][0]['itemCode']);
    }

    public function test_pembatalan_mengubah_status_dan_mengeluarkan_faktur_dari_rekap(): void
    {
        $a = $this->createFaktur('inv-demo-001');
        $b = $this->createFaktur('inv-demo-002', ['dpp' => '250000000.00', 'ppn' => '27500000.00']);

        $response = $this->koreksi($a['taxInvoiceId'], ['changes' => ['status' => 'CANCELLED'], 'note' => 'Invoice salah']);

        $response->assertOk()->assertJsonPath('data.taxInvoice.status', 'CANCELLED');

        $audit = RiwayatPerubahanFaktur::query()->findOrFail($response->json('data.auditId'));
        $this->assertSame('STATUS_CHANGE', $audit->action);
        $this->assertSame(['status' => 'NORMAL'], $audit->before_snapshot);
        $this->assertSame(['status' => 'CANCELLED'], $audit->after_snapshot);

        $this->getJson('/api/v1/tax-recaps/2026-11', $this->auth())
            ->assertOk()
            ->assertJsonPath('data.recap.invoiceCount', 1)
            ->assertJsonPath('data.recap.totalPpn', '27500000.00');

        // tidak ada hard delete: faktur dan riwayat tetap ada
        $this->assertDatabaseHas('faktur_pajak', ['tax_invoice_id' => $a['taxInvoiceId'], 'status' => 'CANCELLED']);
        $this->assertSame(2, RiwayatPerubahanFaktur::query()->where('tax_invoice_id', $a['taxInvoiceId'])->count());
        $this->assertNotNull($b);
    }

    public function test_membatalkan_faktur_pengganti_mengaktifkan_kembali_faktur_asal(): void
    {
        $original = $this->createFaktur('inv-demo-001', ['nsfp' => '010.000-26.00000301']);
        $replacement = $this->createFaktur('inv-demo-001', ['nsfp' => '010.000-26.00000302', 'dpp' => '90000000.00', 'ppn' => '9900000.00'], $original['taxInvoiceId']);

        $this->koreksi($replacement['taxInvoiceId'], ['changes' => ['status' => 'CANCELLED'], 'note' => 'Pengganti dibatalkan'])->assertOk();

        // tautan dilepas (03: replacesTaxInvoiceId hanya untuk REPLACEMENT) dan asal berlaku lagi
        $this->assertNull(FakturPajak::query()->find($replacement['taxInvoiceId'])->replaces_tax_invoice_id);
        $this->getJson('/api/v1/tax-invoices/'.$original['taxInvoiceId'], $this->auth())
            ->assertJsonPath('data.replacedByTaxInvoiceId', null);

        $this->assertDatabaseHas('rekap_pajak_bulanan', ['tax_period' => '2026-11', 'invoice_count' => 1, 'total_ppn' => '11000000.00']);

        $replacementHistory = RiwayatPerubahanFaktur::query()->where('tax_invoice_id', $replacement['taxInvoiceId'])->where('action', 'STATUS_CHANGE')->get();
        $this->assertSame($original['taxInvoiceId'], $replacementHistory->last()->before_snapshot['replacesTaxInvoiceId']);
        $this->assertNull($replacementHistory->last()->after_snapshot['replacesTaxInvoiceId']);
    }

    public function test_faktur_dibatalkan_tidak_dapat_diubah_atau_dibatalkan_lagi(): void
    {
        $faktur = $this->createFaktur('inv-demo-001');
        $this->koreksi($faktur['taxInvoiceId'], ['changes' => ['status' => 'CANCELLED']])->assertOk();

        foreach ([['status' => 'CANCELLED'], ['dpp' => '1.00']] as $changes) {
            $this->koreksi($faktur['taxInvoiceId'], ['changes' => $changes])
                ->assertStatus(409)
                ->assertExactJson([
                    'code' => '409',
                    'message' => 'Faktur sudah berstatus Dibatalkan',
                    'data' => null,
                    'errorCode' => 'TAX_INVOICE_ALREADY_CANCELLED',
                ]);
        }
    }

    public function test_nsfp_hasil_koreksi_tidak_boleh_sama_dengan_faktur_lain(): void
    {
        $a = $this->createFaktur('inv-demo-001', ['nsfp' => '010.000-26.00000401']);
        $this->createFaktur('inv-demo-002', ['nsfp' => '010.000-26.00000402']);

        $this->koreksi($a['taxInvoiceId'], ['changes' => ['taxInvoiceNumber' => '010.000-26.00000402']])
            ->assertStatus(409)->assertJsonPath('errorCode', 'DUPLICATE_NSFP');

        $this->assertSame('010.000-26.00000401', FakturPajak::query()->find($a['taxInvoiceId'])->tax_invoice_number);
    }

    public function test_perubahan_tidak_valid_ditolak_400_tanpa_mengubah_data(): void
    {
        $faktur = $this->createFaktur('inv-demo-001');
        $id = $faktur['taxInvoiceId'];

        $invalid = [
            'objek kosong' => [],
            'field tidak diizinkan (invoiceId)' => ['invoiceId' => 'inv-demo-002'],
            'field tidak diizinkan (createdByAkunId)' => ['createdByAkunId' => 'AKN-9999'],
            'status selain CANCELLED' => ['status' => 'NORMAL'],
            'pembatalan digabung koreksi' => ['status' => 'CANCELLED', 'dpp' => '1.00'],
            'dpp berupa angka JSON' => ['dpp' => 90000000],
            'dpp negatif' => ['dpp' => '-5.00'],
            'dpp bukan angka' => ['dpp' => 'seratus'],
            'ppn tiga desimal' => ['ppn' => '1.234'],
            'masa pajak salah' => ['taxPeriod' => '2026-13'],
            'tanggal tidak ada' => ['taxInvoiceDate' => '2026-02-30'],
            'tanggal format salah' => ['taxInvoiceDate' => '01/11/2026'],
            'nsfp kosong' => ['taxInvoiceNumber' => '  '],
            'items kosong' => ['items' => []],
            'item tanpa kode' => ['items' => [['itemName' => 'A', 'saleReplacementAdvanceTermAmount' => '1.00']]],
            'tanpa perubahan nyata' => ['dpp' => '100000000.00'],
        ];

        foreach ($invalid as $label => $changes) {
            $response = $this->koreksi($id, ['changes' => $changes]);
            $this->assertSame(400, $response->status(), $label);
            $this->assertSame('INVALID_CHANGE', $response->json('errorCode'), $label);
        }

        $this->koreksi($id, ['note' => 'tanpa changes'])->assertStatus(400)->assertJsonPath('errorCode', 'INVALID_CHANGE');
        $this->koreksi($id, ['changes' => ['dpp' => '1.00'], 'note' => str_repeat('x', 501)])->assertStatus(400);

        $this->assertSame(1, RiwayatPerubahanFaktur::query()->where('tax_invoice_id', $id)->count());
        $this->assertSame('100000000.00', FakturPajak::query()->find($id)->dpp);
    }

    public function test_faktur_tidak_dikenal_404_didahulukan_dari_validasi(): void
    {
        $this->koreksi('0191f3a0-7c1e-7a52-9d3b-4f6e2a1b8c99', ['changes' => ['invoiceId' => 'x']])
            ->assertStatus(404)->assertJsonPath('errorCode', 'TAX_INVOICE_NOT_FOUND');
    }

    public function test_peran_tidak_berwenang_403(): void
    {
        $faktur = $this->createFaktur('inv-demo-001');

        $this->koreksi($faktur['taxInvoiceId'], ['changes' => ['dpp' => '1.00']], 'Karyawan')
            ->assertStatus(403)->assertJsonPath('errorCode', 'FORBIDDEN');
    }

    public function test_riwayat_bersifat_append_only_di_basis_data(): void
    {
        $faktur = $this->createFaktur('inv-demo-001');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('append-only');

        DB::table('riwayat_perubahan_faktur')->where('tax_invoice_id', $faktur['taxInvoiceId'])->update(['note' => 'dirusak']);
    }
}
