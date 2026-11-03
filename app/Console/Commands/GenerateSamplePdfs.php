<?php

namespace App\Console\Commands;

use App\Services\EFaktur\EFakturPdfParser;
use Database\Seeders\Support\D2SourceDataset;
use Database\Seeders\Support\DemoDataset;
use Database\Seeders\Support\DemoSampleValidator;
use Database\Seeders\Support\FakeEFakturPdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

class GenerateSamplePdfs extends Command
{
    protected $signature = 'demo:samples {--path=docs/postman/samples : folder keluaran relatif terhadap akar proyek} {--dataset= : d2-source untuk dua invoice snapshot SQLite}';

    protected $description = 'Buat dan periksa PDF demo, tanggal invoice/e-Faktur, serta lampiran per masa pajak';

    public function handle(DemoSampleValidator $validator): int
    {
        if (app()->isProduction() || config('e1.d2.driver') !== 'mock') {
            $this->error('Perintah ini hanya tersedia di luar production dengan D2_DRIVER=mock.');

            return self::FAILURE;
        }
        try {
            $dataset = $this->option('dataset');
            if ($dataset !== null && $dataset !== 'd2-source') {
                throw new RuntimeException('Dataset tidak dikenal: '.$dataset);
            }
            $invoiceIds = $dataset === 'd2-source' ? array_column(D2SourceDataset::rows(), 'invoiceId') : null;
            if ($invoiceIds !== null && count(DemoDataset::invoices(invoiceIds: $invoiceIds)) !== count($invoiceIds)) {
                throw new RuntimeException('Jalankan D2SourceDemoSeeder terlebih dahulu; dua invoice snapshot SQLite harus tersedia.');
            }
            $manifest = $binaries = $attachments = [];
            $numbers = [];
            $samples = DemoDataset::samples($invoiceIds);
            if ($samples === []) {
                throw new RuntimeException('Katalog D-2 belum berisi invoice untuk dibuatkan sampel.');
            }
            foreach ($samples as $name => $fixture) {
                $binary = FakeEFakturPdf::make($fixture['pdf']);
                $result = $validator->validate($binary, $fixture);
                if (isset($numbers[$result['taxInvoiceNumber']])) {
                    throw new RuntimeException("NSFP ganda pada sampel {$name}.");
                }
                $numbers[$result['taxInvoiceNumber']] = true;
                $binaries[$name] = $binary;
                $manifest[] = [
                    'file' => $name, 'invoiceId' => $fixture['invoiceId'],
                    'invoiceNumber' => $fixture['invoiceNumber'], 'invoiceDate' => $fixture['invoiceDate'],
                    'nsfp' => $result['taxInvoiceNumber'], 'taxInvoiceDate' => $result['taxInvoiceDate'],
                    'taxPeriod' => $result['taxPeriod'], 'dpp' => $result['dpp'], 'ppn' => $result['ppn'],
                    'ppnbm' => $result['ppnbm'], 'itemCount' => $result['itemCount'],
                    'seededByDefault' => $fixture['seeded'], 'flow' => $fixture['flow'],
                    'sha256' => $result['sha256'], 'validation' => $result['validation'],
                ];
            }
            foreach (DemoDataset::paymentDocuments($invoiceIds) as $name => $document) {
                $binary = FakeEFakturPdf::attachment($document['title'], $document['fields']);
                $text = (new Parser)->parseContent($binary)->getText();
                foreach ($document['fields'] as $value) {
                    if (! str_contains($text, $value)) {
                        throw new RuntimeException("Lampiran {$name} tidak terbaca lengkap.");
                    }
                }
                $binaries[$name] = $binary;
                $attachments[] = ['file' => $name, 'fields' => $document['fields'], 'sha256' => hash('sha256', $binary), 'validation' => 'PASSED'];
            }
            // Publikasikan hanya setelah seluruh PDF lolos pembacaan ulang.
            $dir = base_path((string) $this->option('path'));
            if ($invoiceIds !== null && is_dir($dir)) {
                $unexpected = array_filter(File::files($dir), fn ($file) => $file->getExtension() === 'pdf' && ! isset($binaries[$file->getFilename()]));
                if ($unexpected !== []) {
                    $archive = $dir.'-legacy-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3));
                    if (! File::moveDirectory($dir, $archive)) {
                        throw new RuntimeException('Gagal memisahkan PDF dataset lama.');
                    }
                    $this->line('PDF lama dipertahankan di '.$archive);
                }
            }
            File::ensureDirectoryExists($dir);
            foreach ($binaries as $name => $binary) {
                File::replace($dir.DIRECTORY_SEPARATOR.$name, $binary);
            }
            File::replace($dir.'/bukan-pdf.txt', "Ini berkas teks biasa, bukan PDF.\n");
            File::replace($dir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
            File::replace($dir.'/validation.json', json_encode([
                'result' => 'PASSED',
                'generatedAtUtc' => now('UTC')->toIso8601String(),
                'parser' => EFakturPdfParser::class,
                'taxInvoiceCount' => count($manifest),
                'attachmentCount' => count($attachments),
                'datesMatchInvoice' => true,
                'nsfpYearsMatchTaxInvoice' => true,
                'attachments' => $attachments,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
            $this->info(count($manifest).' PDF e-Faktur dan '.count($attachments).' PDF lampiran berhasil dibuat dan dibaca ulang.');
            $this->line('Tanggal invoice, tanggal e-Faktur, masa pajak, tahun NSFP, nominal, dan seluruh item: SESUAI.');
            $this->line('Folder: '.$dir);
            $this->line('Pemetaan berkas: manifest.json; hasil pemeriksaan: validation.json.');
            $this->line('Pilih invoice yang tersedia di katalog D-2 untuk pencatatan. Panduan: docs/panduan/DEMO_SEEDER.md.');
        } catch (Throwable $exception) {
            $this->error('Pembuatan/pemeriksaan sampel gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
