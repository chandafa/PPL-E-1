<?php

namespace App\Console\Commands;

use App\Models\FakturPajak;
use Illuminate\Console\Command;

class AuditTaxPeriods extends Command
{
    protected $signature = 'tax:audit-periods';

    protected $description = 'Periksa perbedaan Masa Pajak dan tanggal e-Faktur tanpa mengubah data';

    public function handle(): int
    {
        $count = 0;
        foreach (FakturPajak::query()->whereRaw("tax_period <> to_char(tax_invoice_date, 'YYYY-MM')")->cursor() as $faktur) {
            $this->warn($faktur->tax_invoice_id.' | '.$faktur->invoice_id.' | tanggal '.$faktur->tax_invoice_date->format('Y-m-d').' | Masa Pajak '.$faktur->tax_period);
            $count++;
        }
        $this->info("Ditemukan {$count} faktur dengan periode berbeda. Tidak ada data yang diubah.");
        if ($count > 0) {
            $this->warn('Tinjau PDF, riwayat koreksi, dan aturan pengganti sebelum melakukan koreksi.');
        }

        return $count > 0 ? self::FAILURE : self::SUCCESS;
    }
}
