<?php

namespace Tests\Support;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\TaxParameter;
use App\Models\Termin;
use App\Services\Auth\F1Identity;
use App\Support\Demo\DemoFixtureWriter;
use Database\Seeders\RestoredD2DemoSeeder;
use Database\Seeders\Support\DemoDataset;
use Database\Seeders\Support\DemoSampleValidator;
use Database\Seeders\Support\FakeEFakturPdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class BrowserDemoSeeder extends Seeder
{
    public function run(DemoFixtureWriter $writer, DemoSampleValidator $validator): void
    {
        if (! app()->environment('testing') || ! str_ends_with(DB::connection()->getDatabaseName(), '_test')) {
            throw new RuntimeException('Browser fixtures require a separate testing database.');
        }
        $this->call(RestoredD2DemoSeeder::class);
        $fixtures = DemoDataset::invoices();
        $fixtures['inv-demo-004']['cancel'] = true;
        $payments = array_values(array_filter(DemoDataset::payments(), fn (array $row): bool => $row['taxPeriod'] === '2026-11'));
        foreach ($payments as &$payment) {
            unset($payment['ntpn']);
        }
        unset($payment);
        $writer->write($fixtures, $payments, new F1Identity('AKN-0002', 'Admin'));

        $rows = json_decode(file_get_contents(database_path('data/d2-invoices.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($rows as $row) {
            $invoice = Invoice::find($row['invoiceId']);
            if ($invoice !== null) {
                $invoice->termin->contract->update(['project_name' => $row['projectName'], 'client_id' => $row['clientId'], 'client_name' => $row['clientName']]);

                continue;
            }
            $contract = Contract::firstOrCreate(['contract_id' => 'ktr-browser-'.$row['invoiceId']], [
                'project_id' => $row['projectId'], 'contract_number' => 'BROWSER/'.$row['invoiceNumber'],
                'contract_value' => $row['totalAmount'], 'created_by' => 'browser-fixture',
            ]);
            $contract->update(['project_name' => $row['projectName'], 'client_id' => $row['clientId'], 'client_name' => $row['clientName']]);
            $termin = Termin::create([
                'termin_id' => 'trm-browser-'.$row['invoiceId'], 'contract_id' => $contract->contract_id,
                'termin_number' => 1, 'termin_value' => $row['dppAmount'], 'due_date' => $row['invoiceDate'], 'status' => 'SUDAH_DITAGIHKAN',
            ]);
            TaxParameter::firstOrCreate(['tax_param_id' => $row['taxParamId']], [
                'contract_id' => null, 'ppn_rate' => 11, 'pph_rate' => 2, 'effective_from' => '2026-01-01',
            ]);
            Invoice::create([
                'invoice_id' => $row['invoiceId'], 'invoice_number' => $row['invoiceNumber'], 'termin_id' => $termin->termin_id,
                'tax_param_id' => $row['taxParamId'], 'invoice_date' => $row['invoiceDate'], 'dpp_amount' => $row['dppAmount'],
                'ppn_amount' => $row['ppnAmount'], 'pph_amount' => $row['pphAmount'], 'total_amount' => $row['totalAmount'],
                'status' => $row['status'], 'created_by' => 'browser-fixture',
            ]);
        }
        $this->command->call('demo:samples', ['--path' => 'storage/framework/testing/e1-browser-samples']);
        $folder = storage_path('framework/testing/e1-browser-samples');
        // PPnBM is an explicit PDF test input, separate from the D-2 commercial reference.
        $fixture = DemoDataset::invoices(invoiceIds: ['inv-manual-ppnbm-001'])['inv-manual-ppnbm-001'];
        $fixture['pdf']['ppnbm'] = '750000000.00';
        $binary = FakeEFakturPdf::make($fixture['pdf']);
        $validator->validate($binary, $fixture);
        File::put($folder.'/'.$fixture['file'], $binary);
        $manifest = json_decode(File::get($folder.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($manifest as &$sample) {
            if ($sample['file'] === $fixture['file']) {
                $sample['ppnbm'] = '750000000.00';
                $sample['sha256'] = hash('sha256', $binary);
            }
        }
        unset($sample);
        File::put($folder.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        File::copy(glob($folder.'/e-billing-2026-11-*.pdf')[0], $folder.'/e-billing.pdf');
        File::copy(glob($folder.'/bukti-pembayaran-2026-11-*.pdf')[0], $folder.'/bukti-pembayaran.pdf');
    }
}
