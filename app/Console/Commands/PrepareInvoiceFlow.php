<?php

namespace App\Console\Commands;

use Database\Seeders\InvoiceFlowSeeder;
use Illuminate\Console\Command;
use Throwable;

class PrepareInvoiceFlow extends Command
{
    protected $signature = 'demo:flow {--batch= : Batch baru (huruf kecil, angka, tanda hubung)}';

    protected $description = 'Seed enam invoice D-2 lintas bulan/tahun, PDF upload, dan panduan flow pengujian';

    public function handle(): int
    {
        $seeder = new InvoiceFlowSeeder;
        $seeder->batch = $this->option('batch');
        $seeder->setCommand($this);
        try {
            $seeder->run();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
