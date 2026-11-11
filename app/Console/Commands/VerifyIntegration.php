<?php

namespace App\Console\Commands;

use App\Exceptions\ApiException;
use App\Support\Integration\Runtime;
use App\Support\Integration\ServiceReadiness;
use Illuminate\Console\Command;

class VerifyIntegration extends Command
{
    protected $signature = 'integration:verify';

    protected $description = 'Periksa halaman login dan API invoice D-2 sebelum seed faktur';

    public function handle(ServiceReadiness $readiness): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Pemeriksaan integrasi hanya tersedia di local/testing.');

            return self::FAILURE;
        }
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $readiness->check();
                $this->info('Pemeriksaan layanan berhasil.');

                return self::SUCCESS;
            } catch (\Throwable $error) {
                if ($attempt < 3) {
                    usleep(500000);

                    continue;
                }
                // Connection exceptions can contain request details; expose only known application errors.
                $message = $error instanceof ApiException
                    ? $error->getMessage() : 'Koneksi pemeriksaan layanan gagal.';
                $this->error($message);
                $this->error('Layanan belum siap; seed faktur tidak dijalankan. Periksa log e1/d2 dan d2-requests.ndjson pada direktori runtime.');
                try {
                    $this->line('Direktori runtime: '.Runtime::read(base_path())['directory']);
                } catch (\Throwable) {
                    // The original failure remains the relevant diagnostic if the manifest is unavailable.
                }
            }
        }

        return self::FAILURE;
    }
}
