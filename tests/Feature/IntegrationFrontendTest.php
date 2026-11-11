<?php

namespace Tests\Feature;

use App\Support\Integration\Installer;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Process;

class IntegrationFrontendTest extends TestCase
{
    public function test_missing_manifest_builds_with_a_path_containing_spaces(): void
    {
        $repo = sys_get_temp_dir().'/E1 Windows Linux '.bin2hex(random_bytes(5));
        mkdir($repo.'/public/build', 0700, true);
        Process::fake(function () use ($repo) {
            file_put_contents($repo.'/public/build/manifest.json', '{}');

            return Process::result();
        });
        try {
            (new Installer)->prepareFrontend($repo);
            Process::assertRan(function ($process) use ($repo) {
                $expected = PHP_OS_FAMILY === 'Linux'
                    ? ['bash', 'scripts/integration-node.sh', $repo, 'build']
                    : ['powershell', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', 'scripts/integration-node.ps1', '-Repository', $repo, '-Build'];

                return $process->command === $expected && $process->path === $repo;
            });
            (new Installer)->prepareFrontend($repo);
            Process::assertRanTimes(fn () => true, 1);
        } finally {
            @unlink($repo.'/public/build/manifest.json');
            rmdir($repo.'/public/build');
            rmdir($repo.'/public');
            rmdir($repo);
        }
    }
}
