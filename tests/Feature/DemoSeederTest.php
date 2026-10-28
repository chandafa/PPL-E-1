<?php

namespace Tests\Feature;

use App\Models\FakturPajak;
use App\Models\PembayaranPajak;
use App\Services\EFaktur\EFakturPdfParser;
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
        foreach (['faktur_pajak' => 32, 'item_faktur' => 32, 'riwayat_perubahan_faktur' => 32, 'pembayaran_pajak' => 5, 'rekap_pajak_bulanan' => 5] as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $first = $this->getJson('/api/v1/tax-invoices?pageSize=20&page=1', $this->auth())->assertOk()->assertJsonCount(20, 'data.items')->assertJsonPath('data.pagination.totalItems', 32);
        $second = $this->getJson('/api/v1/tax-invoices?pageSize=20&page=2', $this->auth())->assertOk()->assertJsonCount(12, 'data.items');
        $this->assertSame([], array_intersect(array_column($first->json('data.items'), 'taxInvoiceId'), array_column($second->json('data.items'), 'taxInvoiceId')));
        foreach (
            [
                '2026-10' => [25, '16940000.00', '-11000000.00', 'CONFIRMED'],
                '2026-11' => [3, '46750000.00', '0.00', 'INCOMPLETE'],
                '2026-12' => [2, '19800000.00', '-19800000.00', 'NOT_RECORDED'],
                '2027-01' => [1, '1100000.00', '-100000.00', 'CONFIRMED'],
                '2027-02' => [1, '1100000.00', '0.00', 'CONFIRMED'],
            ] as $period => [$count, $ppn, $difference, $status]
        ) {
            $this->getJson('/api/v1/tax-recaps/' . $period, $this->auth())->assertOk()
                ->assertJsonPath('data.recap.invoiceCount', $count)->assertJsonPath('data.recap.totalPpn', $ppn)
                ->assertJsonPath('data.recap.paymentDifference', $difference)->assertJsonPath('data.recap.paymentStatus', $status);
        }
        $this->getJson('/api/v1/tax-recaps/2026-10', $this->auth())->assertJsonPath('data.recap.totalPpnbm', '0.00')->assertJsonCount(2, 'data.payments');
        $this->assertSame(41, count(Storage::disk('local')->allFiles('tax')));
        foreach (FakturPajak::all() as $invoice) {
            Storage::disk('local')->assertExists(substr($invoice->pdf_uri, strlen('/files/')));
            $pdf = app(EFakturPdfParser::class)->parse(Storage::disk('local')->get(substr($invoice->pdf_uri, strlen('/files/'))));
            $this->assertSame($invoice->tax_invoice_date->format('Y-m-d'), $pdf->taxInvoiceDate);
            $this->assertSame($invoice->tax_period, $pdf->taxPeriod);
            $this->assertSame(DemoDataset::invoices()[$invoice->invoice_id]['invoiceDate'], $pdf->taxInvoiceDate);
        }
        foreach (PembayaranPajak::all() as $payment) {
            Storage::disk('local')->assertExists(substr($payment->e_billing_uri, strlen('/files/')));
            if ($payment->status === 'CONFIRMED') {
                $this->assertNotNull($payment->ntpn);
                Storage::disk('local')->assertExists(substr($payment->payment_proof_uri, strlen('/files/')));
            }
        }

        $invoice = FakturPajak::where('invoice_id', 'inv-demo-sample')->firstOrFail();
        $this->patchJson('/api/v1/tax-invoices/' . $invoice->tax_invoice_id, ['changes' => ['ppnbm' => '500.00'], 'note' => 'Edit pengguna'], $this->auth())->assertOk();
        $snapshot = [];
        foreach (['faktur_pajak', 'item_faktur', 'riwayat_perubahan_faktur', 'pembayaran_pajak', 'rekap_pajak_bulanan'] as $table) {
            $snapshot[$table] = DB::table($table)->get()->toJson();
        }
        $files = Storage::disk('local')->allFiles();
        $this->seed(DemoSeeder::class);
        foreach ($snapshot as $table => $json) {
            $this->assertSame($json, DB::table($table)->get()->toJson(), $table . ' tidak boleh diubah saat seeding ulang');
        }
        $this->assertSame($files, Storage::disk('local')->allFiles());
    }

    public function test_pdf_siap_pakai_bisa_diekstrak_disimpan_dan_diganti_tanpa_konflik_nsfp(): void
    {
        $relative = 'storage/framework/testing/demo-samples-' . Str::uuid();
        $directory = base_path($relative);
        try {
            $this->artisan('demo:samples', ['--path' => $relative])->assertSuccessful();
            $this->assertCount(11, glob($directory . '/*.pdf'));
            $manifest = json_decode(file_get_contents($directory . '/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertCount(2, $manifest);
            $this->assertCount(2, array_unique(array_column($manifest, 'nsfp')));
            $validation = json_decode(file_get_contents($directory . '/validation.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('PASSED', $validation['result']);
            $this->assertSame(9, $validation['attachmentCount']);
            $invoiceId = 'inv-demo-sample';
            $original = $this->post('/api/v1/tax-invoices/outgoing', [
                'invoiceId' => $invoiceId,
                'eFakturFile' => $this->pdfUpload(file_get_contents($directory . '/efaktur-' . $invoiceId . '.pdf')),
            ], $this->auth())->assertOk();
            $replacementFile = 'efaktur-' . $invoiceId . '-pengganti.pdf';
            $replacement = $this->post('/api/v1/tax-invoices/outgoing', [
                'invoiceId' => $invoiceId,
                'replacesTaxInvoiceId' => $original->json('data.taxInvoiceId'),
                'eFakturFile' => $this->pdfUpload(file_get_contents($directory . '/' . $replacementFile)),
            ], $this->auth())->assertOk()->assertJsonPath('data.status', 'REPLACEMENT');
            $this->getJson('/api/v1/tax-invoices/' . $original->json('data.taxInvoiceId'), $this->auth())
                ->assertJsonPath('data.replacedByTaxInvoiceId', $replacement->json('data.taxInvoiceId'));
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
        $this->artisan('db:seed', ['--class' => DemoSeeder::class, '--force' => true])->assertSuccessful();
        $this->app['env'] = 'testing';
        config(['e1.d2.driver' => 'http']);
        $this->seed(DemoSeeder::class);
        $this->assertDatabaseCount('faktur_pajak', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_pdf_generator_uses_current_d2_invoice_dates_amounts_and_nsfp_year(): void
    {
        $relative = 'storage/framework/testing/demo-check-' . Str::uuid();
        $directory = base_path($relative);
        try {
            $this->artisan('demo:samples', ['--path' => $relative])->assertSuccessful();
            $catalog = DemoDataset::invoices();
            $manifest = json_decode(file_get_contents($directory . '/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertCount(1, $catalog);
            foreach ($manifest as $sample) {
                $reference = $catalog[$sample['invoiceId']];
                $this->assertSame($reference['invoiceDate'], $sample['invoiceDate']);
                $this->assertSame(substr($reference['invoiceDate'], 0, 7), $sample['taxPeriod']);
                $this->assertSame(substr($reference['invoiceDate'], 2, 2), substr($sample['nsfp'], 8, 2));
                $this->assertSame(hash_file('sha256', $directory . '/' . $sample['file']), $sample['sha256']);
            }
            $sample = $manifest[0];
            $this->post('/api/v1/tax-invoices/extractions', [
                'invoiceId' => $sample['invoiceId'],
                'eFakturFile' => $this->pdfUpload(file_get_contents($directory . '/' . $sample['file'])),
            ], $this->auth())->assertOk()
                ->assertJsonPath('data.taxInvoiceDate', $sample['invoiceDate'])
                ->assertJsonPath('data.taxPeriod', $sample['taxPeriod'])
                ->assertJsonPath('data.taxInvoiceNumber', $sample['nsfp'])
                ->assertJsonPath('data.dpp', $sample['dpp'])
                ->assertJsonPath('data.ppn', $sample['ppn'])
                ->assertJsonPath('data.ppnbm', $sample['ppnbm'])
                ->assertJsonCount($sample['itemCount'], 'data.items');
            $this->assertDatabaseCount('faktur_pajak', 0);
            $this->assertDatabaseCount('riwayat_perubahan_faktur', 0);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_empty_d2_catalog_fails_before_sample_files_are_published(): void
    {
        $relative = 'storage/framework/testing/empty-d2-' . Str::uuid();
        $directory = base_path($relative);
        try {
            \App\Models\Invoice::query()->delete();
            $this->artisan('demo:samples', ['--path' => $relative])->assertFailed();
            $this->assertDirectoryDoesNotExist($directory);
            $this->assertDatabaseCount('faktur_pajak', 0);
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
