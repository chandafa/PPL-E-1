<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Support\Integration\Installer;
use App\Support\Integration\Runtime;
use App\Support\Integration\SeedRepair;
use Database\Seeders\IntegrationDemoSeeder;
use Database\Seeders\IntegrationSnapshotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class IntegrationRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_http_seed_restores_deleted_invoice_and_keeps_existing_amounts(): void
    {
        config(['e1.d2.driver' => 'http']);
        $this->seed(IntegrationDemoSeeder::class);
        DB::table('contracts')->update(['contract_value' => '999.00']);
        DB::table('invoice_histories')->delete();
        DB::table('invoices')->delete();
        $this->seed(IntegrationDemoSeeder::class);
        $this->assertDatabaseHas('invoices', ['invoice_id' => 'inv-demo-sample']);
        $this->assertDatabaseHas('contracts', ['contract_id' => 'ktr-demo-sample', 'contract_value' => '999.00']);
        $this->assertDatabaseCount('invoice_histories', 1);
    }

    public function test_snapshot_repair_fills_missing_rows_and_preserves_changed_invoice(): void
    {
        config(['e1.d2.driver' => 'mock', 'e1.d2.mock_dataset' => 'sqlite']);
        $this->seed(IntegrationSnapshotSeeder::class);
        DB::table('invoices')->where('invoice_id', 'inv-demo-001')->update(['total_amount' => '999.00', 'status' => 'TERBAYAR']);
        DB::table('invoice_histories')->where('invoice_id', 'inv-demo-sample')->delete();
        DB::table('invoices')->where('invoice_id', 'inv-demo-sample')->delete();
        $this->seed(IntegrationSnapshotSeeder::class);
        $this->assertDatabaseHas('invoices', ['invoice_id' => 'inv-demo-sample']);
        $this->assertDatabaseHas('invoices', ['invoice_id' => 'inv-demo-001', 'total_amount' => '999.00', 'status' => 'TERBAYAR']);
        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_old_seed_markers_do_not_skip_database_checks_and_repair(): void
    {
        $repo = sys_get_temp_dir().'/e1-repair-test-'.bin2hex(random_bytes(6));
        mkdir($repo.'/storage/app/private/integration/runtime', 0700, true);
        file_put_contents($repo.'/.env', "APP_ENV=local\nAPP_KEY=test\nDB_DATABASE=test\n");
        $directory = $repo.'/storage/app/private/integration/runtime';
        touch($directory.'/d2-setup.complete');
        Runtime::save($repo, [
            'directory' => $directory, 'd2' => ['key' => 'test', 'database' => 'test', 'storage' => $directory],
            'db' => ['host' => '127.0.0.1', 'port' => '5432', 'username' => 'test', 'password' => ''],
            'setup' => ['complete' => true, 'driver' => 'http', 'e1Seeded' => ['http'], 'repositories' => ['d2' => ['path' => '/fixture/d2']]],
        ]);
        Process::fake();
        try {
            (new Installer)->repair($repo, 'http');
            Process::assertRanInOrder([
                [PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'],
                [PHP_BINARY, 'artisan', 'db:seed', '--class=IntegrationDemoSeeder', '--force', '--no-interaction'],
                [PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'],
                [PHP_BINARY, $repo.'/scripts/seed-integration-d2.php', '/fixture/d2'],
            ]);
            $this->assertTrue(Runtime::read($repo)['setup']['complete']);
        } finally {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($repo, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($repo);
        }
    }

    public function test_upstream_seed_with_model_events_disabled_cannot_reset_existing_values(): void
    {
        config(['e1.d2.driver' => 'http']);
        $this->seed(IntegrationDemoSeeder::class);
        DB::table('invoices')->update(['total_amount' => '999.00', 'status' => 'TERBAYAR']);
        DB::table('invoice_histories')->delete();
        SeedRepair::run(DB::connection(), function (): void {
            Invoice::withoutEvents(function (): void {
                Invoice::updateOrCreate(['invoice_id' => 'inv-demo-sample'], ['total_amount' => '109000000.00', 'status' => 'TERTAGIH']);
                DB::table('invoice_histories')->insert([
                    'invoice_id' => 'inv-demo-sample', 'status' => 'TERTAGIH',
                    'changed_at' => '2026-10-11 02:00:00', 'changed_by' => 'test', 'note' => 'restored',
                ]);
            });
        });
        $this->assertDatabaseHas('invoices', ['invoice_id' => 'inv-demo-sample', 'total_amount' => '999.00', 'status' => 'TERBAYAR']);
        $this->assertDatabaseCount('invoice_histories', 1);
    }
}
