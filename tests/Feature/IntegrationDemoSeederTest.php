<?php

namespace Tests\Feature;

use Database\Seeders\D2SourceDemoSeeder;
use Database\Seeders\IntegrationDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IntegrationDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['e1.d2.driver' => 'http']);
    }

    public function test_seeds_sample_without_overwriting_later_changes(): void
    {
        $this->seed(IntegrationDemoSeeder::class);
        DB::table('invoices')->where('invoice_id', 'inv-demo-sample')->update(['status' => 'LUNAS', 'total_amount' => '123.00']);
        $before = [];
        foreach (['contracts', 'tax_parameters', 'termins', 'invoices', 'invoice_histories'] as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }
        $this->seed(IntegrationDemoSeeder::class);
        foreach ($before as $table => $json) {
            $this->assertSame($json, DB::table($table)->get()->toJson());
        }
        $this->assertDatabaseHas('invoices', ['invoice_id' => 'inv-demo-sample', 'status' => 'LUNAS', 'total_amount' => '123.00']);
    }

    public function test_conflicting_identity_rolls_back_new_reference_rows(): void
    {
        $this->seed(IntegrationDemoSeeder::class);
        $contract = (array) DB::table('contracts')->first();
        $contract['contract_id'] = 'other-contract';
        $contract['contract_number'] = 'OTHER';
        DB::table('contracts')->insert($contract);
        DB::table('tax_parameters')->update(['contract_id' => 'other-contract']);
        DB::table('invoice_histories')->delete();
        DB::table('invoices')->delete();
        DB::table('termins')->delete();
        DB::table('contracts')->where('contract_id', 'ktr-demo-sample')->delete();
        try {
            $this->seed(IntegrationDemoSeeder::class);
            $this->fail('Expected identity conflict');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Konflik seed integrasi', $error->getMessage());
        }
        $this->assertDatabaseMissing('contracts', ['contract_id' => 'ktr-demo-sample']);
        $this->assertDatabaseHas('tax_parameters', ['contract_id' => 'other-contract']);
    }

    public function test_existing_sqlite_dataset_is_preserved_on_collision(): void
    {
        config(['e1.d2.driver' => 'mock', 'e1.d2.mock_dataset' => 'sqlite']);
        $this->seed(D2SourceDemoSeeder::class);
        $before = DB::table('invoices')->get()->toJson();
        config(['e1.d2.driver' => 'http']);
        try {
            $this->seed(IntegrationDemoSeeder::class);
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Konflik seed integrasi', $error->getMessage());
        }
        $this->assertSame($before, DB::table('invoices')->get()->toJson());
    }

    public function test_mock_mode_is_rejected(): void
    {
        config(['e1.d2.driver' => 'mock']);
        $this->expectException(RuntimeException::class);
        $this->seed(IntegrationDemoSeeder::class);
    }
}
