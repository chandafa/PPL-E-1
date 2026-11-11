<?php

namespace App\Support\Integration;

use Dotenv\Dotenv;
use RuntimeException;

class RuntimeSetup
{
    public function configure(string $repo, string $driver, array $database): array
    {
        if (! in_array($driver, ['mock', 'http'], true)) {
            throw new RuntimeException('Driver D-2 harus mock atau http.');
        }
        $environment = file_get_contents($repo.'/.env');
        $main = Dotenv::parse($environment);
        if (($main['APP_ENV'] ?? '') !== 'local') {
            throw new RuntimeException('Setup runtime hanya tersedia dengan APP_ENV=local.');
        }
        try {
            $runtime = Runtime::read($repo);
            if (empty($runtime['jwtSecret']) || empty($runtime['directory']) || ! is_dir($runtime['directory'])
                || empty($runtime['d2']['database']) || empty($runtime['d2']['storage'])) {
                throw new RuntimeException('Runtime tidak lengkap.');
            }
        } catch (RuntimeException|\JsonException) {
            $runtime = $this->create($repo, $database);
        }
        $settings = [
            'F1_API_ENABLED' => 'true', 'F1_BASE_URL' => 'http://127.0.0.1:4100',
            'F1_LOGIN_PATH' => '/auth/login', 'F1_ROLE_PATH' => '/accounts/{accountId}/role',
            'F1_CHECK_ACCOUNT' => 'true', 'F1_TIMEOUT' => '3', 'F1_JWT_ALGO' => 'HS256',
            'F1_JWT_SECRET' => $runtime['jwtSecret'], 'F1_JWT_PUBLIC_KEY_PATH' => '',
            'F1_JWT_ISSUER' => '', 'F1_JWT_AUDIENCE' => '', 'F1_JWT_LEEWAY' => '0',
            'F1_CLAIM_AKUN_ID' => 'accountId', 'F1_CLAIM_ROLE' => 'roleCode', 'E1_DEV_LOGIN' => 'false',
            'D2_DRIVER' => $driver, 'D2_MOCK_DATASET' => 'sqlite', 'D2_BASE_URL' => 'http://127.0.0.1:8026',
            'D2_INVOICE_PATH' => '/api/v1/invoices/{invoiceId}', 'D2_TIMEOUT' => '3',
        ];
        foreach ($settings as $key => $value) {
            $line = $key.'="'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
            $pattern = '/^'.preg_quote($key, '/').'\s*=.*$/m';
            $environment = preg_match($pattern, $environment)
                ? preg_replace_callback($pattern, fn () => $line, $environment)
                : rtrim($environment).PHP_EOL.$line.PHP_EOL;
        }
        $updated = Dotenv::parse($environment);
        foreach (array_unique(array_merge(array_keys($main), array_keys($updated))) as $key) {
            if (! array_key_exists($key, $settings) && ($main[$key] ?? null) !== ($updated[$key] ?? null)) {
                throw new RuntimeException('Konfigurasi di luar koneksi F-1/D-2 berubah: '.$key);
            }
        }
        if ($environment !== file_get_contents($repo.'/.env')) {
            $backup = $runtime['directory'].'/main-before-enable-'.date('Ymd_His').'-'.bin2hex(random_bytes(3)).'.env';
            copy($repo.'/.env', $backup);
            chmod($backup, 0600);
            file_put_contents($repo.'/.env', $environment, LOCK_EX);
        }
        Runtime::save($repo, $runtime);

        return $runtime;
    }

    private function create(string $repo, array $database): array
    {
        $stamp = date('Ymd_His').'_'.bin2hex(random_bytes(3));
        $directory = $repo.'/storage/app/private/integration/'.$stamp;
        $storage = $directory.'/d2/storage';
        foreach (['app/private', 'app/public', 'framework/cache/data', 'framework/views', 'framework/sessions', 'logs'] as $path) {
            if (! mkdir($storage.'/'.$path, 0700, true) && ! is_dir($storage.'/'.$path)) {
                throw new RuntimeException('Gagal menyiapkan storage runtime D-2.');
            }
        }
        $name = 'e1_integrasi_'.$stamp.'_d2';
        $connection = new \PDO('pgsql:host='.$database['host'].';port='.$database['port'].';dbname=postgres', $database['username'], $database['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $connection->exec('CREATE DATABASE "'.$name.'"');

        return [
            'createdAtUtc' => gmdate('c'), 'directory' => $directory,
            'e1BaseUrl' => 'http://127.0.0.1:8087', 'f1BaseUrl' => 'http://127.0.0.1:4100',
            'd2BaseUrl' => 'http://127.0.0.1:8026', 'jwtSecret' => bin2hex(random_bytes(32)),
            'db' => array_intersect_key($database, array_flip(['host', 'port', 'username', 'password'])),
            'd2' => ['database' => $name, 'storage' => $storage],
        ];
    }
}
