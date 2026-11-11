<?php

namespace Tests\Unit;

use App\Support\Integration\Runtime;
use App\Support\Integration\RuntimeSetup;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RuntimeSetupTest extends TestCase
{
    private string $repo;

    protected function setUp(): void
    {
        $this->repo = sys_get_temp_dir().'/e1-runtime-setup-'.bin2hex(random_bytes(6));
        mkdir($this->repo.'/storage/app/private/integration/runtime', 0700, true);
        file_put_contents($this->repo.'/.env', "APP_ENV=local\nAPP_KEY=original-key\nDB_DATABASE=original_database\nDB_PASSWORD=original-password\nLARAVEL_STORAGE_PATH=original-storage\nCUSTOM_SETTING=preserved\n");
        Runtime::save($this->repo, [
            'directory' => $this->repo.'/storage/app/private/integration/runtime',
            'jwtSecret' => 'existing-runtime-secret',
            'd2' => ['database' => 'existing_d2_database', 'storage' => 'existing_d2_storage'],
        ]);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->repo, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->repo);
    }

    public function test_setup_reuses_runtime_and_preserves_application_database_storage_and_key(): void
    {
        $before = Dotenv::parse(file_get_contents($this->repo.'/.env'));
        $runtime = (new RuntimeSetup)->configure($this->repo, 'http', []);
        $after = Dotenv::parse(file_get_contents($this->repo.'/.env'));
        foreach ($before as $key => $value) {
            $this->assertSame($value, $after[$key], $key);
        }
        $this->assertSame('existing_d2_database', $runtime['d2']['database']);
        $this->assertSame('existing-runtime-secret', $after['F1_JWT_SECRET']);
        $this->assertSame('http', $after['D2_DRIVER']);
        $this->assertSame('false', $after['E1_DEV_LOGIN']);
        $this->assertFileDoesNotExist($this->repo.'/.env.integration');
        $this->assertCount(1, glob($runtime['directory'].'/main-before-enable-*.env'));
        $this->assertSame(0600, fileperms(glob($runtime['directory'].'/main-before-enable-*.env')[0]) & 0777);

        $environment = file_get_contents($this->repo.'/.env');
        $this->assertSame($runtime, (new RuntimeSetup)->configure($this->repo, 'http', []));
        $this->assertSame($environment, file_get_contents($this->repo.'/.env'));
        $this->assertCount(1, glob($runtime['directory'].'/main-before-enable-*.env'));
    }

    public function test_driver_switch_only_changes_connection_profile(): void
    {
        $setup = new RuntimeSetup;
        $runtime = $setup->configure($this->repo, 'http', []);
        $this->assertSame($runtime, $setup->configure($this->repo, 'mock', []));
        $this->assertSame('mock', Dotenv::parse(file_get_contents($this->repo.'/.env'))['D2_DRIVER']);
    }

    public function test_production_rejects_setup_before_writing_anything(): void
    {
        $environment = "APP_ENV=production\nAPP_KEY=preserved\n";
        file_put_contents($this->repo.'/.env', $environment);
        try {
            (new RuntimeSetup)->configure($this->repo, 'http', []);
            $this->fail('Production setup should fail');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('APP_ENV=local', $error->getMessage());
        }
        $this->assertSame($environment, file_get_contents($this->repo.'/.env'));
    }
}
