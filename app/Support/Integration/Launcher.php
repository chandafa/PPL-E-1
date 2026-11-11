<?php

namespace App\Support\Integration;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

class Launcher
{
    public function __construct(private ExecutableFinder $finder) {}

    /** @return resource */
    public function lock()
    {
        $directory = storage_path('app/private/integration');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $lock = fopen($directory.'/launcher.lock', 'ce');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Perintah integrasi lain sedang berjalan.');
        }

        return $lock;
    }

    public function command(bool $stop = false, string $d2Driver = 'http'): array
    {
        if (PHP_OS_FAMILY === 'Linux') {
            return $stop
                ? [PHP_BINARY, 'scripts/linux-integration.php', 'stop']
                : array_merge(['bash', 'scripts/start-integration.sh', PHP_BINARY], $d2Driver === 'mock' ? ['mock'] : []);
        }

        $powershell = $this->finder->find('powershell');
        if (PHP_OS_FAMILY !== 'Windows' || $powershell === null) {
            throw new RuntimeException('Launcher mendukung Linux (Bash/nvm) dan Windows (PowerShell).');
        }

        return array_merge([
            $powershell, '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File',
            $stop ? 'scripts/stop-integration.ps1' : 'scripts/start-integration.ps1',
        ], ! $stop && $d2Driver === 'mock' ? ['-D2Driver', 'mock'] : []);
    }
}
