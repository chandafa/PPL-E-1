<?php

namespace App\Console\Commands;

use App\Support\Integration\Launcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class Integration extends Command
{
    protected $signature = 'integration';

    protected $description = 'Aktifkan integrasi lokal F-1 dan D-2 lalu jalankan ketiga layanan';

    public function handle(Launcher $launcher): int
    {
        try {
            $start = $launcher->command();
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        foreach ([
            [PHP_BINARY, 'artisan', 'config:clear'],
            [PHP_BINARY, 'scripts/enable-integration.php'],
            $start,
        ] as $command) {
            $result = Process::path(base_path())->forever()->run($command, function (string $type, string $output): void {
                $this->output->write($output);
            });

            if ($result->failed()) {
                return $result->exitCode() ?? self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
