<?php

namespace App\Console\Commands;

use Database\Seeders\Support\FakeEFakturPdf;
use Illuminate\Console\Command;

/**
 * Membuat berkas PDF contoh (FIKTIF) untuk mencoba API lewat Postman:
 * e-Faktur untuk inv-demo-001..005, faktur pengganti, e-Billing, bukti
 * pembayaran, dan satu berkas bukan PDF untuk uji kesalahan.
 */
class GenerateSamplePdfs extends Command
{
    protected $signature = 'demo:samples {--path=docs/postman/samples : folder keluaran (relatif terhadap akar proyek)}';

    protected $description = 'Buat PDF contoh fiktif (e-Faktur, e-Billing, bukti pembayaran) untuk Postman';

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

        $files = [
            'efaktur-inv-demo-001.pdf' => FakeEFakturPdf::make([
                'nsfp' => '010.000-26.12345678', 'date' => '1 November 2026',
                'items' => [['code' => '620100', 'name' => 'Jasa Pengembangan Portal ERP Termin 1', 'amount' => '100000000.00']],
                'dpp' => '100000000.00', 'ppn' => '11000000.00',
            ]),
            'efaktur-inv-demo-002.pdf' => FakeEFakturPdf::make([
                'nsfp' => '010.000-26.12345679', 'date' => '5 November 2026',
                'items' => [
                    ['code' => '620200', 'name' => 'Lisensi Aplikasi Gudang', 'amount' => '150000000.00'],
                    ['code' => '620300', 'name' => 'Jasa Implementasi dan Pelatihan', 'amount' => '100000000.00'],
                ],
                'dpp' => '250000000.00', 'ppn' => '27500000.00', 'buyer' => 'PT Lintas Rel Nusantara',
            ]),
            'efaktur-inv-demo-003.pdf' => FakeEFakturPdf::make([
                'nsfp' => '010.000-26.12345680', 'date' => '12 November 2026',
                'items' => [['code' => '620200', 'name' => 'Jasa Integrasi Modul Gudang', 'amount' => '75000000.00']],
                'dpp' => '75000000.00', 'ppn' => '8250000.00', 'buyer' => 'PT Lintas Rel Nusantara',
            ]),
            'efaktur-inv-demo-003-pengganti.pdf' => FakeEFakturPdf::make([
                'nsfp' => '010.001-26.12345681', 'date' => '20 November 2026',
                'items' => [['code' => '620200', 'name' => 'Jasa Integrasi Modul Gudang (pengganti)', 'amount' => '70000000.00']],
                'dpp' => '70000000.00', 'ppn' => '7700000.00', 'buyer' => 'PT Lintas Rel Nusantara',
            ]),
            'efaktur-inv-demo-004.pdf' => FakeEFakturPdf::make([
                'nsfp' => '010.000-26.12345682', 'date' => '2 Desember 2026',
                'items' => [['code' => '620400', 'name' => 'Jasa Pemeliharaan Aplikasi Keuangan', 'amount' => '120000000.00']],
                'dpp' => '120000000.00', 'ppn' => '13200000.00', 'buyer' => 'PT Samudra Karya Digital',
            ]),
            'efaktur-inv-demo-005.pdf' => FakeEFakturPdf::make([
                'nsfp' => '010.000-26.12345683', 'date' => '15 Desember 2026',
                'items' => [['code' => '620100', 'name' => 'Jasa Pengembangan Portal ERP Termin 2', 'amount' => '60000000.00']],
                'dpp' => '60000000.00', 'ppn' => '6600000.00',
            ]),
            'e-billing.pdf' => FakeEFakturPdf::unrelated(),
            'bukti-pembayaran.pdf' => FakeEFakturPdf::unrelated(),
        ];

        foreach ($files as $name => $binary) {
            file_put_contents($dir.DIRECTORY_SEPARATOR.$name, $binary);
            $this->line("  dibuat: {$name}");
        }

        // bukan PDF (uji INVALID_FILE)
        file_put_contents($dir.DIRECTORY_SEPARATOR.'bukan-pdf.txt', "Ini berkas teks biasa, bukan PDF.\n");
        $this->line('  dibuat: bukan-pdf.txt');

        $this->info('Selesai: '.count($files).' PDF + 1 berkas teks di '.$dir);

        return self::SUCCESS;
    }
}
