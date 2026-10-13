<?php

namespace Database\Seeders;

use App\Models\FakturPajak;
use App\Services\Auth\F1Identity;
use App\Services\Tax\TaxInvoiceService;
use App\Services\Tax\TaxPaymentService;
use Database\Seeders\Support\FakeEFakturPdf;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Data demo FIKTIF konsisten dengan contoh pada openapi.yaml dan mock D-2
 * (database/data/d2-invoices.json). Seluruh data dibuat lewat layanan domain
 * yang sama dengan API, sehingga rekap dan riwayat ikut terbentuk.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Hasil: Nov 2026 = 3 faktur aktif (1 faktur diganti), pembayaran Draft;
 *        Des 2026 = 1 faktur aktif, 1 faktur dibatalkan, belum ada pembayaran.
 */
class DemoSeeder extends Seeder
{
    public function run(TaxInvoiceService $invoices, TaxPaymentService $payments): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoSeeder dilewati di production.');

            return;
        }

        // idempotent: kosongkan data E-1 (TRUNCATE tidak terkena trigger append-only baris)
        DB::statement('TRUNCATE riwayat_perubahan_faktur, item_faktur, pembayaran_pajak, rekap_pajak_bulanan, faktur_pajak RESTART IDENTITY CASCADE');

        $admin = new F1Identity('AKN-0002', 'Admin');
        $coo = new F1Identity('AKN-0005', 'COO');

        // --- Masa pajak 2026-11 ---
        // tanggal pada PDF 10 November lalu dikoreksi ke 1 November (sama dengan contoh riwayat API-KE01-06)
        $invoices->createOutgoing('inv-demo-001', $this->pdf([
            'nsfp' => '010.000-26.12345678', 'date' => '10 November 2026',
        ]), null, $admin);

        $invoices->createOutgoing('inv-demo-002', $this->pdf([
            'nsfp' => '010.000-26.12345679', 'date' => '5 November 2026',
            'items' => [
                ['code' => '620200', 'name' => 'Lisensi Aplikasi Gudang', 'amount' => '150000000.00'],
                ['code' => '620300', 'name' => 'Jasa Implementasi dan Pelatihan', 'amount' => '100000000.00'],
            ],
            'dpp' => '250000000.00', 'ppn' => '27500000.00',
        ]), null, $admin);

        $original = $invoices->createOutgoing('inv-demo-003', $this->pdf([
            'nsfp' => '010.000-26.12345680', 'date' => '12 November 2026',
            'items' => [['code' => '620200', 'name' => 'Jasa Integrasi Modul Gudang', 'amount' => '75000000.00']],
            'dpp' => '75000000.00', 'ppn' => '8250000.00',
        ]), null, $admin);

        // faktur pengganti: nilai dikoreksi; faktur asal menjadi non-aktif (tidak dihitung)
        $invoices->createOutgoing('inv-demo-003', $this->pdf([
            'nsfp' => '010.001-26.12345681', 'date' => '20 November 2026',
            'items' => [['code' => '620200', 'name' => 'Jasa Integrasi Modul Gudang (pengganti)', 'amount' => '70000000.00']],
            'dpp' => '70000000.00', 'ppn' => '7700000.00',
        ]), $original->tax_invoice_id, $admin);

        // koreksi data dengan riwayat sebelum/sesudah
        $first = FakturPajak::query()->where('tax_invoice_number', '010.000-26.12345678')->firstOrFail();
        $invoices->update($first->tax_invoice_id, ['taxInvoiceDate' => '2026-11-01'], 'Koreksi tanggal faktur', $coo);

        // pembayaran Draft/INCOMPLETE: total PPN aktif 11.000.000 + 27.500.000 + 7.700.000 = 46.200.000
        $payments->create([
            'taxPeriod' => '2026-11', 'taxYear' => 2026, 'amountPaid' => '46200000.00', 'billingId' => 'BILL-202611-001',
        ], $this->plainPdf('e-billing.pdf'), null, $admin);

        // --- Masa pajak 2026-12 ---
        $cancelled = $invoices->createOutgoing('inv-demo-004', $this->pdf([
            'nsfp' => '010.000-26.12345682', 'date' => '2 Desember 2026',
            'items' => [['code' => '620400', 'name' => 'Jasa Pemeliharaan Aplikasi Keuangan', 'amount' => '120000000.00']],
            'dpp' => '120000000.00', 'ppn' => '13200000.00',
        ]), null, $admin);
        $invoices->update($cancelled->tax_invoice_id, ['status' => 'CANCELLED'], 'Salah klien; diterbitkan ulang', $admin);

        $invoices->createOutgoing('inv-demo-005', $this->pdf([
            'nsfp' => '010.000-26.12345683', 'date' => '15 Desember 2026',
            'items' => [['code' => '620100', 'name' => 'Jasa Pengembangan Portal Termin 2', 'amount' => '60000000.00']],
            'dpp' => '60000000.00', 'ppn' => '6600000.00',
        ]), null, $admin);

        $this->command?->info('Data demo E-1 dibuat: 6 faktur (1 diganti, 1 dibatalkan), 1 pembayaran Draft pada 2026-11.');
    }

    /** @param array<string, mixed> $options */
    private function pdf(array $options): UploadedFile
    {
        return $this->upload(FakeEFakturPdf::make($options), 'e-faktur.pdf');
    }

    private function plainPdf(string $name): UploadedFile
    {
        return $this->upload(FakeEFakturPdf::unrelated(), $name);
    }

    private function upload(string $binary, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'e1seed');
        file_put_contents($path, $binary);

        // mode uji = true agar berkas lokal diterima sebagai unggahan
        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }
}
