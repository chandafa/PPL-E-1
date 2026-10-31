<?php

namespace Tests\Feature;

use App\Support\Integration\Launcher;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Mockery;
use RuntimeException;

class IntegrationCommandTest extends TestCase
{
    private function launcher(?string $executable = 'launcher'): void
    {
        $finder = Mockery::mock(Launcher::class);
        $expectation = $finder->shouldReceive('command')->once();
        if ($executable === null) {
            $expectation->andThrow(new RuntimeException('Launcher tidak tersedia'));
        } else {
            $expectation->andReturn(['launcher']);
        }
        $this->app->instance(Launcher::class, $finder);
    }

    public function test_start_runs_existing_setup_and_launcher_in_order(): void
    {
        $this->launcher();
        Process::fake();

        $this->artisan('integration')->assertSuccessful();

        Process::assertRanInOrder([
            [PHP_BINARY, 'artisan', 'config:clear'],
            [PHP_BINARY, 'scripts/enable-integration.php'],
            ['launcher'],
        ]);
        Process::assertRanTimes(fn (PendingProcess $process) => $process->path === base_path() && $process->timeout === null, 3);
    }

    public function test_setup_failure_prevents_start_and_preserves_exit_code(): void
    {
        $this->launcher();
        Process::fake([
            '*' => Process::sequence()->push(Process::result())->push(Process::result(errorOutput: 'Setup gagal', exitCode: 7)),
        ]);

        $this->artisan('integration')->assertExitCode(7);

        Process::assertRanInOrder([
            [PHP_BINARY, 'artisan', 'config:clear'],
            [PHP_BINARY, 'scripts/enable-integration.php'],
        ]);
    }

    public function test_unsupported_platform_does_not_run_setup_or_stop(): void
    {
        Process::fake();
        $this->launcher(null);
        $this->artisan('integration')->assertFailed();

        $this->launcher(null);
        $this->artisan('integration:stop')->assertFailed();

        Process::assertNothingRan();
    }

    public function test_stop_delegates_to_existing_launcher_and_preserves_failure(): void
    {
        $this->launcher();
        Process::fake(['*' => Process::result(exitCode: 9)]);

        $this->artisan('integration:stop')->assertExitCode(9);

        Process::assertRan(fn (PendingProcess $process) => $process->command === [
            'launcher',
        ]);
        Process::assertRanTimes(fn () => true, 1);
    }
}
