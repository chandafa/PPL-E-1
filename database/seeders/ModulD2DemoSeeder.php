<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\TaxParameter;
use App\Models\Termin;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Adds the D-2 demo invoices used by the E-1 demo dataset.
 * The two October invoices 006 and 007 stay available for UI scenarios.
 */
class ModulD2DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() || config('e1.d2.driver') !== 'mock') {
            $this->command?->warn('ModulD2DemoSeeder hanya tersedia di luar production dengan D2_DRIVER=mock.');

            return;
        }

        $path = database_path('data/d2-invoices.json');
        $rows = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $fixtures = array_values(array_filter($rows, static function (array $row): bool {
            if (preg_match('/^inv-demo-([0-9]{3})$/D', $row['invoiceId'] ?? '', $matches) !== 1) {
                return false;
            }

            $number = (int) $matches[1];

            return ($number >= 1 && $number <= 5) || ($number >= 10 && $number <= 35);
        }));

        if (count($fixtures) !== 31) {
            throw new RuntimeException('Dataset D-2 demo harus memuat 31 invoice E-1; periksa database/data/d2-invoices.json.');
        }

        if (! TaxParameter::query()->whereKey('pjk-demo-001')->exists()) {
            throw new RuntimeException('Parameter pajak pjk-demo-001 harus diseed sebelum invoice D-2 demo.');
        }

        foreach ($fixtures as $fixture) {
            $invoiceId = $fixture['invoiceId'];
            $contractId = 'ktr-e1-demo-'.$invoiceId;
            $terminId = $fixture['terminId'];

            Contract::query()->updateOrCreate(
                ['contract_id' => $contractId],
                [
                    'project_id' => $fixture['projectId'],
                    'contract_number' => 'KTR/E1/DEMO/'.$fixture['invoiceNumber'],
                    'contract_value' => $fixture['totalAmount'],
                    'created_by' => 'e1-demo-seeder',
                ],
            );

            Termin::query()->updateOrCreate(
                ['termin_id' => $terminId],
                [
                    'contract_id' => $contractId,
                    'termin_number' => 1,
                    'termin_value' => $fixture['dppAmount'],
                    'due_date' => $fixture['invoiceDate'],
                    'status' => 'TERTAGIH',
                ],
            );

            Invoice::query()->updateOrCreate(
                ['invoice_id' => $invoiceId],
                [
                    'invoice_number' => $fixture['invoiceNumber'],
                    'termin_id' => $terminId,
                    'tax_param_id' => 'pjk-demo-001',
                    'invoice_date' => $fixture['invoiceDate'],
                    'dpp_amount' => $fixture['dppAmount'],
                    'ppn_amount' => $fixture['ppnAmount'],
                    'pph_amount' => $fixture['pphAmount'],
                    'total_amount' => $fixture['totalAmount'],
                    'status' => $fixture['status'],
                    'created_by' => 'e1-demo-seeder',
                ],
            );

            InvoiceHistory::query()->firstOrCreate(
                ['invoice_id' => $invoiceId, 'status' => $fixture['status']],
                [
                    'changed_at' => now(),
                    'changed_by' => 'e1-demo-seeder',
                    'note' => 'Invoice D-2 demo',
                ],
            );
        }

        $this->command?->info('31 invoice sumber D-2 untuk dataset demo E-1 tersedia; invoice 006/007 tetap tersedia untuk UI.');
    }
}
