<?php

namespace Tests\Unit;

use App\Support\Integration\Runtime;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class IntegrationRuntimeTest extends TestCase
{
    private string $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = sys_get_temp_dir().'/e1-runtime-test-'.bin2hex(random_bytes(6));
        foreach (['storage/app/private/integration/runtime', 'f1/demo', 'f1/node_modules/jsonwebtoken', 'd2/vendor'] as $directory) {
            mkdir($this->repo.'/'.$directory, 0700, true);
        }
        foreach (['f1/demo/local-server.js', 'f1/node_modules/jsonwebtoken/package.json', 'd2/artisan', 'd2/vendor/autoload.php'] as $file) {
            touch($this->repo.'/'.$file);
        }
        file_put_contents($this->repo.'/.env', "APP_ENV=local\nAPP_KEY=test-key\nDB_CONNECTION=pgsql\nDB_DATABASE=example\nD2_DRIVER=http\nF1_JWT_SECRET=test-secret\nF1_BASE_URL=http://127.0.0.1:4100\nD2_BASE_URL=http://127.0.0.1:8026\n");
        $directory = $this->repo.'/storage/app/private/integration/runtime';
        touch($directory.'/d2-setup.complete');
        Runtime::save($this->repo, [
            'directory' => $directory, 'jwtSecret' => 'test-secret', 'd2' => ['key' => 'test-key'],
            'setup' => [
                'complete' => true, 'driver' => 'http', 'target' => Runtime::target($this->repo),
                'profile' => Runtime::profile($this->repo),
                'repositories' => ['f1' => ['path' => $this->repo.'/f1'], 'd2' => ['path' => $this->repo.'/d2']],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->repo, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->repo);
        parent::tearDown();
    }

    public function test_completed_setup_is_ready_without_changing_manifest(): void
    {
        $before = Runtime::read($this->repo);
        $this->assertSame($before, Runtime::ready($this->repo, 'http'));
        $this->assertSame($before, Runtime::read($this->repo));
    }

    public function test_changed_profile_requires_setup_again(): void
    {
        file_put_contents($this->repo.'/.env', "F1_API_ENABLED=false\n", FILE_APPEND);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('konfigurasi berubah');
        Runtime::ready($this->repo, 'http');
    }

    public function test_interrupted_setup_is_rejected(): void
    {
        $runtime = Runtime::read($this->repo);
        $runtime['setup']['complete'] = false;
        Runtime::save($this->repo, $runtime);
        $this->expectException(RuntimeException::class);
        Runtime::ready($this->repo, 'http');
    }

    public function test_missing_dependency_is_rejected(): void
    {
        unlink($this->repo.'/d2/vendor/autoload.php');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Dependensi d2');
        Runtime::ready($this->repo, 'http');
    }

    public function test_missing_seed_marker_allows_data_repair_before_run(): void
    {
        unlink($this->repo.'/storage/app/private/integration/runtime/d2-setup.complete');
        $this->assertTrue(Runtime::ready($this->repo, 'http')['setup']['complete']);
    }

    public function test_driver_must_match_setup(): void
    {
        $this->expectException(RuntimeException::class);
        Runtime::ready($this->repo, 'mock');
    }

    public function test_database_target_is_independent_of_env_line_order(): void
    {
        $target = Runtime::target($this->repo);
        file_put_contents($this->repo.'/.env', "DB_DATABASE=example\nDB_CONNECTION=pgsql\n");
        $this->assertSame($target, Runtime::target($this->repo));
    }

    public function test_child_environment_pins_database_and_clears_inherited_url(): void
    {
        $environment = Runtime::e1Environment($this->repo, 'http');
        $this->assertSame('example', $environment['DB_DATABASE']);
        $this->assertSame('', $environment['DB_URL']);
        $this->assertSame('', $environment['DB_PASSWORD']);
        $this->assertSame('http', $environment['D2_DRIVER']);
    }
}
