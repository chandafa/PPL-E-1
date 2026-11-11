<?php

namespace App\Console\Commands;

use App\Support\Integration\Launcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class StopIntegration extends Command
{
    protected $signature = 'integration:stop';

    protected $description = 'Hentikan layanan F-1, D-2, dan E-1 milik runtime integrasi';

    public function handle(Launcher $launcher): int
    {
        $lock = null;
        try {
            $stop = $launcher->command(true);
            $lock = $launcher->lock();

            return Process::path(base_path())->forever()->run($stop, function (string $type, string $output): void {
                $this->output->write($output);
            })->exitCode() ?? self::FAILURE;
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        } finally {
            if (is_resource($lock)) {
                fclose($lock);
            }
        }
    }
}
