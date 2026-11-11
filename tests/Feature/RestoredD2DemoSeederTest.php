<?php

namespace Tests\Feature;

use App\Models\FakturPajak;
use Database\Seeders\IntegrationSnapshotSeeder;
use Database\Seeders\RestoredD2DemoSeeder;
use Database\Seeders\TaxInvoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RestoredD2DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_restores_historical_project_and_amount_and_creates_matching_e1_pdf(): void
    {
        config(['e1.d2.driver' => 'mock', 'e1.d2.mock_dataset' => 'legacy']);
        Storage::fake('local');
        $this->seed(RestoredD2DemoSeeder::class);
        $this->assertDatabaseCount('invoices', 31);
        $this->assertDatabaseHas('invoices', [
            'invoice_id' => 'inv-demo-004', 'invoice_number' => 'INV-2026-0004',
            'dpp_amount' => '120000000.00', 'ppn_amount' => '13200000.00',
            'pph_amount' => '2400000.00', 'total_amount' => '130800000.00',
        ]);
        $project = DB::table('invoices')->join('termins', 'invoices.termin_id', '=', 'termins.termin_id')
            ->join('contracts', 'termins.contract_id', '=', 'contracts.contract_id')
            ->where('invoice_id', 'inv-demo-004')->value('project_id');
        $this->assertSame('prj-demo-003', $project);
        $this->seed(TaxInvoiceSeeder::class);
        $invoice = FakturPajak::where('invoice_id', 'inv-demo-004')->firstOrFail();
        $this->assertSame('120000000.00', $invoice->dpp);
        $this->assertSame('13200000.00', $invoice->ppn);
        $this->assertSame('prj-demo-003', $invoice->project_id);
        Storage::disk('local')->assertExists(substr($invoice->pdf_uri, strlen('/files/')));
    }

    public function test_preserves_snapshot_collisions_and_user_changes_on_repeat_seed(): void
    {
        config(['e1.d2.driver' => 'mock', 'e1.d2.mock_dataset' => 'sqlite']);
        $this->seed(IntegrationSnapshotSeeder::class);
        $original = (array) DB::table('invoices')->where('invoice_id', 'inv-demo-001')->first();
        $this->seed(RestoredD2DemoSeeder::class);
        $this->assertSame($original, (array) DB::table('invoices')->where('invoice_id', 'inv-demo-001')->first());
        $this->assertDatabaseMissing('invoices', ['invoice_id' => 'inv-demo-002']);
        $this->assertDatabaseHas('invoices', ['invoice_id' => 'inv-demo-004', 'dpp_amount' => '120000000.00']);
        DB::table('invoices')->where('invoice_id', 'inv-demo-004')->update(['status' => 'TERBAYAR', 'total_amount' => '999.00']);
        $before = [];
        foreach (['contracts', 'termins', 'tax_parameters', 'invoices', 'invoice_histories'] as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }
        $this->seed(RestoredD2DemoSeeder::class);
        foreach ($before as $table => $json) {
            $this->assertSame($json, DB::table($table)->get()->toJson(), $table);
        }
    }
}
