<?php

namespace App\Console\Commands;

use App\Support\Integration\Installer;
use App\Support\Integration\Launcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class Integration extends Command
{
    protected $signature = 'integration {action? : run untuk menjalankan layanan; kosong untuk setup} {--d2=http : http untuk server D-2; mock untuk dummy lokal}';

    protected $description = 'Siapkan integrasi; gunakan integration run untuk menjalankan E-1, F-1, dan D-2';

    public function handle(Launcher $launcher, Installer $installer): int
    {
        $driver = (string) $this->option('d2');
        if (! in_array($driver, ['mock', 'http'], true)) {
            $this->error('Opsi --d2 harus mock atau http.');

            return self::FAILURE;
        }
        $action = $this->argument('action');
        if ($action !== null && $action !== 'run') {
            $this->error('Gunakan composer integration untuk setup atau composer integration run untuk menjalankan layanan.');

            return self::FAILURE;
        }
        $lock = null;
        try {
            $start = $launcher->command(false, $driver);
            $lock = $launcher->lock();
            if ($action === null) {
                $installer->setup(base_path(), $driver);

                return self::SUCCESS;
            }
            $installer->ready(base_path(), $driver);
            $installer->repair(base_path(), $driver);
            $result = Process::path(base_path())->forever()->run($start, function (string $type, string $output): void {
                $this->output->write($output);
            });
            if ($result->successful()) {
                $installer->verifyServices(base_path(), $driver);
                $installer->seedTaxInvoices(base_path(), $driver);
                $this->info('Integrasi siap: http://127.0.0.1:8087/login; pemeriksaan layanan dan seed faktur selesai.');
            }

            return $result->exitCode() ?? self::FAILURE;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return $error instanceof RuntimeException && $error->getCode() > 0 && $error->getCode() < 256
                ? $error->getCode() : self::FAILURE;
        } finally {
            if (is_resource($lock)) {
                fclose($lock);
            }
        }
    }
}
