<?php

namespace Tests\Feature;

use App\Models\PembayaranPajak;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** API-KE01-08 (catat pembayaran) dan API-KE01-11 (lengkapi menjadi Terkonfirmasi). */
class TaxPaymentTest extends TestCase
{
    private const URI = '/api/v1/tax-payments';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFaktur('inv-demo-001'); // masa pajak 2026-11, PPN 11.000.000,00
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'taxPeriod' => '2026-11',
            'taxYear' => 2026,
            'amountPaid' => '11000000.00',
            'billingId' => 'BILL-202611-001',
            'eBillingFile' => $this->pdfUpload($this->simplePdf(), 'ebilling.pdf'),
        ], $override);
    }

    // ---------------- API-KE01-08 ----------------

    public function test_pembayaran_tanpa_ntpn_dan_bukti_tersimpan_sebagai_draft_incomplete(): void
    {
        $response = $this->post(self::URI, $this->payload(), $this->auth());

        $response->assertOk()
            ->assertJsonPath('code', '200')
            ->assertJsonPath('message', 'Pembayaran tersimpan')
            ->assertJsonPath('data.status', 'INCOMPLETE')
            ->assertJsonPath('data.taxPeriod', '2026-11')
            ->assertJsonPath('data.paymentDifference', '0.00');

        $payment = PembayaranPajak::query()->findOrFail($response->json('data.taxPaymentId'));
        $this->assertNull($payment->ntpn);
        $this->assertNull($payment->payment_proof_uri);
        $this->assertSame('AKN-0002', $payment->created_by_akun_id);
        Storage::disk('local')->assertExists(ltrim(substr($payment->e_billing_uri, strlen('/files/')), '/'));

        $this->assertDatabaseHas('rekap_pajak_bulanan', ['tax_period' => '2026-11', 'payment_status' => 'INCOMPLETE', 'amount_paid' => '11000000.00']);
    }

    public function test_pembayaran_dengan_ntpn_dan_bukti_langsung_confirmed(): void
    {
        $response = $this->post(self::URI, $this->payload([
            'ntpn' => 'NTPN-ABC123',
            'paymentProofFile' => $this->pdfUpload($this->simplePdf(), 'bukti.pdf'),
        ]), $this->auth());

        $response->assertOk()->assertJsonPath('data.status', 'CONFIRMED');

        $this->assertDatabaseHas('rekap_pajak_bulanan', ['tax_period' => '2026-11', 'payment_status' => 'CONFIRMED']);
    }

    public function test_hanya_ntpn_tanpa_bukti_tetap_incomplete(): void
    {
        $response = $this->post(self::URI, $this->payload(['ntpn' => 'NTPN-ABC123']), $this->auth());

        $response->assertOk()->assertJsonPath('data.status', 'INCOMPLETE');
        $this->assertSame('NTPN-ABC123', PembayaranPajak::query()->findOrFail($response->json('data.taxPaymentId'))->ntpn);
    }

    public function test_jumlah_setor_berbeda_dari_total_ppn_tetap_tersimpan_dengan_selisih(): void
    {
        $this->post(self::URI, $this->payload(['amountPaid' => '10000000']), $this->auth())
            ->assertOk()
            ->assertJsonPath('data.paymentDifference', '-1000000.00');

        $this->post(self::URI, $this->payload(['amountPaid' => '2500000.50', 'billingId' => 'BILL-202611-002']), $this->auth())
            ->assertOk()
            ->assertJsonPath('data.paymentDifference', '1500000.50');   // 10.000.000 + 2.500.000,50 - 11.000.000

        $this->assertSame(2, PembayaranPajak::query()->where('tax_period', '2026-11')->count());
    }

    public function test_masa_pajak_tanpa_rekap_404(): void
    {
        $this->post(self::URI, $this->payload(['taxPeriod' => '2027-01', 'taxYear' => 2027]), $this->auth())
            ->assertStatus(404)
            ->assertExactJson([
                'code' => '404',
                'message' => 'Masa pajak belum memiliki rekap',
                'data' => null,
                'errorCode' => 'TAX_RECAP_NOT_FOUND',
            ]);
    }

    public function test_masukan_tidak_valid_400_invalid_payment(): void
    {
        $cases = [
            'masa pajak salah' => ['taxPeriod' => '2026-13'],
            'masa pajak kosong' => ['taxPeriod' => null],
            'tahun tidak cocok' => ['taxYear' => 2025],
            'tahun bukan angka' => ['taxYear' => 'dua ribu'],
            'jumlah negatif' => ['amountPaid' => '-1.00'],
            'jumlah bukan angka' => ['amountPaid' => 'banyak'],
            'jumlah kosong' => ['amountPaid' => null],
            'billing kosong' => ['billingId' => '  '],
            'e-billing bukan pdf' => ['eBillingFile' => UploadedFile::fake()->createWithContent('x.pdf', 'bukan pdf')],
            'e-billing tidak ada' => ['eBillingFile' => null],
            'bukti bukan pdf' => ['paymentProofFile' => UploadedFile::fake()->createWithContent('b.pdf', 'bukan pdf')],
            'bukti terlalu besar' => ['paymentProofFile' => UploadedFile::fake()->create('b.pdf', 5121, 'application/pdf')],
        ];

        foreach ($cases as $label => $override) {
            $payload = array_filter($this->payload($override), fn ($v) => $v !== null);
            $response = $this->post(self::URI, $payload, $this->auth());

            $this->assertSame(400, $response->status(), $label);
            $this->assertSame('INVALID_PAYMENT', $response->json('errorCode'), $label);
        }

        $this->assertSame(0, PembayaranPajak::query()->count());
    }

    public function test_peran_tidak_berwenang_403(): void
    {
        $this->post(self::URI, $this->payload(), $this->auth('Finance'))
            ->assertStatus(403)->assertJsonPath('errorCode', 'FORBIDDEN');
    }

    // ---------------- API-KE01-11 ----------------

    private function draft(): string
    {
        return $this->createPayment('2026-11', '11000000.00')['taxPaymentId'];
    }

    public function test_melengkapi_pembayaran_menjadi_confirmed(): void
    {
        $id = $this->draft();

        $response = $this->patchMultipart(self::URI.'/'.$id, ['ntpn' => 'NTPN-ABC123'], [
            'paymentProofFile' => $this->pdfUpload($this->simplePdf(), 'bukti.pdf'),
        ]);

        $response->assertOk()->assertExactJson([
            'code' => '200',
            'message' => 'Pembayaran Terkonfirmasi',
            'data' => ['taxPaymentId' => $id, 'status' => 'CONFIRMED', 'paymentStatus' => 'CONFIRMED'],
        ]);

        $payment = PembayaranPajak::query()->findOrFail($id);
        $this->assertSame('NTPN-ABC123', $payment->ntpn);
        $this->assertStringStartsWith('/files/tax/payment-proof/', $payment->payment_proof_uri);
        $this->assertDatabaseHas('rekap_pajak_bulanan', ['tax_period' => '2026-11', 'payment_status' => 'CONFIRMED']);
    }

    public function test_status_rekap_tetap_incomplete_selama_masih_ada_catatan_draft(): void
    {
        $first = $this->draft();
        $this->createPayment('2026-11', '500000.00', ['billingId' => 'BILL-202611-002']); // tetap draft

        $this->patchMultipart(self::URI.'/'.$first, ['ntpn' => 'NTPN-1'], ['paymentProofFile' => $this->pdfUpload($this->simplePdf())])
            ->assertOk()
            ->assertJsonPath('data.status', 'CONFIRMED')
            ->assertJsonPath('data.paymentStatus', 'INCOMPLETE');
    }

    public function test_pelengkapan_wajib_ntpn_dan_bukti(): void
    {
        $id = $this->draft();

        $this->patchMultipart(self::URI.'/'.$id, [], ['paymentProofFile' => $this->pdfUpload($this->simplePdf())])
            ->assertStatus(400)
            ->assertJsonPath('errorCode', 'INVALID_PAYMENT')
            ->assertJsonPath('message', 'NTPN dan bukti pembayaran wajib diisi untuk Terkonfirmasi');

        $this->patchMultipart(self::URI.'/'.$id, ['ntpn' => 'NTPN-ABC123'])
            ->assertStatus(400)->assertJsonPath('errorCode', 'INVALID_PAYMENT');

        $this->patchMultipart(self::URI.'/'.$id, ['ntpn' => 'NTPN-ABC123'], [
            'paymentProofFile' => UploadedFile::fake()->createWithContent('b.pdf', 'bukan pdf'),
        ])->assertStatus(400)->assertJsonPath('errorCode', 'INVALID_PAYMENT');

        $this->assertSame('INCOMPLETE', PembayaranPajak::query()->findOrFail($id)->status);
    }

    public function test_pembayaran_tidak_dikenal_404(): void
    {
        foreach (['0191f3a0-7c1e-7a52-9d3b-4f6e2a1b8c99', 'bukan-uuid'] as $id) {
            $this->patchMultipart(self::URI.'/'.$id, ['ntpn' => 'N'], ['paymentProofFile' => $this->pdfUpload($this->simplePdf())])
                ->assertStatus(404)
                ->assertJsonPath('errorCode', 'TAX_PAYMENT_NOT_FOUND')
                ->assertJsonPath('message', 'Catatan pembayaran tidak ditemukan');
        }
    }

    public function test_mengganti_bukti_tidak_menghapus_berkas_lama(): void
    {
        $id = $this->draft();
        $this->patchMultipart(self::URI.'/'.$id, ['ntpn' => 'NTPN-1'], ['paymentProofFile' => $this->pdfUpload($this->simplePdf())])->assertOk();
        $old = PembayaranPajak::query()->findOrFail($id)->payment_proof_uri;

        $this->patchMultipart(self::URI.'/'.$id, ['ntpn' => 'NTPN-2'], ['paymentProofFile' => $this->pdfUpload($this->simplePdf())])->assertOk();

        $payment = PembayaranPajak::query()->findOrFail($id);
        $this->assertNotSame($old, $payment->payment_proof_uri);
        $this->assertSame('NTPN-2', $payment->ntpn);
        Storage::disk('local')->assertExists(ltrim(substr($old, strlen('/files/')), '/'));
    }
}
