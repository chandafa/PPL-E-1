<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\TaxParameter;
use App\Models\Termin;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ModulD2Seeder extends Seeder
{
    /**
     * Seed the D-2 reference data available in the source module.
     */
    public function run(): void
    {
        TaxParameter::updateOrCreate(
            ['tax_param_id' => 'pjk-demo-001'],
            [
                'contract_id' => null,
                'ppn_rate' => 11.00,
                'pph_rate' => 2.00,
                'effective_from' => '2026-01-01',
                'effective_to' => null,
            ]
        );

        foreach ([
            [
                'contract_id' => 'ktr-demo-001',
                'project_id' => 'prj-demo-001',
                'contract_number' => 'KTR/2026/001',
                'contract_value' => 500000000.00,
            ],
            [
                'contract_id' => 'ktr-demo-004',
                'project_id' => 'prj-demo-002',
                'contract_number' => 'KTR/2026/004',
                'contract_value' => 80000000.00,
            ],
            [
                'contract_id' => 'ktr-demo-sample',
                'project_id' => 'prj-demo-001',
                'contract_number' => 'KTR/2026/SAMPLE',
                'contract_value' => 200000000.00,
                'created_at' => Carbon::parse('2026-10-01 00:00:00'),
            ],
        ] as $contract) {
            Contract::updateOrCreate(
                ['contract_id' => $contract['contract_id']],
                $contract + [
                    'created_by' => 'emp-demo-007',
                    'created_at' => Carbon::parse('2026-10-08 03:15:00'),
                ]
            );
        }

        foreach ([
            [
                'termin_id' => 'trm-demo-001',
                'contract_id' => 'ktr-demo-001',
                'termin_number' => 1,
                'termin_value' => 100000000.00,
                'due_date' => '2026-10-15',
                'status' => 'SIAP_DITAGIH',
            ],
            [
                'termin_id' => 'trm-demo-002',
                'contract_id' => 'ktr-demo-001',
                'termin_number' => 2,
                'termin_value' => 150000000.00,
                'due_date' => '2026-12-15',
                'status' => 'SIAP_DITAGIH',
            ],
            [
                'termin_id' => 'trm-demo-003',
                'contract_id' => 'ktr-demo-004',
                'termin_number' => 1,
                'termin_value' => 80000000.00,
                'due_date' => '2026-10-20',
                'status' => 'SIAP_DITAGIH',
            ],
            [
                'termin_id' => 'trm-demo-sample',
                'contract_id' => 'ktr-demo-sample',
                'termin_number' => 1,
                'termin_value' => 100000000.00,
                'due_date' => '2026-10-10',
                'status' => 'SUDAH_DITAGIHKAN',
            ],
        ] as $termin) {
            Termin::updateOrCreate(
                ['termin_id' => $termin['termin_id']],
                $termin
            );
        }

        Invoice::updateOrCreate(
            ['invoice_id' => 'inv-demo-sample'],
            [
                'invoice_number' => 'INV-2026-SAMPLE',
                'termin_id' => 'trm-demo-sample',
                'tax_param_id' => 'pjk-demo-001',
                'invoice_date' => '2026-10-11',
                'dpp_amount' => 100000000.00,
                'ppn_amount' => 11000000.00,
                'pph_amount' => 2000000.00,
                'total_amount' => 109000000.00,
                'status' => 'TERTAGIH',
                'created_by' => 'emp-demo-012',
                'created_at' => Carbon::parse('2026-10-11 02:00:00'),
            ]
        );

        InvoiceHistory::updateOrCreate(
            ['invoice_id' => 'inv-demo-sample', 'status' => 'TERTAGIH'],
            [
                'changed_at' => Carbon::parse('2026-10-11 02:00:00'),
                'changed_by' => 'emp-demo-012',
                'note' => 'Invoice diterbitkan oleh petugas keuangan',
            ]
        );
    }
}
