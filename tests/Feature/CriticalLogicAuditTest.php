<?php

namespace Tests\Feature;

use App\Models\FakturPajak;
use App\Models\PembayaranPajak;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InvoiceScenarioSeeder;
use Tests\TestCase;

/** Regresi audit; OBS-02 tentang koreksi setelah setor tetap observasi kebijakan. */
class CriticalLogicAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InvoiceScenarioSeeder::class);
    }

    public function test_cancel_middle_replacement_is_rejected_without_double_counting(): void
    {
        $a = $this->createFaktur();
        $b = $this->createFaktur('inv-demo-sample', [], $a['taxInvoiceId']);
        $c = $this->createFaktur('inv-demo-sample', [], $b['taxInvoiceId']);

        $this->getJson('/api/v1/tax-recaps/2026-11', $this->auth())->assertOk()
            ->assertJsonPath('data.recap.invoiceCount', 1)
            ->assertJsonPath('data.recap.totalPpn', '11000000.00');

        $this->patchJson('/api/v1/tax-invoices/'.$b['taxInvoiceId'], [
            'changes' => ['status' => 'CANCELLED'],
        ], $this->auth())->assertStatus(409)->assertJsonPath('errorCode', 'INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE');

        $this->assertEqualsCanonicalizing([$c['taxInvoiceId']],
            FakturPajak::query()->active()->pluck('tax_invoice_id')->all());
        $this->getJson('/api/v1/tax-recaps/2026-11', $this->auth())->assertOk()
            ->assertJsonPath('data.recap.invoiceCount', 1)
            ->assertJsonPath('data.recap.totalPpn', '11000000.00');
    }

    public function test_paid_invoice_can_be_reduced_then_payment_confirmed_with_positive_difference(): void
    {
        $f = $this->createFaktur();
        $payment = $this->createPayment('2026-11', '11000000.00');
        $this->patchJson('/api/v1/tax-invoices/'.$f['taxInvoiceId'], [
            'changes' => ['ppn' => '1.00'],
        ], $this->auth())->assertOk();

        $this->patchMultipart('/api/v1/tax-payments/'.$payment['taxPaymentId'], [
            'ntpn' => 'q9z3k7m2w8',
        ], ['paymentProofFile' => $this->pdfUpload($this->simplePdf())])->assertOk();

        $this->getJson('/api/v1/tax-recaps/2026-11', $this->auth())->assertOk()
            ->assertJsonPath('data.recap.totalPpn', '1.00')
            ->assertJsonPath('data.recap.amountPaid', '11000000.00')
            ->assertJsonPath('data.recap.paymentDifference', '10999999.00')
            ->assertJsonPath('data.recap.paymentStatus', 'CONFIRMED');
    }

    public function test_same_ntpn_cannot_be_counted_as_two_payments(): void
    {
        $this->createFaktur();
        $proof = $this->simplePdf();
        $this->createPayment('2026-11', '5500000.00', [
            'billingId' => 'x8k4n2q9m6', 'ntpn' => 'q9z3k7m2w8', 'paymentProofFile' => $this->pdfUpload($proof),
        ]);
        $this->post('/api/v1/tax-payments', [
            'taxPeriod' => '2026-11', 'taxYear' => 2026, 'amountPaid' => '5500000.00',
            'billingId' => 'w7r3z8p2v5', 'ntpn' => 'q9z3k7m2w8',
            'eBillingFile' => $this->pdfUpload($this->simplePdf()), 'paymentProofFile' => $this->pdfUpload($proof),
        ], $this->auth())->assertStatus(409)->assertJsonPath('errorCode', 'DUPLICATE_NTPN');
        $this->assertSame(1, PembayaranPajak::query()->where('ntpn', 'q9z3k7m2w8')->count());
        $this->getJson('/api/v1/tax-recaps/2026-11', $this->auth())->assertOk()
            ->assertJsonPath('data.recap.amountPaid', '5500000.00')
            ->assertJsonPath('data.recap.paymentStatus', 'CONFIRMED');
    }

    public function test_aggregate_overflow_is_rejected_400_and_rolls_back_correction(): void
    {
        $a = $this->createFaktur();
        $b = $this->createFaktur('inv-demo-002');
        $this->patchJson('/api/v1/tax-invoices/'.$a['taxInvoiceId'], [
            'changes' => ['dpp' => '9999999899999999.99'],
        ], $this->auth())->assertOk();
        $this->patchJson('/api/v1/tax-invoices/'.$b['taxInvoiceId'], [
            'changes' => ['dpp' => '100000001.00'],
        ], $this->auth())->assertStatus(400)->assertJsonPath('errorCode', 'INVALID_CHANGE');

        $this->assertSame('100000000.00', FakturPajak::query()->findOrFail($b['taxInvoiceId'])->dpp);
        $this->getJson('/api/v1/tax-recaps/2026-11', $this->auth())->assertOk()
            ->assertJsonPath('data.recap.totalDpp', '9999999999999999.99');
    }

    public function test_money_comparison_rejects_overpayment_without_files_or_audit_side_effects(): void
    {
        $this->createFaktur();
        $files = Storage::disk('local')->allFiles();
        $this->post('/api/v1/tax-payments', [
            'taxPeriod' => '2026-11', 'taxYear' => 2026, 'amountPaid' => '11000000.01',
            'billingId' => 'q9z3k7m2w8', 'eBillingFile' => $this->pdfUpload($this->simplePdf()),
        ], $this->auth())->assertStatus(400)->assertJsonPath('errorCode', 'INVALID_PAYMENT');
        $this->assertDatabaseCount('pembayaran_pajak', 0);
        $this->assertDatabaseCount('riwayat_perubahan_pembayaran', 0);
        $this->assertSame($files, Storage::disk('local')->allFiles());
    }

    public function test_actor_and_payment_status_cannot_be_mass_assigned(): void
    {
        $this->createFaktur();
        $payment = $this->createPayment('2026-11', '1.00', [
            'created_by_akun_id' => 'x8k4n2q9m6', 'createdByAkunId' => 'x8k4n2q9m6',
            'status' => 'CONFIRMED', 'payment_proof_uri' => '/files/q9z3k7m2w8.pdf',
        ]);
        $stored = PembayaranPajak::query()->findOrFail($payment['taxPaymentId']);
        $this->assertSame('AKN-0002', $stored->created_by_akun_id);
        $this->assertSame('INCOMPLETE', $stored->status);
        $this->assertNull($stored->payment_proof_uri);
    }

    public function test_search_inputs_remain_bound_literals_instead_of_sql_or_wildcards(): void
    {
        $this->createFaktur();
        foreach (["' OR 1=1 -- q9z3k7m2w8", '%', '_'] as $value) {
            foreach (['taxInvoiceNumber', 'project', 'client'] as $field) {
                $this->getJson('/api/v1/tax-invoices?'.http_build_query([$field => $value]), $this->auth())
                    ->assertOk()->assertJsonPath('data.pagination.totalItems', 0);
            }
        }
        $this->assertDatabaseCount('faktur_pajak', 1);
    }

    public static function protectedEndpoints(): array
    {
        $id = '0191f3a0-7c1e-7a52-9d3b-4f6e2a1b8c99';

        return [
            ['POST', '/api/v1/tax-invoices/extractions'],
            ['POST', '/api/v1/tax-invoices/outgoing'],
            ['GET', '/api/v1/tax-invoices'],
            ['GET', '/api/v1/tax-invoices/'.$id],
            ['PATCH', '/api/v1/tax-invoices/'.$id],
            ['GET', '/api/v1/tax-invoices/'.$id.'/history'],
            ['GET', '/api/v1/tax-recaps/2026-11'],
            ['GET', '/api/v1/tax-recaps/2026-11/export?format=xlsx'],
            ['POST', '/api/v1/tax-payments'],
            ['PATCH', '/api/v1/tax-payments/'.$id],
            ['GET', '/api/ui/d2/invoices'],
            ['GET', '/api/ui/tax-recap-periods'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_protected_endpoint_rejects_missing_token_and_summary_only_role(string $method, string $uri): void
    {
        $body = ['changes' => ['status' => 'CANCELLED']];
        $this->json($method, $uri, $body)->assertStatus(401)->assertJsonPath('errorCode', 'UNAUTHORIZED');
        $this->json($method, $uri, $body, $this->auth('LayananE2'))
            ->assertStatus(403)->assertJsonPath('errorCode', 'FORBIDDEN');
        $this->assertDatabaseCount('faktur_pajak', 0);
        $this->assertDatabaseCount('pembayaran_pajak', 0);
    }

    public function test_none_algorithm_and_payload_tampering_do_not_grant_admin(): void
    {
        $token = $this->token('Finance');
        [$header, $payload, $signature] = explode('.', $token);
        $claims = json_decode(JWT::urlsafeB64Decode($payload), true);
        $claims['kodePeran'] = 'Admin';
        $payload = JWT::urlsafeB64Encode(json_encode($claims, JSON_THROW_ON_ERROR));
        $none = JWT::urlsafeB64Encode('{"alg":"none","typ":"JWT"}');

        foreach (["{$none}.{$payload}.", "{$header}.{$payload}.{$signature}"] as $forged) {
            $this->getJson('/api/v1/tax-invoices', ['Authorization' => 'Bearer '.$forged])
                ->assertStatus(401)->assertJsonPath('errorCode', 'UNAUTHORIZED');
        }
    }
}
