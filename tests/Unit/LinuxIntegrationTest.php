<?php

namespace Tests\Unit;

use App\Support\Integration\Launcher;
use App\Support\Integration\LinuxProcesses;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

class LinuxIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (PHP_OS_FAMILY !== 'Linux' || ! function_exists('posix_kill')) {
            $this->markTestSkipped('Linux /proc dan posix diperlukan.');
        }
    }

    public function test_linux_commands_do_not_require_powershell(): void
    {
        $finder = $this->createMock(ExecutableFinder::class);
        $finder->expects($this->never())->method('find');
        $launcher = new Launcher($finder);

        $this->assertSame(['bash', 'scripts/start-integration.sh', PHP_BINARY], $launcher->command());
        $this->assertSame([PHP_BINARY, 'scripts/linux-integration.php', 'stop'], $launcher->command(true));
    }

    public function test_stale_identity_cannot_stop_an_unrelated_process(): void
    {
        $manager = new LinuxProcesses;
        $entry = ['id' => getmypid(), 'startedAt' => 'stale', 'port' => 0];

        $manager->stop($entry);

        $this->assertFalse($manager->owned($entry));
        $this->assertTrue($manager->owned(['id' => getmypid(), 'startedAt' => $manager->identity(getmypid())]));
    }

    public function test_detached_server_is_owned_and_can_be_stopped_without_touching_another_listener(): void
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            $this->markTestSkipped('Socket lokal tidak tersedia: '.$error);
        }
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $directory = sys_get_temp_dir().'/e1-linux-test-'.bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        $manager = new LinuxProcesses;
        $entry = null;
        try {
            $entry = $manager->start('fixture', [PHP_BINARY, '-S', '127.0.0.1:'.$port], $directory, $port, $directory);
            $manager->wait('fixture', $entry, $directory);
            $this->assertTrue($manager->owned($entry));
            $this->assertTrue($manager->listening($port, $entry['id']));
            $this->assertFalse($manager->listening($port, getmypid()));
            try {
                $manager->start('other', [PHP_BINARY, '-S', '127.0.0.1:'.$port], $directory, $port, $directory);
                $this->fail('Port yang sudah dipakai harus ditolak.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('sedang dipakai', $error->getMessage());
            }
            $this->assertTrue($manager->owned($entry));
            $manager->stop($entry);
            $this->assertFalse($manager->owned($entry));
            $this->assertFalse($manager->listening($port));
        } finally {
            if ($entry !== null) {
                $manager->stop($entry);
            }
            foreach (glob($directory.'/*') as $path) {
                unlink($path);
            }
            rmdir($directory);
        }
    }

    public function test_detached_process_survives_launcher_and_receives_environment(): void
    {
        $directory = sys_get_temp_dir().'/e1-linux-process-'.bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        $manager = new LinuxProcesses;
        $entry = null;
        try {
            $entry = $manager->start('fixture', [PHP_BINARY, '-r',
                'file_put_contents("env.txt", getenv("E1_FIXTURE")); sleep(30);',
            ], $directory, 0, $directory, ['E1_FIXTURE' => 'isolated']);
            $deadline = microtime(true) + 3;
            while (! is_file($directory.'/env.txt') && microtime(true) < $deadline) {
                usleep(50000);
            }
            $this->assertTrue($manager->owned($entry));
            $this->assertSame('isolated', file_get_contents($directory.'/env.txt'));
            $manager->stop($entry);
            $this->assertFalse($manager->owned($entry));
        } finally {
            if ($entry !== null) {
                $manager->stop($entry);
            }
            foreach (glob($directory.'/*') as $path) {
                unlink($path);
            }
            rmdir($directory);
        }
    }
}
