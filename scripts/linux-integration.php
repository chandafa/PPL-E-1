<?php

declare(strict_types=1);

use App\Support\Integration\LinuxProcesses;
use App\Support\Integration\Runtime;
use Symfony\Component\Process\ExecutableFinder;

$repo = dirname(__DIR__);
require $repo.'/vendor/autoload.php';

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
    $d2Driver = $argv[2] ?? 'http';
    if (! in_array($d2Driver, ['mock', 'http'], true)) {
        throw new RuntimeException('Driver D-2 harus mock atau http.');
    }
    $runtime = Runtime::ready($repo, $d2Driver);
    $mockD2 = $d2Driver === 'mock';
    $modeFile = $directory.'/d2-mode.txt';
    $previousMode = is_file($modeFile) ? trim(file_get_contents($modeFile)) : 'http';
    if ($previousMode !== $d2Driver) {
        $manager->stop($state['e1'] ?? []);
        unset($state['e1']);
    }
    if ($mockD2) {
        $manager->stop($state['d2'] ?? []);
        unset($state['d2']);
    }
    $ports = $mockD2 ? ['f1' => 4100, 'e1' => 8087] : ['f1' => 4100, 'd2' => 8026, 'e1' => 8087];
    $d2Label = $mockD2 ? 'D-2 dummy lokal (2 invoice SQLite)' : 'D-2 8026';
    foreach (['node', 'npm', 'setsid', 'nohup', 'composer'] as $executable) {
        if ($finder->find($executable) === null) {
            throw new RuntimeException('Perintah '.$executable.' tidak ditemukan di PATH.');
        }
    }
    $processes = [];
    foreach ($ports as $name => $port) {
        $entry = $state[$name] ?? [];
        if ($manager->owned($entry)) {
            if ($entry['port'] !== $port) {
                if ($name === 'e1' && $entry['port'] === 8080 && $port === 8087) {
                    $manager->stop($entry);

                    continue;
                }
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
    if (count($processes) === count($ports)) {
        echo 'Integrasi sudah berjalan: http://127.0.0.1:8087/login; F-1 4100; '.$d2Label.'.'.PHP_EOL;
        exit(0);
    }

    $f1 = $runtime['setup']['repositories']['f1']['path'];
    $d2 = $mockD2 ? null : $runtime['setup']['repositories']['d2']['path'];
    if (! is_file($f1.'/node_modules/jsonwebtoken/package.json') || (! $mockD2 && ! is_file($d2.'/vendor/autoload.php'))) {
        throw new RuntimeException('Dependensi belum lengkap. Jalankan composer integration.');
    }
    $common = ['JWT_SECRET' => $runtime['jwtSecret'], 'JWT_EXPIRES_IN' => '3600', 'PORT' => '4100'];
    $d2Env = $mockD2 ? [] : Runtime::d2Environment($runtime);
    $specifications = [
        'f1' => [[$finder->find('node'), '--require', $repo.'/scripts/trace-f1.cjs', 'demo/local-server.js'],
            $f1, 4100, $common + ['E1_DEPENDENCY_TRACE' => $directory.'/f1-requests.ndjson']],
        'd2' => [[PHP_BINARY, '-S', '127.0.0.1:8026', '-t', 'public', $repo.'/scripts/trace-d2.php'], $d2, 8026, $d2Env],
        'e1' => [[PHP_BINARY, '-d', 'upload_max_filesize=6M', '-d', 'post_max_size=16M', '-S', '127.0.0.1:8087',
            '-t', 'public', 'server.php'], $repo, 8087, Runtime::e1Environment($repo, $d2Driver)],
    ];
    if ($mockD2) {
        unset($specifications['d2']);
    }
    foreach ($specifications as $name => [$command, $cwd, $port, $env]) {
        if (! isset($processes[$name])) {
            $entry = $manager->start($name, $command, $cwd, $port, $directory, $env);
            $started[$name] = $processes[$name] = $entry;
            // Persist every PID before waiting, so interrupted startup can be stopped/recovered.
            file_put_contents($stateFile, json_encode($processes, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
        }
        $manager->wait($name, $processes[$name], $directory);
    }
    file_put_contents($modeFile, $d2Driver);
    echo 'Siap diuji: http://127.0.0.1:8087/login; F-1 4100; '.$d2Label.'.'.PHP_EOL;
    echo 'Stop: php artisan integration:stop. Database utama E-1 dipertahankan.'.PHP_EOL;
} catch (Throwable $error) {
    foreach ($started as $entry) {
        $manager->stop($entry);
    }
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
