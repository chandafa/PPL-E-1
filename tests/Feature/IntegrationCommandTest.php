<?php

namespace Tests\Feature;

use App\Support\Integration\Installer;
use App\Support\Integration\Launcher;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Process;
use Mockery;
use RuntimeException;

class IntegrationCommandTest extends TestCase
{
    private function launcher(bool $stop = false): void
    {
        $launcher = Mockery::mock(Launcher::class);
        $launcher->shouldReceive('command')->once()->with($stop, ...($stop ? [] : ['http']))->andReturn(['launcher']);
        $launcher->shouldReceive('lock')->once()->andReturn(tmpfile());
        $this->app->instance(Launcher::class, $launcher);
    }

    public function test_default_command_only_sets_up_http_integration(): void
    {
        $this->launcher();
        $installer = Mockery::mock(Installer::class);
        $installer->shouldReceive('setup')->once()->with(base_path(), 'http');
        $installer->shouldNotReceive('ready');
        $installer->shouldNotReceive('seedTaxInvoices');
        $this->app->instance(Installer::class, $installer);
        Process::fake();
        $this->artisan('integration')->assertSuccessful();
        Process::assertNothingRan();
    }

    public function test_run_repairs_missing_data_before_launching_services(): void
    {
        $this->launcher();
        $installer = Mockery::mock(Installer::class);
        $installer->shouldReceive('ready')->once()->with(base_path(), 'http')->andReturn([]);
        $installer->shouldReceive('repair')->once()->with(base_path(), 'http');
        $installer->shouldReceive('seedTaxInvoices')->once()->with(base_path(), 'http');
        $installer->shouldNotReceive('setup');
        $this->app->instance(Installer::class, $installer);
        Process::fake();
        $this->artisan('integration', ['action' => 'run'])->assertSuccessful();
        Process::assertRanTimes(fn ($process) => $process->command === ['launcher'], 1);
    }

    public function test_incomplete_setup_prevents_run(): void
    {
        $this->launcher();
        $installer = Mockery::mock(Installer::class);
        $installer->shouldReceive('ready')->andThrow(new RuntimeException('Jalankan composer integration terlebih dahulu.'));
        $installer->shouldNotReceive('setup');
        $installer->shouldNotReceive('repair');
        $this->app->instance(Installer::class, $installer);
        Process::fake();
        $this->artisan('integration', ['action' => 'run'])->assertFailed();
        Process::assertNothingRan();
    }

    public function test_failed_launcher_does_not_seed_tax_invoices(): void
    {
        $this->launcher();
        $installer = Mockery::mock(Installer::class);
        $installer->shouldReceive('ready')->once()->andReturn([]);
        $installer->shouldReceive('repair')->once();
        $installer->shouldNotReceive('seedTaxInvoices');
        $this->app->instance(Installer::class, $installer);
        Process::fake(['*' => Process::result(exitCode: 9)]);
        $this->artisan('integration', ['action' => 'run'])->assertExitCode(9);
    }

    public function test_setup_failure_preserves_exit_code_without_starting_services(): void
    {
        $this->launcher();
        $installer = Mockery::mock(Installer::class);
        $installer->shouldReceive('setup')->andThrow(new RuntimeException('Seed gagal', 8));
        $this->app->instance(Installer::class, $installer);
        Process::fake();
        $this->artisan('integration')->assertExitCode(8);
        Process::assertNothingRan();
    }

    public function test_failed_data_repair_prevents_service_start(): void
    {
        $this->launcher();
        $installer = Mockery::mock(Installer::class);
        $installer->shouldReceive('ready')->once()->andReturn([]);
        $installer->shouldReceive('repair')->once()->andThrow(new RuntimeException('Pemulihan seed gagal', 7));
        $this->app->instance(Installer::class, $installer);
        Process::fake();
        $this->artisan('integration', ['action' => 'run'])->assertExitCode(7);
        Process::assertNothingRan();
    }

    public function test_mock_is_an_explicit_setup_option(): void
    {
        $launcher = Mockery::mock(Launcher::class);
        $launcher->shouldReceive('command')->with(false, 'mock')->andReturn(['launcher']);
        $launcher->shouldReceive('lock')->andReturn(tmpfile());
        $this->app->instance(Launcher::class, $launcher);
        $installer = Mockery::mock(Installer::class);
        $installer->shouldReceive('setup')->once()->with(base_path(), 'mock');
        $this->app->instance(Installer::class, $installer);
        Process::fake();
        $this->artisan('integration', ['--d2' => 'mock'])->assertSuccessful();
        Process::assertNothingRan();
    }

    public function test_invalid_action_and_driver_do_not_change_configuration(): void
    {
        Process::fake();
        $this->artisan('integration', ['action' => 'invalid'])->assertFailed();
        $this->artisan('integration', ['--d2' => 'unknown'])->assertFailed();
        Process::assertNothingRan();
    }

    public function test_stop_preserves_launcher_failure(): void
    {
        $this->launcher(true);
        Process::fake(['*' => Process::result(exitCode: 9)]);
        $this->artisan('integration:stop')->assertExitCode(9);
        Process::assertRanTimes(fn ($process) => $process->command === ['launcher'], 1);
    }

    public function test_shared_lock_rejects_a_second_command(): void
    {
        $launcher = $this->app->make(Launcher::class);
        $lock = $launcher->lock();
        Process::fake();
        try {
            $this->artisan('integration')->assertFailed();
            $this->artisan('integration:stop')->assertFailed();
            Process::assertNothingRan();
        } finally {
            fclose($lock);
        }
    }
}
