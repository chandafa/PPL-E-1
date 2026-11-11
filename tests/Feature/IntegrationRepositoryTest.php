<?php

namespace Tests\Feature;

use App\Support\Integration\Installer;
use App\Support\Integration\Runtime;
use Illuminate\Foundation\Testing\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class IntegrationRepositoryTest extends TestCase
{
    private string $directory;

    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/e1-repository-test-'.bin2hex(random_bytes(6));
        mkdir($this->directory.'/upstream', 0700, true);
        $this->git(['init', '--initial-branch=main'], $this->directory.'/upstream');
        file_put_contents($this->directory.'/upstream/artisan', 'first');
        $this->commit();
        $this->spec = ['url' => $this->directory.'/upstream', 'branch' => 'main', 'required' => 'artisan'];
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    private function git(array $arguments, string $path): string
    {
        $process = new Process(array_merge(['git'], $arguments), $path);
        $process->mustRun();

        return trim($process->getOutput());
    }

    private function commit(): void
    {
        $this->git(['add', 'artisan'], $this->directory.'/upstream');
        $this->git(['-c', 'user.name=Integration Test', '-c', 'user.email=integration@example.invalid', 'commit', '-m', 'fixture'], $this->directory.'/upstream');
    }

    public function test_new_clones_follow_main_as_upstream_advances(): void
    {
        $installer = new Installer;
        $first = $installer->repository($this->directory.'/first', $this->spec);
        file_put_contents($this->directory.'/upstream/artisan', 'second');
        $this->commit();
        $second = $installer->repository($this->directory.'/second', $this->spec);

        $this->assertSame('main', $first['branch']);
        $this->assertSame('main', $second['branch']);
        $this->assertNotSame($first['revision'], $second['revision']);
        $this->assertSame($this->git(['rev-parse', 'HEAD'], $this->directory.'/upstream'), $second['revision']);
        $this->assertSame('main', $this->git(['symbolic-ref', '--short', 'HEAD'], $second['path']));
        foreach (Runtime::REPOSITORIES as $spec) {
            $this->assertSame('main', $spec['branch']);
            $this->assertArrayNotHasKey('revision', $spec);
        }
    }

    public function test_existing_main_checkout_is_reused_without_commit_pin_or_pull(): void
    {
        $installer = new Installer;
        $first = $installer->repository($this->directory.'/checkout', $this->spec);
        file_put_contents($this->directory.'/upstream/artisan', 'second');
        $this->commit();
        $again = $installer->repository($this->directory.'/checkout', $this->spec);

        $this->assertSame($first, $again);
        $this->assertSame('first', file_get_contents($again['path'].'/artisan'));
    }

    public function test_detached_checkout_is_rejected_without_resetting_it(): void
    {
        $installer = new Installer;
        $repository = $installer->repository($this->directory.'/checkout', $this->spec);
        $this->git(['checkout', '--detach', 'HEAD'], $repository['path']);
        try {
            $installer->repository($repository['path'], $this->spec);
            $this->fail('Expected detached checkout rejection');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('harus pada branch main', $error->getMessage());
        }
        $this->assertSame('HEAD', $this->git(['rev-parse', '--abbrev-ref', 'HEAD'], $repository['path']));
        $this->assertSame($repository['revision'], $this->git(['rev-parse', 'HEAD'], $repository['path']));
    }
}
