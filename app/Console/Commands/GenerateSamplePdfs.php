<?php

namespace App\Console\Commands;

use Database\Seeders\Support\DemoDataset;
use Database\Seeders\Support\FakeEFakturPdf;
use Illuminate\Console\Command;

class GenerateSamplePdfs extends Command
{
    protected $signature = 'demo:samples {--path=docs/postman/samples : folder keluaran relatif terhadap akar proyek}';

    protected $description = 'Buat PDF demo dan manifest invoice untuk pencatatan, pengganti, serta pembayaran';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Perintah ini dinonaktifkan di production.');

            return self::FAILURE;
        }
        $dir = base_path((string) $this->option('path'));
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error("Tidak dapat membuat folder {$dir}");

            return self::FAILURE;
        }
        $manifest = [];
        foreach (DemoDataset::samples() as $name => $fixture) {
            file_put_contents($dir.DIRECTORY_SEPARATOR.$name, FakeEFakturPdf::make($fixture['pdf']));
            $manifest[] = [
                'file' => $name, 'invoiceId' => $fixture['invoiceId'], 'nsfp' => $fixture['pdf']['nsfp'],
                'seededByDefault' => $fixture['seeded'], 'flow' => $fixture['flow'],
            ];
            $this->line("dibuat: {$name}");
        }
        file_put_contents($dir.'/e-billing.pdf', FakeEFakturPdf::attachment('e-Billing Demo'));
        file_put_contents($dir.'/bukti-pembayaran.pdf', FakeEFakturPdf::attachment('Bukti Pembayaran Demo'));
        file_put_contents($dir.'/bukan-pdf.txt', "Ini berkas teks biasa, bukan PDF.\n");
        file_put_contents($dir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        $this->info((count($manifest) + 2).' PDF, 1 berkas teks, dan manifest dibuat. Gunakan invoice 006/007 untuk pencatatan baru; petunjuk: docs/panduan/DEMO_SEEDER.md.');

        return self::SUCCESS;
    }
}
