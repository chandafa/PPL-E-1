<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PrepareD2SourceDemo extends Command
{
    protected $signature = 'demo:d2 {--path=storage/app/private/d2-source-samples : Folder keluaran PDF relatif terhadap proyek}';

    protected $description = 'Siapkan snapshot API D-2 lengkap dan PDF di E-1 tanpa memulai layanan lain';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing']) || config('e1.d2.driver') !== 'mock' || config('e1.d2.mock_dataset') !== 'sqlite') {
            $this->error('Gunakan APP_ENV=local/testing, D2_DRIVER=mock dan D2_MOCK_DATASET=sqlite.');

            return self::FAILURE;
        }
        foreach ([
            ['migrate', ['--force' => true, '--no-interaction' => true]],
            ['db:seed', ['--class' => 'D2SourceDemoSeeder', '--force' => true, '--no-interaction' => true]],
            ['demo:samples', ['--dataset' => 'd2-source', '--path' => $this->option('path')]],
        ] as [$command, $arguments]) {
            $status = $this->call($command, $arguments);
            if ($status !== self::SUCCESS) {
                return $status;
            }
        }
        $this->info('Snapshot simulasi D-2 siap dibaca melalui API E-1; tidak ada layanan lain yang dijalankan.');

        return self::SUCCESS;
    }
}
