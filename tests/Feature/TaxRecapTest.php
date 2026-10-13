<?php

namespace Tests\Feature;

use Tests\TestCase;

/** API-KE01-07: rekap per masa pajak. */
class TaxRecapTest extends TestCase
{
    public function test_rekap_hanya_menjumlahkan_faktur_aktif_dan_menandai_is_counted(): void
    {
        $a = $this->createFaktur('inv-demo-001', ['nsfp' => '010.000-26.00000501']);                                    // normal, dihitung
        $b = $this->createFaktur('inv-demo-002', ['nsfp' => '010.000-26.00000502', 'dpp' => '250000000.00', 'ppn' => '27500000.00']); // akan diganti
        $c = $this->createFaktur('inv-demo-003', ['nsfp' => '010.000-26.00000503', 'dpp' => '75000000.00', 'ppn' => '8250000.00']);  // akan dibatalkan
        $b2 = $this->createFaktur('inv-demo-002', ['nsfp' => '010.000-26.00000504', 'dpp' => '200000000.00', 'ppn' => '22000000.00'], $b['taxInvoiceId']);
        $this->patchJson('/api/v1/tax-invoices/'.$c['taxInvoiceId'], ['changes' => ['status' => 'CANCELLED']], $this->auth())->assertOk();

        $response = $this->getJson('/api/v1/tax-recaps/2026-11', $this->auth());

        $response->assertOk()
            ->assertJsonPath('code', '200')
            ->assertJsonPath('message', 'Rekap masa pajak')
            ->assertJsonPath('data.recap.taxPeriod', '2026-11')
            ->assertJsonPath('data.recap.invoiceCount', 2)
            ->assertJsonPath('data.recap.totalDpp', '300000000.00')
            ->assertJsonPath('data.recap.totalPpn', '33000000.00')
            ->assertJsonPath('data.recap.totalPpnbm', '0.00')
            ->assertJsonPath('data.recap.amountPaid', '0.00')
            ->assertJsonPath('data.recap.paymentDifference', '-33000000.00')
            ->assertJsonPath('data.recap.paymentStatus', 'NOT_RECORDED');

        // seluruh faktur periode tampil; hanya a dan b2 yang dihitung
        $counted = collect($response->json('data.invoices'))->pluck('isCounted', 'taxInvoiceId')->all();
        $this->assertSame([
            $a['taxInvoiceId'] => true,
            $b['taxInvoiceId'] => false,
            $c['taxInvoiceId'] => false,
            $b2['taxInvoiceId'] => true,
        ], $counted);

        $statuses = collect($response->json('data.invoices'))->pluck('status', 'taxInvoiceId')->all();
        $this->assertSame('CANCELLED', $statuses[$c['taxInvoiceId']]);
        $this->assertSame('REPLACEMENT', $statuses[$b2['taxInvoiceId']]);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $response->json('data.recap.computedAt'));
    }

    public function test_rekap_membawa_pembayaran_selisih_dan_status(): void
    {
        $this->createFaktur('inv-demo-001'); // PPN 11.000.000
        $this->createPayment('2026-11', '10500000.00', ['ntpn' => 'NTPN-ABC123', 'paymentProofFile' => $this->pdfUpload($this->simplePdf(), 'bukti.pdf')]);

        $response = $this->getJson('/api/v1/tax-recaps/2026-11', $this->auth());

        $response->assertOk()
            ->assertJsonPath('data.recap.amountPaid', '10500000.00')
            ->assertJsonPath('data.recap.paymentDifference', '-500000.00')
            ->assertJsonPath('data.recap.paymentStatus', 'CONFIRMED')
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.ntpn', 'NTPN-ABC123')
            ->assertJsonPath('data.payments.0.status', 'CONFIRMED');
    }

    public function test_masa_pajak_tanpa_faktur_menghasilkan_rekap_kosong_bukan_error(): void
    {
        $this->getJson('/api/v1/tax-recaps/2026-01', $this->auth())
            ->assertOk()
            ->assertJsonPath('data.recap.invoiceCount', 0)
            ->assertJsonPath('data.recap.totalDpp', '0.00')
            ->assertJsonPath('data.recap.totalPpn', '0.00')
            ->assertJsonPath('data.recap.totalPpnbm', '0.00')
            ->assertJsonPath('data.recap.amountPaid', '0.00')
            ->assertJsonPath('data.recap.paymentDifference', '0.00')
            ->assertJsonPath('data.recap.paymentStatus', 'NOT_RECORDED')
            ->assertJsonPath('data.invoices', [])
            ->assertJsonPath('data.payments', []);

        // tidak membuat data fiktif
        $this->assertDatabaseCount('rekap_pajak_bulanan', 0);
    }

    public function test_masa_pajak_tidak_valid_400(): void
    {
        foreach (['2026-13', '2026-00', '2026-1', 'abc', '202611', '2026-11-01'] as $period) {
            $this->getJson('/api/v1/tax-recaps/'.$period, $this->auth())
                ->assertStatus(400)
                ->assertExactJson([
                    'code' => '400',
                    'message' => 'Masa pajak harus berformat YYYY-MM (contoh 2026-11)',
                    'data' => null,
                    'errorCode' => 'INVALID_TAX_PERIOD',
                ]);
        }
    }

    public function test_faktur_masa_pajak_lain_tidak_ikut_dihitung(): void
    {
        $this->createFaktur('inv-demo-001', ['date' => '1 November 2026']);
        $this->createFaktur('inv-demo-004', ['date' => '2 Desember 2026', 'dpp' => '120000000.00', 'ppn' => '13200000.00']);

        $this->getJson('/api/v1/tax-recaps/2026-12', $this->auth())
            ->assertOk()
            ->assertJsonPath('data.recap.invoiceCount', 1)
            ->assertJsonPath('data.recap.totalPpn', '13200000.00');
    }
}
