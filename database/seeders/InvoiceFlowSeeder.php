<?php

namespace Database\Seeders;

use App\Models\FakturPajak;
use Database\Seeders\Support\FakeEFakturPdf;
use Database\Seeders\Support\InvoiceFlowDataset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class InvoiceFlowSeeder extends Seeder
{
    public ?string $batch = null;

    public ?string $outputRoot = null;

    public function run(): void
    {
        if (app()->isProduction() || config('e1.d2.driver') !== 'mock') {
            throw new RuntimeException('Seed flow hanya tersedia di luar production dengan D2_DRIVER=mock.');
        }
        $batch = $this->batch ?? now('Asia/Jakarta')->format('Ymd-His').'-'.Str::lower(Str::random(4));
        if (! preg_match('/^[a-z0-9][a-z0-9-]{0,31}$/D', $batch)) {
            throw new RuntimeException('Batch harus 1–32 huruf kecil, angka, atau tanda hubung.');
        }
        $source = (string) config('e1.d2.mock_file');
        $original = File::get($source);
        $references = json_decode($original, true, flags: JSON_THROW_ON_ERROR);
        do {
            $package = InvoiceFlowDataset::make($batch, random_int(10000000, 89999990));
            $numbers = array_column(array_column($package['samples'], 'pdf'), 'nsfp');
        } while (FakturPajak::query()->whereIn('tax_invoice_number', $numbers)->exists());
        $ids = array_column($package['references'], 'invoiceId');
        if (array_intersect($ids, array_column($references, 'invoiceId')) || FakturPajak::query()->whereIn('invoice_id', $ids)->exists()) {
            throw new RuntimeException('Batch sudah ada atau pernah dipakai. Gunakan batch baru; data lama dipertahankan.');
        }
        $directory = ($this->outputRoot ?? base_path('docs/testing-invoice-flow')).'/'.$batch;
        if (File::exists($directory)) {
            throw new RuntimeException('Folder batch sudah ada; gunakan batch baru.');
        }
        File::makeDirectory($directory, 0775, true);
        try {
            $manifest = $package;
            unset($manifest['samples']);
            $manifest['documents'] = [];
            foreach ($package['samples'] as $name => $fixture) {
                File::put($directory.'/'.$name, FakeEFakturPdf::make($fixture['pdf']));
                $manifest['documents'][] = ['file' => $name, 'invoiceId' => $fixture['invoiceId'], 'flow' => $fixture['flow'],
                    'nsfp' => $fixture['pdf']['nsfp'], 'dpp' => $fixture['pdf']['dpp'], 'ppn' => $fixture['pdf']['ppn'], 'ppnbm' => $fixture['pdf']['ppnbm']];
            }
            File::put($directory.'/07-e-billing.pdf', FakeEFakturPdf::attachment('e-Billing Uji Flow (DUMMY)', [
                'Batch' => $batch, 'Masa pajak' => $package['taxPeriod'], 'ID Billing' => $package['billingId'], 'Jumlah setor' => 'Rp 4.070.000,00',
            ]));
            File::put($directory.'/08-bukti-pembayaran.pdf', FakeEFakturPdf::attachment('Bukti Pembayaran Uji Flow (DUMMY)', [
                'Batch' => $batch, 'NTPN' => $package['ntpn'], 'Jumlah setor' => 'Rp 4.070.000,00',
            ]));
            File::put($directory.'/09-bukan-efaktur.pdf', FakeEFakturPdf::attachment('Dokumen Uji Bukan e-Faktur (DUMMY)'));
            File::put($directory.'/10-bukan-pdf.txt', "Dokumen uji penolakan format, bukan PDF.\n");
            $manifest['attachments'] = ['07-e-billing.pdf', '08-bukti-pembayaran.pdf', '09-bukan-efaktur.pdf', '10-bukan-pdf.txt'];
            File::put($directory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
            $guide = str_replace(['{{batch}}', '{{projectId}}', '{{billingId}}', '{{ntpn}}'], [$batch, $package['projectId'], $package['billingId'], $package['ntpn']], File::get(base_path('docs/testing-invoice-flow/PANDUAN_TEMPLATE.md')));
            File::put($directory.'/PANDUAN.md', $guide);
            File::copy(base_path('docs/testing-invoice-flow/CHECKLIST_TEMPLATE.csv'), $directory.'/CHECKLIST.csv');
            if (File::get($source) !== $original) {
                throw new RuntimeException('Katalog D-2 berubah selama proses. Ulangi dengan batch baru.');
            }
            File::replace($source, json_encode(array_merge($references, $package['references']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        } catch (Throwable $exception) {
            File::deleteDirectory($directory);
            throw $exception;
        }
        $this->command?->info('Empat invoice D-2 baru disiapkan tanpa menyimpan faktur/pembayaran E-1.');
        $this->command?->info('Batch: '.$batch);
        $this->command?->info('Dokumen dan panduan: '.$directory);
    }
}
