<?php

namespace App\Support\Integration;

use Dotenv\Dotenv;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

class Installer
{
    private ?string $logDirectory = null;

    public function ready(string $repo, string $driver): array
    {
        return Runtime::ready($repo, $driver);
    }

    public function execute(array $command, string $cwd, array $env = [], bool $display = true): string
    {
        $result = Process::path($cwd)->env($env)->forever()->run($command, static function (string $type, string $output) use ($display): void {
            // Child errors may contain database connection details; retain those in private logs only.
            if ($display && $type === 'out') {
                fwrite(STDOUT, $output);
            }
        });
        if ($result->failed()) {
            $directory = $this->logDirectory ?? $cwd.'/storage/logs';
            if (! is_dir($directory)) {
                mkdir($directory, 0700, true);
            }
            $log = $directory.'/integration-setup.log';
            file_put_contents($log, $result->output().$result->errorOutput(), FILE_APPEND | LOCK_EX);
            chmod($log, 0600);
            throw new RuntimeException('Tahap '.$command[0].' gagal (exit '.$result->exitCode().'). Periksa '.$log, $result->exitCode() ?: 1);
        }

        return trim($result->output());
    }

    public function repository(string $path, array $spec, bool $override = false): array
    {
        if (! is_dir($path)) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0700, true);
            }
            $this->execute(['git', 'clone', '--branch', $spec['branch'], '--single-branch', $spec['url'], $path], dirname($path));
        }
        if (! is_file($path.'/'.$spec['required'])) {
            throw new RuntimeException('Checkout tidak valid: '.$path);
        }
        $branch = $this->execute(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $path, display: false);
        if (! $override && $branch !== $spec['branch']) {
            throw new RuntimeException('Checkout terkelola '.$path.' harus pada branch '.$spec['branch'].'. Pindahkan checkout lama atau tentukan override path secara eksplisit; tidak ada reset otomatis.');
        }
        if (! $override && $this->execute(['git', 'status', '--porcelain', '--untracked-files=no'], $path, display: false) !== '') {
            throw new RuntimeException('Checkout terkelola '.$path.' memiliki perubahan lokal. Simpan pekerjaan atau gunakan override path eksplisit.');
        }
        $revision = $this->execute(['git', 'rev-parse', 'HEAD'], $path, display: false);

        return ['path' => realpath($path), 'branch' => $branch, 'revision' => $revision];
    }

    public function setup(string $repo, string $driver): void
    {
        $this->logDirectory = $repo.'/storage/logs';
        if (! is_file($repo.'/.env')) {
            throw new RuntimeException('Siapkan .env dan APP_KEY E-1 sebelum setup integrasi.');
        }
        $env = Dotenv::parse(file_get_contents($repo.'/.env'));
        if (($env['APP_ENV'] ?? '') !== 'local' || empty($env['APP_KEY'])) {
            throw new RuntimeException('Setup integrasi memerlukan APP_ENV=local dan APP_KEY pada .env E-1.');
        }
        $finder = new ExecutableFinder;
        foreach (array_merge(['git', 'composer'], PHP_OS_FAMILY === 'Linux' ? ['bash', 'setsid', 'nohup'] : ['powershell', 'nvm']) as $tool) {
            if ($finder->find($tool) === null) {
                throw new RuntimeException('Perintah '.$tool.' belum tersedia di PATH.');
            }
        }
        if (PHP_OS_FAMILY === 'Linux' && (! function_exists('posix_kill') || ! is_file((getenv('NVM_DIR') ?: getenv('HOME').'/.nvm').'/nvm.sh'))) {
            throw new RuntimeException('Linux memerlukan PHP posix dan nvm pada akun pengguna.');
        }
        foreach ([4100, 8026, 8087] as $port) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.2);
            if ($socket !== false) {
                fclose($socket);
                throw new RuntimeException('Port '.$port.' aktif. Hentikan layanan dengan composer integration:stop sebelum setup.');
            }
        }

        try {
            if (($env['DB_CONNECTION'] ?? '') !== 'pgsql') {
                throw new RuntimeException('Setup integrasi memerlukan DB_CONNECTION=pgsql pada .env E-1.');
            }
            $db = (new ConfigurationUrlParser)->parseConfiguration([
                'url' => $env['DB_URL'] ?? '', 'driver' => 'pgsql',
                'host' => $env['DB_HOST'] ?? '127.0.0.1', 'port' => $env['DB_PORT'] ?? '5432',
                'database' => $env['DB_DATABASE'] ?? '', 'username' => $env['DB_USERNAME'] ?? '',
                'password' => $env['DB_PASSWORD'] ?? '',
            ]);
            $connection = new \PDO('pgsql:host='.$db['host'].';port='.$db['port'].';dbname='.$db['database'], $db['username'], $db['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            if (! $connection->query('SELECT rolcreatedb OR rolsuper FROM pg_roles WHERE rolname = current_user')->fetchColumn()) {
                throw new RuntimeException('Akun PostgreSQL memerlukan hak CREATE DATABASE.');
            }
        } catch (\PDOException) {
            throw new RuntimeException('Koneksi PostgreSQL E-1 gagal. Periksa konfigurasi database pada .env.');
        }
        $runtimePath = $repo.'/storage/app/private/integration/current.json';
        if (is_file($runtimePath)) {
            try {
                $existing = Runtime::read($repo);
                $existing['setup']['complete'] = false;
                Runtime::save($repo, $existing);
            } catch (\JsonException) {
                // RuntimeSetup recovers an interrupted manifest after the port checks.
            }
        }

        $repositories = [];
        foreach (Runtime::REPOSITORIES as $name => $spec) {
            if ($name === 'd2' && $driver === 'mock') {
                continue;
            }
            $override = getenv(strtoupper($name).'_PATH');
            $path = $override ?: dirname($repo).'/_integrasi/'.strtoupper($name);
            $repository = $this->repository($path, $spec, (bool) $override);
            echo strtoupper($name).' '.$repository['branch'].' '.$repository['revision'].' ('.$path.')'.PHP_EOL;
            $repositories[$name] = $repository;
        }

        $f1 = $repositories['f1']['path'];
        $node = PHP_OS_FAMILY === 'Linux'
            ? ['bash', 'scripts/integration-node.sh', $f1, 'install']
            : ['powershell', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', 'scripts/integration-node.ps1', '-Repository', $f1, '-Install'];
        $this->execute($node, $repo);
        if ($driver === 'http') {
            $this->execute(['composer', 'install', '--no-interaction', '--prefer-dist'], $repositories['d2']['path']);
        }
        $e1Env = Runtime::e1Environment($repo, $driver);
        $this->execute([PHP_BINARY, 'artisan', 'config:clear'], $repo, $e1Env);
        $runtime = (new RuntimeSetup)->configure($repo, $driver, $db);
        $target = Runtime::target($repo);
        $runtime['setup'] = [
            'complete' => false, 'driver' => $driver, 'target' => $target, 'profile' => Runtime::profile($repo), 'repositories' => $repositories,
        ];
        $runtime['d2']['key'] ??= 'base64:'.base64_encode(random_bytes(32));
        Runtime::save($repo, $runtime);

        $this->ensureData($repo, $driver, $runtime);
        echo 'Setup selesai. Jalankan composer integration run'.($driver === 'mock' ? ' -- --d2=mock' : '').PHP_EOL;
    }

    public function repair(string $repo, string $driver): void
    {
        $this->logDirectory = $repo.'/storage/logs';
        $this->ensureData($repo, $driver, Runtime::read($repo));
    }

    public function seedTaxInvoices(string $repo, string $driver): void
    {
        $this->logDirectory = $repo.'/storage/logs';
        $this->execute([PHP_BINARY, 'artisan', 'db:seed', '--class=TaxInvoiceSeeder', '--force', '--no-interaction'], $repo, Runtime::e1Environment($repo, $driver));
    }

    private function ensureData(string $repo, string $driver, array $runtime): void
    {
        $runtime['setup']['complete'] = false;
        Runtime::save($repo, $runtime);
        $e1Env = Runtime::e1Environment($repo, $driver);
        $this->execute([PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'], $repo, $e1Env);
        $this->execute([PHP_BINARY, 'artisan', 'db:seed', '--class='.($driver === 'mock' ? 'IntegrationSnapshotSeeder' : 'IntegrationDemoSeeder'), '--force', '--no-interaction'], $repo, $e1Env);
        if ($driver === 'mock') {
            $this->execute([PHP_BINARY, 'artisan', 'demo:samples', '--dataset=d2-source', '--path=storage/app/private/d2-source-samples'], $repo, $e1Env);
        } else {
            $d2Env = Runtime::d2Environment($runtime);
            $path = $runtime['setup']['repositories']['d2']['path'];
            $this->execute([PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'], $path, $d2Env);
            $this->execute([PHP_BINARY, $repo.'/scripts/seed-integration-d2.php', $path], $path, $d2Env);
            file_put_contents($runtime['directory'].'/d2-setup.complete', gmdate('c'), LOCK_EX);
        }
        $runtime['setup']['complete'] = true;
        Runtime::save($repo, $runtime);
    }
}
