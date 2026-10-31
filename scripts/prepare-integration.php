<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;

// Membuat DB baru; tidak pernah menjalankan migrate:fresh atau menghapus DB.
$repo = dirname(__DIR__);
require $repo.'/vendor/autoload.php';
$app = require $repo.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if ($app->configurationIsCached()) {
    throw new RuntimeException('Jalankan php artisan config:clear terlebih dahulu.');
}
if (! is_file($repo.'/.env')) {
    throw new RuntimeException('Siapkan .env PostgreSQL E-1 terlebih dahulu.');
}
foreach ([4100, 8026, 8087] as $port) {
    $socket = @fsockopen('127.0.0.1', $port, $errorNumber, $errorMessage, 0.2);
    if ($socket !== false) {
        fclose($socket);
        throw new RuntimeException('Port integrasi '.$port.' masih dipakai; hentikan runtime dahulu agar profil aktif tidak tertimpa.');
    }
}
$db = config('database.connections.pgsql');
$pdo = new PDO('pgsql:host='.$db['host'].';port='.$db['port'].';dbname=postgres', $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$stamp = date('Ymd_His').'_'.bin2hex(random_bytes(3));
$directory = $repo.'/storage/app/private/integration/'.$stamp;
mkdir($directory, 0777, true);
$runtime = [
    'createdAtUtc' => gmdate('c'), 'directory' => $directory,
    'e1BaseUrl' => 'http://127.0.0.1:8087', 'f1BaseUrl' => 'http://127.0.0.1:4100',
    'd2BaseUrl' => 'http://127.0.0.1:8026', 'jwtSecret' => bin2hex(random_bytes(32)),
    'db' => ['host' => $db['host'], 'port' => $db['port'], 'username' => $db['username'], 'password' => $db['password']],
];
foreach (['e1', 'd2', 'tests'] as $mode) {
    $database = 'e1_integrasi_'.$stamp.'_'.$mode;
    $pdo->exec('CREATE DATABASE "'.$database.'"');
    $storage = $directory.'/'.$mode.'/storage';
    foreach (['app/private', 'app/public', 'framework/cache/data', 'framework/views', 'framework/sessions', 'logs'] as $path) {
        mkdir($storage.'/'.$path, 0777, true);
    }
    $runtime[$mode] = ['database' => $database, 'storage' => $storage];
}
$environment = file_get_contents($repo.'/.env');
$settings = Dotenv\Dotenv::parse(file_get_contents($repo.'/.env.integration.example'));
$settings += ['APP_KEY' => config('app.key')];
$settings['F1_JWT_SECRET'] = $runtime['jwtSecret'];
$settings['DB_CONNECTION'] = 'pgsql';
$settings['DB_URL'] = '';
$settings['DB_DATABASE'] = $runtime['e1']['database'];
$settings['LARAVEL_STORAGE_PATH'] = $runtime['e1']['storage'];
foreach ($settings as $key => $value) {
    $line = $key.'="'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], (string) $value).'"';
    $pattern = '/^'.preg_quote($key, '/').'\s*=.*$/m';
    if (preg_match($pattern, $environment)) {
        $environment = preg_replace_callback($pattern, fn () => $line, $environment);
    } else {
        $environment .= PHP_EOL.$line;
    }
}
if (is_file($repo.'/.env.integration')) {
    copy($repo.'/.env.integration', $directory.'/previous-profile.env');
}
file_put_contents($repo.'/.env.integration', $environment.PHP_EOL);
$runtimeFile = $repo.'/storage/app/private/integration/current.json';
file_put_contents($runtimeFile, json_encode($runtime, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
echo 'Profil .env.integration dibuat; tiga DB baru dan storage terpisah disiapkan. Data .env utama tidak diubah.'.PHP_EOL;
echo 'Runtime privat: storage/app/private/integration/current.json'.PHP_EOL;
