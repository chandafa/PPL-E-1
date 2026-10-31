<?php

declare(strict_types=1);

use App\Support\Integration\LinuxProcesses;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

$repo = dirname(__DIR__);
require $repo.'/vendor/autoload.php';

function runIntegrationCommand(array $command, string $cwd, array $env = []): void
{
    $process = new Process($command, $cwd, $env, timeout: null);
    $process->mustRun(static function (string $type, string $output): void {
        fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
    });
}

function integrationRepository(string $path, string $url, string $revision, string $required): string
{
    if (! is_dir($path)) {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        runIntegrationCommand(['git', 'clone', $url, $path], dirname($path));
        runIntegrationCommand(['git', 'checkout', '--detach', $revision], $path);
    }
    if (! is_file($path.'/'.$required)) {
        throw new RuntimeException('Checkout tidak dapat dipakai: '.$path.' ('.$required.' tidak ditemukan).');
    }

    return realpath($path);
}

$started = [];
$manager = new LinuxProcesses;
try {
    if (PHP_OS_FAMILY !== 'Linux' || ! function_exists('posix_kill')) {
        throw new RuntimeException('Launcher Linux memerlukan PHP dengan ekstensi posix.');
    }
    $action = $argv[1] ?? 'start';
    if (! in_array($action, ['start', 'stop'], true)) {
        throw new RuntimeException('Aksi harus start atau stop.');
    }
    $root = $repo.'/storage/app/private/integration';
    if (! is_dir($root)) {
        mkdir($root, 0700, true);
    }
    $lock = fopen($root.'/launcher.lock', 'c');
    if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Launcher integrasi lain sedang berjalan.');
    }
    $runtimeFile = $root.'/current.json';
    if (! is_file($runtimeFile) && $action === 'stop') {
        echo 'Tidak ada runtime integrasi yang tercatat.'.PHP_EOL;
        exit(0);
    }
    if (! is_file($runtimeFile)) {
        throw new RuntimeException('Jalankan php artisan integration untuk menyiapkan runtime.');
    }
    $runtime = json_decode(file_get_contents($runtimeFile), true, flags: JSON_THROW_ON_ERROR);
    $directory = $runtime['directory'];
    if (! is_dir($directory)) {
        throw new RuntimeException('Direktori runtime tidak ditemukan.');
    }
    $stateFile = $directory.'/processes.json';
    $state = is_file($stateFile)
        ? json_decode(file_get_contents($stateFile), true, flags: JSON_THROW_ON_ERROR) : [];
    if ($action === 'stop') {
        foreach (['e1', 'd2', 'f1'] as $name) {
            $manager->stop($state[$name] ?? []);
        }
        echo 'Proses integrasi milik runtime ini dihentikan. Database dan bukti tetap disimpan.'.PHP_EOL;
        exit(0);
    }

    $finder = new ExecutableFinder;
    foreach (['node', 'npm', 'setsid', 'nohup', 'composer'] as $executable) {
        if ($finder->find($executable) === null) {
            throw new RuntimeException('Perintah '.$executable.' tidak ditemukan di PATH.');
        }
    }
    $processes = [];
    foreach (['f1' => 4100, 'd2' => 8026, 'e1' => 8080] as $name => $port) {
        $entry = $state[$name] ?? [];
        if ($manager->owned($entry)) {
            if ($entry['port'] !== $port) {
                throw new RuntimeException('Profil/port berbeda. Jalankan php artisan integration:stop dahulu.');
            }
            if ($manager->listening($port, $entry['id'])) {
                $processes[$name] = $entry;

                continue;
            }
            $manager->stop($entry);
        }
        if ($manager->listening($port)) {
            throw new RuntimeException('Port '.$port.' dipakai proses di luar runtime ini; tidak dihentikan.');
        }
    }
    if (count($processes) === 3) {
        echo 'Integrasi sudah berjalan: http://127.0.0.1:8080/login; F-1 4100; D-2 8026.'.PHP_EOL;
        exit(0);
    }

    $parent = dirname($repo);
    $f1 = integrationRepository($argv[2] ?? $parent.'/_integrasi/F1',
        'https://github.com/Izra29/PPL_F1.git', '0feb37aafe9df9e871aaba9a5457093696ce38a0', 'demo/local-server.js');
    $d2 = integrationRepository($argv[3] ?? $parent.'/_integrasi/D2',
        'https://github.com/eldyfg/PPL-D-2.git', 'b93fc4dadcf7916995c9e6c2f981f95cc039dd97', 'artisan');
    if (! is_file($f1.'/node_modules/jsonwebtoken/package.json')) {
        runIntegrationCommand(['npm', 'ci', '--ignore-scripts'], $f1);
    }
    if (! is_file($d2.'/vendor/autoload.php')) {
        runIntegrationCommand(['composer', 'install', '--no-interaction', '--prefer-dist'], $d2);
    }
    $common = ['JWT_SECRET' => $runtime['jwtSecret'], 'JWT_EXPIRES_IN' => '3600'];
    $d2Env = $common + [
        'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => (string) $runtime['db']['host'],
        'DB_PORT' => (string) $runtime['db']['port'], 'DB_USERNAME' => $runtime['db']['username'],
        'DB_PASSWORD' => $runtime['db']['password'], 'DB_DATABASE' => $runtime['d2']['database'],
        'LARAVEL_STORAGE_PATH' => $runtime['d2']['storage'],
        'APP_CONFIG_CACHE' => $directory.'/d2-config.php',
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'single',
        'E1_DEPENDENCY_TRACE' => $directory.'/d2-requests.ndjson', 'E1_DEPENDENCY_REPO' => $d2,
    ];
    if (! isset($processes['d2'])) {
        $migration = [PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'];
        $marker = $directory.'/d2-setup.complete';
        if (! is_file($marker)) {
            $migration[] = '--seed';
        }
        runIntegrationCommand($migration, $d2, $d2Env);
        file_put_contents($marker, gmdate('c'));
    }
    $specifications = [
        'f1' => [[$finder->find('node'), '--require', $repo.'/scripts/trace-f1.cjs', 'demo/local-server.js'],
            $f1, 4100, $common + ['E1_DEPENDENCY_TRACE' => $directory.'/f1-requests.ndjson']],
        'd2' => [[PHP_BINARY, '-S', '127.0.0.1:8026', '-t', 'public', $repo.'/scripts/trace-d2.php'], $d2, 8026, $d2Env],
        'e1' => [[PHP_BINARY, '-d', 'upload_max_filesize=6M', '-d', 'post_max_size=16M', '-S', '127.0.0.1:8080',
            '-t', 'public', 'server.php'], $repo, 8080, []],
    ];
    foreach ($specifications as $name => [$command, $cwd, $port, $env]) {
        if (! isset($processes[$name])) {
            $entry = $manager->start($name, $command, $cwd, $port, $directory, $env);
            $started[$name] = $processes[$name] = $entry;
            // Persist every PID before waiting, so interrupted startup can be stopped/recovered.
            file_put_contents($stateFile, json_encode($processes, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
        }
        $manager->wait($name, $processes[$name], $directory);
    }
    echo 'Siap diuji: http://127.0.0.1:8080/login; F-1 4100; D-2 8026.'.PHP_EOL;
    echo 'Stop: php artisan integration:stop. Database utama E-1 dipertahankan.'.PHP_EOL;
} catch (Throwable $error) {
    foreach ($started as $entry) {
        $manager->stop($entry);
    }
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
