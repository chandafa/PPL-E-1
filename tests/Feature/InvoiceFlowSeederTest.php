<?php

namespace Tests\Feature;

use Database\Seeders\InvoiceFlowSeeder;
use Database\Seeders\Support\DemoDataset;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class InvoiceFlowSeederTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/flow-'.Str::uuid());
        File::makeDirectory($this->root, 0775, true);
        File::copy(config('e1.d2.mock_file'), $this->root.'/d2.json');
        config(['e1.d2.driver' => 'mock', 'e1.d2.mock_file' => $this->root.'/d2.json']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function prepare(): InvoiceFlowSeeder
    {
        $seeder = new InvoiceFlowSeeder;
        $seeder->batch = 'php-test';
        $seeder->outputRoot = $this->root;
        $seeder->run();

        return $seeder;
    }

    private function document(string $name)
    {
        return $this->pdfUpload(File::get($this->root.'/php-test/'.$name), $name);
    }

    private function record(string $id, string $file, ?string $replaces = null): array
    {
        $payload = ['invoiceId' => $id, 'eFakturFile' => $this->document($file)];
        if ($replaces !== null) {
            $payload['replacesTaxInvoiceId'] = $replaces;
        }

        return $this->post('/api/v1/tax-invoices/outgoing', $payload, $this->auth())->assertOk()->json('data');
    }

    private function cancel(string $id): void
    {
        $this->patchJson('/api/v1/tax-invoices/'.$id, [
            'changes' => ['status' => 'CANCELLED'], 'note' => 'Pengujian flow invoice',
        ], $this->auth())->assertOk();
    }

    public function test_seed_prepares_unused_invoices_and_documents_without_tax_transactions(): void
    {
        $seeder = $this->prepare();
        $this->assertDatabaseCount('faktur_pajak', 0);
        $this->assertDatabaseCount('pembayaran_pajak', 0);
        $this->assertCount(9, File::glob($this->root.'/php-test/*.pdf'));
        $this->assertStringNotContainsString('{{batch}}', File::get($this->root.'/php-test/PANDUAN.md'));
        $this->getJson('/api/ui/d2/invoices', $this->auth())->assertOk()
            ->assertJsonFragment(['invoiceId' => 'inv-flow-php-test-001'])
            ->assertJsonFragment(['invoiceId' => 'inv-flow-php-test-004']);
        $source = File::get($this->root.'/d2.json');
        $documents = File::get($this->root.'/php-test/manifest.json');
        try {
            $seeder->run();
            $this->fail('Batch yang sama seharusnya ditolak');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Batch sudah ada', $exception->getMessage());
        }
        $this->assertSame($source, File::get($this->root.'/d2.json'));
        $this->assertSame($documents, File::get($this->root.'/php-test/manifest.json'));
        $this->assertArrayNotHasKey('inv-flow-php-test-001', DemoDataset::seededInvoices());
    }

    public function test_new_package_runs_create_replace_cancel_reissue_payment_and_export(): void
    {
        $this->prepare();
        $first = 'inv-flow-php-test-001';
        $second = 'inv-flow-php-test-002';
        $third = 'inv-flow-php-test-003';
        $this->post('/api/v1/tax-invoices/extractions', [
            'invoiceId' => $first, 'eFakturFile' => $this->document('09-bukan-efaktur.pdf'),
        ], $this->auth())->assertBadRequest();
        $this->post('/api/v1/tax-invoices/extractions', [
            'invoiceId' => $first, 'eFakturFile' => $this->document('01-faktur-001.pdf'),
        ], $this->auth())->assertOk()->assertJsonPath('data.dpp', '10000000.00');
        $original = $this->record($first, '01-faktur-001.pdf');
        $this->getJson('/api/ui/d2/invoices', $this->auth())->assertJsonMissing(['invoiceId' => $first]);
        $this->post('/api/v1/tax-invoices/outgoing', [
            'invoiceId' => $first, 'eFakturFile' => $this->document('01-faktur-001.pdf'),
        ], $this->auth())->assertConflict()->assertJsonPath('errorCode', 'INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE');
        $replacement = $this->record($first, '05-pengganti-001.pdf', $original['taxInvoiceId']);
        $this->assertSame('REPLACEMENT', $replacement['status']);
        $this->getJson('/api/ui/d2/invoices', $this->auth())->assertJsonMissing(['invoiceId' => $first]);

        $cancelled = $this->record($second, '02-faktur-002.pdf');
        $this->cancel($cancelled['taxInvoiceId']);
        $this->getJson('/api/ui/d2/invoices', $this->auth())->assertJsonFragment(['invoiceId' => $second]);
        $this->record($second, '06-catat-ulang-002.pdf');
        $multi = $this->record($third, '03-faktur-003.pdf');
        $this->assertCount(2, $multi['items']);
        $this->assertSame('100000.00', $multi['ppnbm']);
        $catalog = $this->getJson('/api/ui/d2/invoices', $this->auth())->assertOk()->json('data.invoices');
        $remaining = array_filter(array_column($catalog, 'invoiceId'), fn ($id) => str_starts_with($id, 'inv-flow-php-test-'));
        $this->assertSame(['inv-flow-php-test-004'], array_values($remaining));
        $this->getJson('/api/v1/tax/summary?taxPeriod=2026-09&projectId=prj-flow-php-test', $this->auth())
            ->assertOk()->assertJsonPath('data.invoiceCount', 3)->assertJsonPath('data.totalDpp', '37000000.00')
            ->assertJsonPath('data.totalPpn', '4070000.00')->assertJsonPath('data.totalPpnbm', '100000.00');
        $draft = $this->post('/api/v1/tax-payments', [
            'taxPeriod' => '2026-09', 'taxYear' => 2026, 'amountPaid' => '4070000.00',
            'billingId' => 'BILL-FLOW-php-test', 'eBillingFile' => $this->document('07-e-billing.pdf'),
        ], $this->auth())->assertOk()->assertJsonPath('data.status', 'INCOMPLETE')->json('data');
        $this->patchMultipart('/api/v1/tax-payments/'.$draft['taxPaymentId'], ['ntpn' => 'NTPN-FLOW-php-test'], [
            'paymentProofFile' => $this->document('08-bukti-pembayaran.pdf'),
        ])->assertOk()->assertJsonPath('data.status', 'CONFIRMED');
        $this->getJson('/api/v1/tax-recaps/2026-09', $this->auth())->assertOk()
            ->assertJsonPath('data.recap.paymentDifference', '0.00');
        foreach (['pdf', 'xlsx'] as $format) {
            $this->get('/api/v1/tax-recaps/2026-09/export?format='.$format, $this->auth())->assertOk();
        }
        $this->assertDatabaseMissing('faktur_pajak', ['invoice_id' => 'inv-flow-php-test-004']);
    }

    public function test_seed_flow_is_disabled_for_production_and_http_driver(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        $this->prepare();
    }
}
