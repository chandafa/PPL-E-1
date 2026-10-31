<?php

namespace App\Support\Integration;

use RuntimeException;
use Symfony\Component\Process\Process;

/** Linux /proc identities prevent stopping a reused PID or an unrelated listener. */
class LinuxProcesses
{
    public function identity(int $pid): ?string
    {
        $stat = @file_get_contents('/proc/'.$pid.'/stat');
        if ($stat === false) {
            return null;
        }
        $fields = explode(' ', substr($stat, strrpos($stat, ')') + 2));
        if (in_array($fields[0], ['Z', 'X'], true)) {
            return null;
        }

        return 'linux:'.trim(file_get_contents('/proc/sys/kernel/random/boot_id')).':'.$fields[19];
    }

    public function owned(array $entry): bool
    {
        return isset($entry['id'], $entry['startedAt'])
            && (int) $entry['id'] > 1
            && $this->identity((int) $entry['id']) === $entry['startedAt'];
    }

    public function listening(int $port, ?int $pid = null): bool
    {
        $sockets = [];
        if ($pid !== null) {
            foreach (glob('/proc/'.$pid.'/fd/*') ?: [] as $fd) {
                $sockets[] = @readlink($fd);
            }
        }
        foreach (['/proc/net/tcp', '/proc/net/tcp6'] as $table) {
            foreach (@file($table, FILE_IGNORE_NEW_LINES) ?: [] as $row) {
                $fields = preg_split('/\s+/', trim($row));
                if (count($fields) < 10 || $fields[3] !== '0A') {
                    continue;
                }
                if (hexdec(explode(':', $fields[1])[1]) === $port
                    && ($pid === null || in_array('socket:['.$fields[9].']', $sockets, true))) {
                    return true;
                }
            }
        }

        return false;
    }

    public function start(string $name, array $command, string $cwd, int $port, string $directory, array $env = []): array
    {
        if ($this->listening($port)) {
            throw new RuntimeException('Port '.$port.' sedang dipakai proses lain.');
        }
        $shell = 'nohup setsid '.implode(' ', array_map(escapeshellarg(...), $command))
            .' < /dev/null > '.escapeshellarg($directory.'/'.$name.'.out.log')
            .' 2> '.escapeshellarg($directory.'/'.$name.'.err.log').' & echo $!';
        $spawn = Process::fromShellCommandline($shell, $cwd, $env);
        $spawn->mustRun();
        $pid = (int) trim($spawn->getOutput());
        $identity = $pid > 1 ? $this->identity($pid) : null;
        if ($identity === null) {
            throw new RuntimeException('Proses '.$name.' gagal dimulai; lihat '.$directory.'/'.$name.'.err.log');
        }

        return ['id' => $pid, 'startedAt' => $identity, 'port' => $port];
    }

    public function wait(string $name, array $entry, string $directory): void
    {
        $deadline = microtime(true) + 15;
        do {
            if (! $this->owned($entry)) {
                break;
            }
            if ($this->listening($entry['port'], $entry['id'])) {
                return;
            }
            usleep(100000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Layanan '.$name.' belum siap; lihat '.$directory.'/'.$name.'.err.log');
    }

    public function stop(array $entry): void
    {
        if (! $this->owned($entry)) {
            return;
        }
        if (! posix_kill($entry['id'], 15)) {
            throw new RuntimeException('Tidak dapat menghentikan PID '.$entry['id']);
        }
        $deadline = microtime(true) + 3;
        while ($this->owned($entry) && microtime(true) < $deadline) {
            usleep(50000);
        }
        if ($this->owned($entry) && ! posix_kill($entry['id'], 9)) {
            throw new RuntimeException('Tidak dapat menghentikan PID '.$entry['id']);
        }
    }
}
