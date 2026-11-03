<?php

namespace App\Console\Commands;

use App\Support\Integration\Launcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class Integration extends Command
{
    protected $signature = 'integration {--d2=mock : mock untuk dummy lokal; http untuk server D-2}';

    protected $description = 'Jalankan E-1 dan F-1 dengan dummy D-2 lokal (atau --d2=http)';

    public function handle(Launcher $launcher): int
    {
        $driver = (string) $this->option('d2');
        if (! in_array($driver, ['mock', 'http'], true)) {
            $this->error('Opsi --d2 harus mock atau http.');

            return self::FAILURE;
        }
        try {
            $start = $launcher->command(false, $driver);
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $commands = [
            [PHP_BINARY, 'artisan', 'config:clear'],
            [PHP_BINARY, 'scripts/enable-integration.php', $driver],
        ];
        if ($driver === 'mock') {
            $commands[] = [PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'];
            $commands[] = [PHP_BINARY, 'artisan', 'db:seed', '--class=D2SourceDemoSeeder', '--force', '--no-interaction'];
            $commands[] = [PHP_BINARY, 'artisan', 'demo:samples', '--dataset=d2-source', '--path=storage/app/private/d2-source-samples'];
        }
        $commands[] = $start;
        foreach ($commands as $command) {
            $result = Process::path(base_path())->env(['D2_DRIVER' => $driver, 'D2_MOCK_DATASET' => 'sqlite'])->forever()->run($command, function (string $type, string $output): void {
                $this->output->write($output);
            });

            if ($result->failed()) {
                return $result->exitCode() ?? self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
