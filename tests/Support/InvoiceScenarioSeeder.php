<?php

namespace Tests\Support;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Termin;
use Illuminate\Database\Seeder;

class InvoiceScenarioSeeder extends Seeder
{
    public function run(): void
    {
        Contract::updateOrCreate(
            ['contract_id' => 'ktr-test-simulated'],
            [
                'project_id' => 'prj-simulasi-yacht-001',
                'contract_number' => 'TEST/SIMULATED/001',
                'contract_value' => 1000000000.00,
                'created_by' => 'test-fixture',
            ]
        );

        foreach ([
            'inv-demo-001' => 'prj-demo-001',
            'inv-demo-002' => 'prj-demo-002',
            'inv-demo-003' => 'prj-demo-002',
            'inv-demo-004' => 'prj-demo-001',
            'inv-demo-005' => 'prj-demo-001',
            'inv-demo-006' => 'prj-demo-001',
            'inv-demo-007' => 'prj-demo-001',
            'inv-demo-010' => 'prj-demo-001',
            'inv-manual-001' => 'prj-simulasi-yacht-001',
            'inv-manual-ppnbm-001' => 'prj-simulasi-yacht-001',
            'inv-manual-jasa-001' => 'prj-simulasi-yacht-001',
        ] as $invoiceId => $projectId) {
            $contractId = Contract::query()
                ->where('project_id', $projectId)
                ->orderBy('contract_id')
                ->value('contract_id');
            if (! is_string($contractId)) {
                throw new \RuntimeException("Missing test contract for project {$projectId}.");
            }
            $terminId = 'trm-test-'.substr(hash('sha256', $invoiceId), 0, 24);

            Termin::updateOrCreate(
                ['termin_id' => $terminId],
                [
                    'contract_id' => $contractId,
                    'termin_number' => 1,
                    'termin_value' => 1000000.00,
                    'due_date' => '2026-10-10',
                    'status' => 'SUDAH_DITAGIHKAN',
                ]
            );

            Invoice::updateOrCreate(
                ['invoice_id' => $invoiceId],
                [
                    'invoice_number' => 'TEST-'.strtoupper(substr(hash('sha256', $invoiceId), 0, 12)),
                    'termin_id' => $terminId,
                    'tax_param_id' => 'pjk-demo-001',
                    'invoice_date' => '2026-10-11',
                    'dpp_amount' => 1000000.00,
                    'ppn_amount' => 110000.00,
                    'pph_amount' => 0.00,
                    'total_amount' => 1110000.00,
                    'status' => 'TERTAGIH',
                    'created_by' => 'test-fixture',
                ]
            );
        }
    }
}
