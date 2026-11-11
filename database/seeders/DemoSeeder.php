<?php

namespace Database\Seeders;

use App\Services\Auth\F1Identity;
use App\Support\Demo\DemoFixtureWriter;
use Database\Seeders\Support\DemoDataset;
use Illuminate\Database\Seeder;

/** Data demo aditif: penyimpanan ulang tidak mereset koreksi, pembayaran, atau riwayat. */
class DemoSeeder extends Seeder
{
    public function run(DemoFixtureWriter $writer): void
    {
        if (app()->environment(['local', 'testing']) && config('e1.d2.driver') === 'http') {
            $this->call(TaxInvoiceSeeder::class);

            return;
        }
        if (app()->isProduction() || config('e1.d2.driver') !== 'mock') {
            $this->command?->warn('DemoSeeder hanya tersedia di luar production dengan D2_DRIVER=mock.');

            return;
        }
        if (config('e1.d2.mock_dataset') !== 'legacy') {
            throw new \RuntimeException('Dataset legacy hanya tersedia dengan D2_MOCK_DATASET=legacy pada database terpisah.');
        }
        $this->call(RestoredD2DemoSeeder::class);
        $counts = $writer->write(DemoDataset::invoices(), DemoDataset::payments(), new F1Identity('AKN-0002', 'Admin'));
        $this->command?->info("Demo E-1: {$counts['invoices']} faktur dan {$counts['payments']} pembayaran ditambahkan.");
    }
}
