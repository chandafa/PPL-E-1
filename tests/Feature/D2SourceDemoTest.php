<?php

namespace Tests\Feature;

use App\Services\Invoices\MockInvoiceProvider;
use Database\Seeders\D2SourceDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RestoredD2DemoSeeder;
use Database\Seeders\Support\D2SourceDataset;
use Database\Seeders\Support\DemoDataset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class D2SourceDemoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['e1.d2.mock_dataset' => 'sqlite']);
        DB::table('invoice_histories')->where('invoice_id', 'inv-demo-sample')->delete();
        DB::table('invoices')->where('invoice_id', 'inv-demo-sample')->delete();
    }

    public function test_default_database_seeder_installs_the_sqlite_snapshot(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseHas('invoices', ['invoice_id' => 'inv-demo-001', 'invoice_number' => 'INV-2026-0002', 'invoice_date' => '2026-10-28']);
        $this->assertDatabaseHas('invoices', ['invoice_id' => 'inv-demo-sample', 'invoice_date' => '2026-11-01']);
        $this->assertDatabaseCount('faktur_pajak', 0);
        $this->assertDatabaseCount('pembayaran_pajak', 0);
    }

    public function test_stale_reference_already_used_by_e1_is_not_overwritten(): void
    {
        $this->seed(D2SourceDemoSeeder::class);
        $this->createFaktur('inv-demo-001', DemoDataset::invoices()['inv-demo-001']['pdf']);
        DB::table('termins')->where('termin_id', 'trm-demo-002')->update(['status' => 'SIAP_DITAGIH']);
        try {
            $this->seed(D2SourceDemoSeeder::class);
            $this->fail('Expected conflict for a consumed reference');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Konflik snapshot SQLite', $error->getMessage());
        }
        $this->assertDatabaseHas('termins', ['termin_id' => 'trm-demo-002', 'status' => 'SIAP_DITAGIH']);
        $this->assertDatabaseCount('faktur_pajak', 1);
    }

    public function test_seed_matches_all_source_business_rows_and_timestamps(): void
    {
        $this->seed(D2SourceDemoSeeder::class);
        foreach (D2SourceDataset::snapshot()['tables'] as $table => $rows) {
            $this->assertDatabaseCount($table, count($rows));
            foreach ($rows as $row) {
                if ($table === 'invoice_histories') {
                    unset($row['id']);
                }
                $this->assertDatabaseHas($table, $row);
            }
        }
        $this->assertDatabaseCount('d2_invoice_items', 0);
        $this->assertDatabaseCount('faktur_pajak', 0);
        $this->assertDatabaseCount('pembayaran_pajak', 0);
        $this->assertDatabaseHas('invoices', ['invoice_id' => 'inv-demo-001', 'invoice_number' => 'INV-2026-0002', 'dpp_amount' => '150000000.00']);
        $this->assertDatabaseHas('termins', ['termin_id' => 'trm-demo-002', 'status' => 'SUDAH_DITAGIHKAN']);
    }

    public function test_repeat_seed_preserves_tax_transactions_and_reference_timestamps(): void
    {
        $this->seed(D2SourceDemoSeeder::class);
        $fixture = DemoDataset::invoices()['inv-demo-sample'];
        $this->createFaktur('inv-demo-sample', $fixture['pdf']);
        $before = [];
        foreach (['contracts', 'termins', 'tax_parameters', 'invoices', 'invoice_histories', 'faktur_pajak', 'riwayat_perubahan_faktur'] as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }
        $this->seed(D2SourceDemoSeeder::class);
        foreach ($before as $table => $json) {
            $this->assertSame($json, DB::table($table)->get()->toJson(), $table);
        }
    }

    public function test_conflicting_legacy_data_rolls_back_the_whole_import(): void
    {
        config(['e1.d2.mock_dataset' => 'legacy']);
        $this->seed(RestoredD2DemoSeeder::class);
        $before = [];
        foreach (['contracts', 'termins', 'invoices', 'invoice_histories'] as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }
        config(['e1.d2.mock_dataset' => 'sqlite']);
        try {
            $this->seed(D2SourceDemoSeeder::class);
            $this->fail('Expected snapshot conflict');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Konflik snapshot SQLite', $error->getMessage());
        }
        foreach ($before as $table => $json) {
            $this->assertSame($json, DB::table($table)->get()->toJson());
        }
    }

    public function test_restoring_demo_preserves_canonical_invoice(): void
    {
        $this->seed(D2SourceDemoSeeder::class);
        config(['e1.d2.mock_dataset' => 'legacy']);
        $this->seed(RestoredD2DemoSeeder::class);
        $this->assertDatabaseHas('invoices', ['invoice_id' => 'inv-demo-001', 'dpp_amount' => '150000000.00']);
    }

    public function test_old_synthetic_records_are_retained_but_not_served_by_active_snapshot(): void
    {
        DB::table('contracts')->insert(['contract_id' => 'ktr-d2-local-a', 'project_id' => 'prj-d2-local-a', 'project_name' => 'Legacy ERP', 'client_id' => 'cli-old', 'client_name' => 'Old Client', 'contract_number' => 'OLD', 'contract_value' => '1.00', 'created_by' => 'old']);
        DB::table('termins')->insert(['termin_id' => 'trm-d2-local-001', 'contract_id' => 'ktr-d2-local-a', 'termin_number' => 1, 'termin_value' => '1.00', 'due_date' => '2026-10-01', 'status' => 'SUDAH_DITAGIHKAN']);
        DB::table('invoices')->insert(['invoice_id' => 'inv-d2-local-001', 'invoice_number' => 'OLD', 'termin_id' => 'trm-d2-local-001', 'tax_param_id' => 'pjk-demo-001', 'invoice_date' => '2026-10-01', 'dpp_amount' => '1.00', 'ppn_amount' => '0.11', 'pph_amount' => '0.02', 'total_amount' => '1.09', 'created_by' => 'old']);
        $this->seed(D2SourceDemoSeeder::class);
        $provider = app(MockInvoiceProvider::class);
        $this->assertCount(2, $provider->catalog());
        $this->assertNull($provider->find('inv-d2-local-001'));
        $this->assertSame([], $provider->projectIdsMatching('Legacy'));
        $this->assertSame([], $provider->clientIdsMatching('Old'));
        $this->assertFalse($provider->projectExists('prj-d2-local-a'));
        $this->getJson('/api/ui/d2/invoices/inv-d2-local-001', $this->auth())->assertNotFound();
        $this->assertDatabaseHas('invoices', ['invoice_id' => 'inv-d2-local-001']);
    }

    public function test_e1_api_exposes_source_only_without_external_http_or_invented_metadata(): void
    {
        Http::preventStrayRequests();
        $this->seed(D2SourceDemoSeeder::class);
        $this->getJson('/api/ui/d2/invoices/all', $this->auth())->assertOk()->assertJsonCount(2, 'data.invoices');
        $this->getJson('/api/ui/d2/invoices/inv-demo-001', $this->auth())->assertOk()
            ->assertJsonPath('data.source', 'SQLITE_SNAPSHOT')
            ->assertJsonPath('data.sourceSha256', D2SourceDataset::snapshot()['source']['sha256'])
            ->assertJsonPath('data.invoice.invoiceNumber', 'INV-2026-0002')
            ->assertJsonPath('data.invoice.invoiceDate', '2026-10-28')
            ->assertJsonPath('data.invoice.projectId', 'prj-demo-001')
            ->assertJsonPath('data.invoice.projectName', null)->assertJsonPath('data.invoice.clientId', null)
            ->assertJsonPath('data.invoice.clientName', null)->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.contract.contractValue', '500000000.00')
            ->assertJsonPath('data.termin.terminNumber', 2)->assertJsonPath('data.taxParameter.ppnRate', '11.00')
            ->assertJsonPath('data.invoice.totalAmount', '163500000.00')->assertJsonCount(1, 'data.history');
        $this->getJson('/api/ui/d2/invoices/inv-demo-001')->assertUnauthorized();
        $this->getJson('/api/ui/d2/invoices/inv-demo-001', $this->auth('CTO'))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_generated_documents_support_both_invoices_replacement_and_monthly_payments(): void
    {
        $this->seed(D2SourceDemoSeeder::class);
        $relative = 'storage/framework/testing/d2-sqlite-'.Str::uuid();
        $directory = base_path($relative);
        try {
            $this->artisan('demo:samples', ['--dataset' => 'd2-source', '--path' => $relative])->assertSuccessful();
            $this->assertCount(8, glob($directory.'/*.pdf'));
            $manifest = json_decode(file_get_contents($directory.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertCount(4, $manifest);
            $this->assertCount(4, array_unique(array_column($manifest, 'nsfp')));
            foreach ($manifest as $sample) {
                $expectedPeriod = $sample['invoiceId'] === 'inv-demo-sample' ? '2026-11' : '2026-10';
                $this->assertSame($expectedPeriod, $sample['taxPeriod']);
                $this->assertSame(1, $sample['itemCount']);
                $this->assertSame(hash_file('sha256', $directory.'/'.$sample['file']), $sample['sha256']);
                if ($sample['flow'] !== 'create') {
                    continue;
                }
                $payload = ['invoiceId' => $sample['invoiceId'], 'eFakturFile' => $this->pdfUpload(file_get_contents($directory.'/'.$sample['file']))];
                $this->post('/api/v1/tax-invoices/extractions', $payload, $this->auth())->assertOk()->assertJsonPath('data.ppn', $sample['ppn']);
                $saved = $this->post('/api/v1/tax-invoices/outgoing', $payload, $this->auth())->assertOk();
                if ($sample['invoiceId'] === 'inv-demo-sample') {
                    $this->post('/api/v1/tax-invoices/outgoing', [
                        'invoiceId' => $sample['invoiceId'], 'replacesTaxInvoiceId' => $saved->json('data.taxInvoiceId'),
                        'eFakturFile' => $this->pdfUpload(file_get_contents($directory.'/efaktur-inv-demo-sample-pengganti.pdf')),
                    ], $this->auth())->assertOk();
                }
            }
            $this->post('/api/v1/tax-payments', [
                'taxPeriod' => '2026-10', 'taxYear' => 2026, 'amountPaid' => '16500000.00',
                'billingId' => 'BILL-DEMO-202610-001', 'ntpn' => 'NTPN-DEMO-202610-001',
                'eBillingFile' => $this->pdfUpload(file_get_contents($directory.'/e-billing-2026-10-001.pdf')),
                'paymentProofFile' => $this->pdfUpload(file_get_contents($directory.'/bukti-pembayaran-2026-10-001.pdf')),
            ], $this->auth())->assertOk();
            $this->getJson('/api/v1/tax-recaps/2026-10', $this->auth())->assertOk()
                ->assertJsonPath('data.recap.invoiceCount', 1)->assertJsonPath('data.recap.totalPpn', '16500000.00')
                ->assertJsonPath('data.recap.paymentDifference', '0.00')->assertJsonPath('data.recap.paymentStatus', 'CONFIRMED');
            $this->post('/api/v1/tax-payments', [
                'taxPeriod' => '2026-11', 'taxYear' => 2026, 'amountPaid' => '11000000.00',
                'billingId' => 'BILL-DEMO-202611-002', 'ntpn' => 'NTPN-DEMO-202611-002',
                'eBillingFile' => $this->pdfUpload(file_get_contents($directory.'/e-billing-2026-11-002.pdf')),
                'paymentProofFile' => $this->pdfUpload(file_get_contents($directory.'/bukti-pembayaran-2026-11-002.pdf')),
            ], $this->auth())->assertOk();
            $this->getJson('/api/v1/tax-recaps/2026-11', $this->auth())->assertOk()
                ->assertJsonPath('data.recap.invoiceCount', 1)->assertJsonPath('data.recap.totalPpn', '11000000.00')
                ->assertJsonPath('data.recap.paymentDifference', '0.00')->assertJsonPath('data.recap.paymentStatus', 'CONFIRMED');
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_prepare_command_and_archive_preserve_old_sample_files(): void
    {
        Http::preventStrayRequests();
        $relative = 'storage/framework/testing/d2-prepare-'.Str::uuid();
        $directory = base_path($relative);
        File::ensureDirectoryExists($directory);
        file_put_contents($directory.'/old.pdf', 'previous sample');
        try {
            $this->artisan('demo:d2', ['--path' => $relative])->assertSuccessful();
            $this->assertCount(8, glob($directory.'/*.pdf'));
            $archives = glob($directory.'-legacy-*');
            $this->assertCount(1, $archives);
            $this->assertSame('previous sample', file_get_contents($archives[0].'/old.pdf'));
            $this->assertDatabaseCount('faktur_pajak', 0);
            Http::assertNothingSent();
        } finally {
            File::deleteDirectory($directory);
            foreach (glob($directory.'-legacy-*') as $archive) {
                File::deleteDirectory($archive);
            }
        }
    }

    public function test_prepare_command_rejects_production_and_http(): void
    {
        foreach (['production', 'testing'] as $environment) {
            $this->app['env'] = $environment;
            config(['e1.d2.driver' => $environment === 'testing' ? 'http' : 'mock']);
            $this->artisan('demo:d2')->assertFailed();
            $this->assertDatabaseCount('invoices', 0);
        }
    }
}
