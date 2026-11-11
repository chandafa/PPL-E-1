<?php

namespace Tests\Feature;

use App\Models\FakturPajak;
use App\Services\Auth\F1Identity;
use App\Services\Invoices\IntegrationCatalog;
use App\Services\Tax\TaxInvoiceService;
use App\Support\Integration\DemoIdentity;
use Database\Seeders\DemoSeeder;
use Database\Seeders\IntegrationSnapshotSeeder;
use Database\Seeders\TaxInvoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;

class TaxInvoiceSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['e1.d2.driver' => 'mock', 'e1.d2.mock_dataset' => 'sqlite']);
        Storage::fake('local');
        $this->seed(IntegrationSnapshotSeeder::class);
    }

    public function test_seeds_current_snapshot_invoices_with_pdf_audit_and_recap_without_payments(): void
    {
        Http::preventStrayRequests();
        $this->seed(TaxInvoiceSeeder::class);
        foreach (['faktur_pajak', 'item_faktur', 'riwayat_perubahan_faktur', 'rekap_pajak_bulanan'] as $table) {
            $this->assertDatabaseCount($table, 2);
        }
        $this->assertDatabaseCount('pembayaran_pajak', 0);
        foreach (FakturPajak::all() as $invoice) {
            $source = DB::table('invoices')->where('invoice_id', $invoice->invoice_id)->first();
            $this->assertSame($source->invoice_date, $invoice->tax_invoice_date->format('Y-m-d'));
            $this->assertSame($source->dpp_amount, $invoice->dpp);
            $this->assertSame($source->ppn_amount, $invoice->ppn);
            Storage::disk('local')->assertExists(substr($invoice->pdf_uri, strlen('/files/')));
        }
    }

    public function test_repeat_seed_preserves_user_cancellation_amounts_audit_and_files(): void
    {
        $this->seed(TaxInvoiceSeeder::class);
        $invoice = FakturPajak::where('invoice_id', 'inv-demo-sample')->firstOrFail();
        app(TaxInvoiceService::class)->update($invoice->tax_invoice_id, ['ppnbm' => '500.00'], 'Koreksi pengguna', new F1Identity('user', 'Admin'));
        app(TaxInvoiceService::class)->update($invoice->tax_invoice_id, ['status' => 'CANCELLED'], 'Pembatalan pengguna', new F1Identity('user', 'Admin'));
        $before = [];
        foreach (['faktur_pajak', 'item_faktur', 'riwayat_perubahan_faktur', 'rekap_pajak_bulanan', 'pembayaran_pajak'] as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }
        $files = Storage::disk('local')->allFiles();
        $this->seed(TaxInvoiceSeeder::class);
        foreach ($before as $table => $json) {
            $this->assertSame($json, DB::table($table)->get()->toJson());
        }
        $this->assertSame($files, Storage::disk('local')->allFiles());
    }

    private function httpSource(): void
    {
        config(['e1.d2.driver' => 'http', 'e1.d2.base_url' => 'http://127.0.0.1:8026', 'e1.d2.integration_catalog' => false]);
        $identity = Mockery::mock(DemoIdentity::class);
        $identity->shouldReceive('authenticate')->once()->andReturn(['accountId' => 'f1-admin-current', 'roleCode' => 'ADMIN', 'accessToken' => 'current-token']);
        $this->app->instance(DemoIdentity::class, $identity);
        $catalog = Mockery::mock(IntegrationCatalog::class);
        $catalog->shouldReceive('ids')->once()->andReturn(['live-source-invoice']);
        $this->app->instance(IntegrationCatalog::class, $catalog);
    }

    public function test_http_seed_uses_real_actor_and_fresh_source_instead_of_local_snapshot(): void
    {
        $this->httpSource();
        Http::fake(['http://127.0.0.1:8026/*' => Http::response(['data' => [
            'invoiceId' => 'live-source-invoice', 'invoiceNumber' => 'INV-LIVE-2027',
            'invoiceDate' => '2027-03-15', 'dppAmount' => '250000000.00', 'ppnAmount' => '27500000.00',
            'status' => 'TERTAGIH', 'projectId' => 'live-project',
        ]])]);
        $this->seed(TaxInvoiceSeeder::class);
        $this->assertDatabaseCount('faktur_pajak', 1);
        $this->assertDatabaseHas('faktur_pajak', [
            'invoice_id' => 'live-source-invoice', 'tax_invoice_date' => '2027-03-15', 'tax_period' => '2027-03',
            'dpp' => '250000000.00', 'ppn' => '27500000.00', 'created_by_akun_id' => 'f1-admin-current',
        ]);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer current-token'));
        $this->assertNull(request()->headers->get('Authorization'));
        $this->assertFalse(config('e1.d2.integration_catalog'));
        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_http_failure_leaves_no_tax_transactions_or_documents(): void
    {
        $this->httpSource();
        Http::fake(['http://127.0.0.1:8026/*' => Http::response([], 503)]);
        try {
            $this->seed(TaxInvoiceSeeder::class);
            $this->fail('Expected source failure');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('faktur_pajak', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
            $this->assertNull(request()->headers->get('Authorization'));
        }
    }

    public function test_every_http_request_including_invoice_creation_uses_the_fresh_token(): void
    {
        $this->httpSource();
        request()->headers->set('Authorization', 'Bearer stale-token');
        Http::fake(['http://127.0.0.1:8026/*' => function ($request) {
            if (! $request->hasHeader('Authorization', 'Bearer current-token')) {
                return Http::response([], 401);
            }

            return Http::response(['data' => [
                'invoiceId' => 'live-source-invoice', 'invoiceNumber' => 'INV-LIVE-2027',
                'invoiceDate' => '2027-03-15', 'dppAmount' => '250000000.00', 'ppnAmount' => '27500000.00',
                'status' => 'TERTAGIH', 'projectId' => 'live-project',
            ]]);
        }]);
        $this->artisan('db:seed', ['--class' => TaxInvoiceSeeder::class, '--force' => true])->assertSuccessful();
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => ! $request->hasHeader('Authorization', 'Bearer current-token'));
        $this->assertDatabaseHas('faktur_pajak', ['invoice_id' => 'live-source-invoice', 'created_by_akun_id' => 'f1-admin-current']);
        $this->assertSame('Bearer stale-token', request()->headers->get('Authorization'));
        $this->assertFalse(config('e1.d2.integration_catalog'));
    }

    public function test_original_demo_seeder_entry_point_also_restores_http_tax_invoices(): void
    {
        $this->httpSource();
        Http::fake(['http://127.0.0.1:8026/*' => Http::response(['data' => [
            'invoiceId' => 'live-source-invoice', 'invoiceNumber' => 'INV-2026-0004',
            'invoiceDate' => '2026-12-02', 'dppAmount' => '120000000.00', 'ppnAmount' => '13200000.00',
            'status' => 'TERTAGIH', 'projectId' => 'prj-demo-003',
        ]])]);
        $this->seed(DemoSeeder::class);
        $this->assertDatabaseHas('faktur_pajak', [
            'invoice_id' => 'live-source-invoice', 'project_id' => 'prj-demo-003', 'dpp' => '120000000.00',
        ]);
    }

    public function test_production_is_not_seeded(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('db:seed', ['--class' => TaxInvoiceSeeder::class, '--force' => true])->assertSuccessful();
        $this->assertDatabaseCount('faktur_pajak', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
