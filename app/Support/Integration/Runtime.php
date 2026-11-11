<?php

namespace App\Support\Integration;

use Dotenv\Dotenv;
use RuntimeException;

class Runtime
{
    public const REPOSITORIES = [
        'f1' => ['url' => 'https://github.com/Izra29/PPL_F1.git', 'branch' => 'main', 'required' => 'demo/local-server.js'],
        'd2' => ['url' => 'https://github.com/eldyfg/PPL-D-2.git', 'branch' => 'main', 'required' => 'artisan'],
    ];

    public static function read(string $repo): array
    {
        $path = $repo.'/storage/app/private/integration/current.json';
        if (! is_file($path)) {
            throw new RuntimeException('Jalankan composer integration terlebih dahulu.');
        }

        return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function target(string $repo): string
    {
        $env = Dotenv::parse(file_get_contents($repo.'/.env'));

        $target = array_intersect_key($env, array_flip([
            'DB_CONNECTION', 'DB_URL', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME',
        ]));
        ksort($target);

        return hash('sha256', json_encode($target, JSON_THROW_ON_ERROR));
    }

    public static function profile(string $repo): string
    {
        $env = Dotenv::parse(file_get_contents($repo.'/.env'));
        $profile = array_filter($env, static fn (string $key): bool => preg_match('/^(F1_|D2_|DB_|APP_ENV$|APP_KEY$|E1_DEV_LOGIN$|LARAVEL_STORAGE_PATH$)/', $key) === 1, ARRAY_FILTER_USE_KEY);
        ksort($profile);

        return hash('sha256', json_encode($profile, JSON_THROW_ON_ERROR));
    }

    public static function ready(string $repo, string $driver): array
    {
        $runtime = self::read($repo);
        $setup = $runtime['setup'] ?? [];
        $env = Dotenv::parse(file_get_contents($repo.'/.env'));
        if (($setup['complete'] ?? false) !== true || ($setup['driver'] ?? null) !== $driver
            || ($setup['target'] ?? null) !== self::target($repo)
            || ($setup['profile'] ?? null) !== self::profile($repo)
            || ($env['D2_DRIVER'] ?? null) !== $driver
            || ($env['F1_JWT_SECRET'] ?? null) !== $runtime['jwtSecret']
            || ($env['APP_ENV'] ?? null) !== 'local'
            || ($env['F1_BASE_URL'] ?? null) !== 'http://127.0.0.1:4100'
            || ($driver === 'http' && ($env['D2_BASE_URL'] ?? null) !== 'http://127.0.0.1:8026')
            || is_file($repo.'/bootstrap/cache/config.php')) {
            throw new RuntimeException('Setup integrasi belum lengkap atau konfigurasi berubah. Jalankan composer integration terlebih dahulu.');
        }
        foreach ($setup['repositories'] ?? [] as $name => $repository) {
            if (! is_file($repository['path'].'/'.self::REPOSITORIES[$name]['required'])) {
                throw new RuntimeException('Checkout '.$name.' tidak tersedia. Jalankan composer integration.');
            }
            $dependency = $name === 'f1' ? 'node_modules/jsonwebtoken/package.json' : 'vendor/autoload.php';
            if (! is_file($repository['path'].'/'.$dependency)) {
                throw new RuntimeException('Dependensi '.$name.' belum tersedia. Jalankan composer integration.');
            }
        }
        if (! isset($setup['repositories']['f1']) || ($driver === 'http' && ! isset($setup['repositories']['d2']))) {
            throw new RuntimeException('Checkout belum disiapkan. Jalankan composer integration.');
        }
        if ($driver === 'http' && empty($runtime['d2']['key'])) {
            throw new RuntimeException('Database D-2 belum disiapkan. Jalankan composer integration.');
        }

        return $runtime;
    }

    public static function d2Environment(array $runtime): array
    {
        return [
            'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_KEY' => $runtime['d2']['key'],
            'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => (string) $runtime['db']['host'],
            'DB_PORT' => (string) $runtime['db']['port'], 'DB_USERNAME' => $runtime['db']['username'],
            'DB_PASSWORD' => $runtime['db']['password'], 'DB_DATABASE' => $runtime['d2']['database'],
            'LARAVEL_STORAGE_PATH' => $runtime['d2']['storage'],
            'APP_CONFIG_CACHE' => $runtime['directory'].'/d2-config.php',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'single',
            'E1_DEPENDENCY_TRACE' => $runtime['directory'].'/d2-requests.ndjson',
            'E1_DEPENDENCY_REPO' => $runtime['setup']['repositories']['d2']['path'] ?? '',
        ];
    }

    public static function e1Environment(string $repo, string $driver): array
    {
        $env = Dotenv::parse(file_get_contents($repo.'/.env'));

        // Pin child processes to the .env database checked during setup, even if the shell exports DB_*.
        return [
            'APP_ENV' => $env['APP_ENV'], 'APP_KEY' => $env['APP_KEY'],
            'DB_CONNECTION' => $env['DB_CONNECTION'] ?? 'pgsql', 'DB_URL' => $env['DB_URL'] ?? '',
            'DB_HOST' => $env['DB_HOST'] ?? '127.0.0.1', 'DB_PORT' => $env['DB_PORT'] ?? '5432',
            'DB_DATABASE' => $env['DB_DATABASE'] ?? 'laravel', 'DB_USERNAME' => $env['DB_USERNAME'] ?? 'root',
            'DB_PASSWORD' => $env['DB_PASSWORD'] ?? '', 'D2_DRIVER' => $driver, 'D2_MOCK_DATASET' => 'sqlite',
            'D2_INTEGRATION_CATALOG' => $driver === 'http' ? 'true' : 'false',
        ];
    }

    public static function save(string $repo, array $runtime): void
    {
        $path = $repo.'/storage/app/private/integration/current.json';
        file_put_contents($path.'.tmp', json_encode($runtime, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
        chmod($path.'.tmp', 0600);
        rename($path.'.tmp', $path);
    }
}
