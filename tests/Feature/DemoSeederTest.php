<?php

namespace Tests\Feature;

use App\Models\FakturPajak;
use App\Models\PembayaranPajak;
use App\Services\Tax\TaxPaymentService;
use Database\Seeders\DemoSeeder;
use Database\Seeders\Support\DemoDataset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    public function test_dataset_lengkap_pagination_nyata_dan_pengisian_ulang_mempertahankan_edit(): void
    {
        $this->seed(DemoSeeder::class);
        foreach (['faktur_pajak' => 32, 'item_faktur' => 34, 'riwayat_perubahan_faktur' => 35, 'pembayaran_pajak' => 5, 'rekap_pajak_bulanan' => 5] as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $first = $this->getJson('/api/v1/tax-invoices?pageSize=20&page=1', $this->auth())->assertOk()->assertJsonCount(20, 'data.items')->assertJsonPath('data.pagination.totalItems', 32);
        $second = $this->getJson('/api/v1/tax-invoices?pageSize=20&page=2', $this->auth())->assertOk()->assertJsonCount(12, 'data.items');
        $this->assertSame([], array_intersect(array_column($first->json('data.items'), 'taxInvoiceId'), array_column($second->json('data.items'), 'taxInvoiceId')));
        foreach ([
            '2026-10' => [24, '5940000.00', '0.00', 'CONFIRMED'],
            '2026-11' => [3, '46200000.00', '0.00', 'INCOMPLETE'],
            '2026-12' => [1, '6600000.00', '-6600000.00', 'NOT_RECORDED'],
            '2027-01' => [1, '1100000.00', '-100000.00', 'CONFIRMED'],
            '2027-02' => [1, '1100000.00', '100000.00', 'CONFIRMED'],
        ] as $period => [$count, $ppn, $difference, $status]) {
            $this->getJson('/api/v1/tax-recaps/'.$period, $this->auth())->assertOk()
                ->assertJsonPath('data.recap.invoiceCount', $count)->assertJsonPath('data.recap.totalPpn', $ppn)
                ->assertJsonPath('data.recap.paymentDifference', $difference)->assertJsonPath('data.recap.paymentStatus', $status);
        }
        $this->getJson('/api/v1/tax-recaps/2026-10', $this->auth())->assertJsonPath('data.recap.totalPpnbm', '100000.00')->assertJsonCount(2, 'data.payments');
        $this->assertSame(41, count(Storage::disk('local')->allFiles('tax')));
        foreach (FakturPajak::all() as $invoice) {
            Storage::disk('local')->assertExists(substr($invoice->pdf_uri, strlen('/files/')));
        }
        foreach (PembayaranPajak::all() as $payment) {
            Storage::disk('local')->assertExists(substr($payment->e_billing_uri, strlen('/files/')));
            if ($payment->status === 'CONFIRMED') {
                $this->assertNotNull($payment->ntpn);
                Storage::disk('local')->assertExists(substr($payment->payment_proof_uri, strlen('/files/')));
            }
        }

        $invoice = FakturPajak::where('invoice_id', 'inv-demo-001')->firstOrFail();
        $this->patchJson('/api/v1/tax-invoices/'.$invoice->tax_invoice_id, ['changes' => ['ppnbm' => '500.00'], 'note' => 'Edit pengguna'], $this->auth())->assertOk();
        $draft = PembayaranPajak::where('status', 'INCOMPLETE')->firstOrFail();
        $this->patch('/api/v1/tax-payments/'.$draft->tax_payment_id, ['ntpn' => 'NTPN-EDIT-PENGGUNA', 'paymentProofFile' => $this->pdfUpload($this->simplePdf())], $this->auth())->assertOk();
        $manual = $this->createFaktur('inv-demo-007', DemoDataset::invoices()['inv-demo-007']['pdf']);
        $snapshot = [];
        foreach (['faktur_pajak', 'item_faktur', 'riwayat_perubahan_faktur', 'pembayaran_pajak', 'rekap_pajak_bulanan'] as $table) {
            $snapshot[$table] = DB::table($table)->get()->toJson();
        }
        $files = Storage::disk('local')->allFiles();
        $this->seed(DemoSeeder::class);
        foreach ($snapshot as $table => $json) {
            $this->assertSame($json, DB::table($table)->get()->toJson(), $table.' tidak boleh diubah saat seeding ulang');
        }
        $this->assertSame($files, Storage::disk('local')->allFiles());
        $this->assertDatabaseHas('faktur_pajak', ['tax_invoice_id' => $manual['taxInvoiceId']]);
    }

    public function test_pdf_siap_pakai_bisa_diekstrak_disimpan_dan_diganti_tanpa_konflik_nsfp(): void
    {
        $this->seed(DemoSeeder::class);
        $relative = 'storage/framework/testing/demo-samples-'.Str::uuid();
        $directory = base_path($relative);
        try {
            $this->artisan('demo:samples', ['--path' => $relative])->assertSuccessful();
            $this->assertCount(39, glob($directory.'/*.pdf'));
            $manifest = json_decode(file_get_contents($directory.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertCount(37, $manifest);
            $this->assertCount(37, array_unique(array_column($manifest, 'nsfp')));
            foreach (['006', '007'] as $suffix) {
                $id = 'inv-demo-'.$suffix;
                $this->assertDatabaseMissing('faktur_pajak', ['invoice_id' => $id]);
                $this->post('/api/v1/tax-invoices/extractions', ['invoiceId' => $id, 'eFakturFile' => $this->pdfUpload(file_get_contents($directory.'/efaktur-'.$id.'.pdf'))], $this->auth())->assertOk();
            }
            $create = $this->post('/api/v1/tax-invoices/outgoing', [
                'invoiceId' => 'inv-demo-006', 'eFakturFile' => $this->pdfUpload(file_get_contents($directory.'/efaktur-inv-demo-006.pdf')),
            ], $this->auth())->assertOk();
            $replacement = $this->post('/api/v1/tax-invoices/outgoing', [
                'invoiceId' => 'inv-demo-006', 'replacesTaxInvoiceId' => $create->json('data.taxInvoiceId'),
                'eFakturFile' => $this->pdfUpload(file_get_contents($directory.'/efaktur-inv-demo-006-pengganti.pdf')),
            ], $this->auth())->assertOk()->assertJsonPath('data.status', 'REPLACEMENT');
            $this->getJson('/api/v1/tax-invoices/'.$create->json('data.taxInvoiceId'), $this->auth())->assertJsonPath('data.replacedByTaxInvoiceId', $replacement->json('data.taxInvoiceId'));
            $this->post('/api/v1/tax-invoices/extractions', ['invoiceId' => 'inv-demo-004', 'eFakturFile' => $this->pdfUpload(file_get_contents($directory.'/efaktur-inv-demo-004-baru.pdf'))], $this->auth())->assertOk();
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_gagal_seeding_rollback_data_dan_berkas_baru_tetapi_mempertahankan_data_lama(): void
    {
        $existing = $this->createFaktur();
        $oldFiles = Storage::disk('local')->allFiles();
        $mock = Mockery::mock(TaxPaymentService::class);
        $mock->shouldReceive('create')->once()->andThrow(new RuntimeException('Simulasi penyimpanan pembayaran gagal'));
        $this->app->instance(TaxPaymentService::class, $mock);
        try {
            $this->seed(DemoSeeder::class);
            $this->fail('Seeding seharusnya gagal.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulasi penyimpanan pembayaran gagal', $exception->getMessage());
        }
        $this->assertDatabaseCount('faktur_pajak', 1);
        $this->assertDatabaseHas('faktur_pajak', ['tax_invoice_id' => $existing['taxInvoiceId']]);
        $this->assertDatabaseCount('pembayaran_pajak', 0);
        $this->assertDatabaseCount('riwayat_perubahan_faktur', 1);
        $this->assertSame($oldFiles, Storage::disk('local')->allFiles());
    }

    public function test_seeder_tidak_berjalan_di_production_atau_dengan_d2_http(): void
    {
        $this->app['env'] = 'production';
        $this->seed(DemoSeeder::class);
        $this->app['env'] = 'testing';
        config(['e1.d2.driver' => 'http']);
        $this->seed(DemoSeeder::class);
        $this->assertDatabaseCount('faktur_pajak', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
