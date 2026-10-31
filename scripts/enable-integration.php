<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Symfony\Component\Process\Process;

$repo = dirname(__DIR__);
require $repo.'/vendor/autoload.php';
if (! is_file($repo.'/.env')) {
    throw new RuntimeException('Siapkan .env PostgreSQL dan APP_KEY E-1 terlebih dahulu.');
}
$main = Dotenv::parse(file_get_contents($repo.'/.env'));
if (($main['APP_ENV'] ?? 'production') === 'production' || getenv('APP_ENV') === 'production') {
    throw new RuntimeException('Profil demo resmi F-1 ini hanya untuk lingkungan lokal, bukan production.');
}
$runtimeFile = $repo.'/storage/app/private/integration/current.json';
if (! is_file($runtimeFile)) {
    $process = new Process([PHP_BINARY, $repo.'/scripts/prepare-integration.php'], $repo);
    $process->mustRun();
}
$runtime = json_decode(file_get_contents($runtimeFile), true, flags: JSON_THROW_ON_ERROR);
$profile = Dotenv::parse(file_get_contents($repo.'/.env.integration'));
if (($profile['F1_JWT_SECRET'] ?? null) !== $runtime['jwtSecret']) {
    throw new RuntimeException('Profil dan runtime integrasi berbeda; siapkan ulang setelah semua proses uji dihentikan.');
}
$environment = file_get_contents($repo.'/.env');
$backup = $runtime['directory'].'/main-before-enable-'.date('Ymd_His').'-'.bin2hex(random_bytes(3)).'.env';
copy($repo.'/.env', $backup);
$keys = [
    'F1_API_ENABLED', 'F1_BASE_URL', 'F1_LOGIN_PATH', 'F1_ROLE_PATH', 'F1_CHECK_ACCOUNT', 'F1_TIMEOUT',
    'F1_JWT_ALGO', 'F1_JWT_SECRET', 'F1_JWT_PUBLIC_KEY_PATH', 'F1_JWT_ISSUER', 'F1_JWT_AUDIENCE', 'F1_JWT_LEEWAY',
    'F1_CLAIM_AKUN_ID', 'F1_CLAIM_ROLE', 'E1_DEV_LOGIN',
    'D2_DRIVER', 'D2_BASE_URL', 'D2_INVOICE_PATH', 'D2_TIMEOUT',
];
foreach ($keys as $key) {
    $value = (string) ($profile[$key] ?? '');
    $line = $key.'="'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    $pattern = '/^'.preg_quote($key, '/').'\s*=.*$/m';
    $environment = preg_match($pattern, $environment)
        ? preg_replace_callback($pattern, fn () => $line, $environment)
        : rtrim($environment).PHP_EOL.$line.PHP_EOL;
}
$updated = Dotenv::parse($environment);
// Konfigurasi DB/storage dan identitas aplikasi utama harus tetap milik pengguna.
foreach (array_unique(array_merge(array_keys($main), array_keys($updated))) as $key) {
    if (! in_array($key, $keys, true) && ($main[$key] ?? null) !== ($updated[$key] ?? null)) {
        throw new RuntimeException('Konfigurasi di luar koneksi F-1/D-2 berubah: '.$key);
    }
}
file_put_contents($repo.'/.env', $environment);
echo 'Koneksi F-1/D-2 aktif pada .env E-1: login F-1, cek akun aktif, invoice HTTP. Login demo E-1 mati.'.PHP_EOL;
echo 'Database, storage, dan APP_KEY utama tetap. Cadangan .env tersimpan privat di runtime integrasi.'.PHP_EOL;
