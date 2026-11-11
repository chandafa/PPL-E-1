<?php

namespace App\Services\Invoices;

use App\Exceptions\ApiException;
use App\Support\Integration\Runtime;
use Illuminate\Support\Facades\DB;

class IntegrationCatalog
{
    public function __construct(private readonly string $repo) {}

    /** Discover local source IDs only; commercial values still come from authenticated HTTP. */
    public function ids(): array
    {
        if (! app()->environment(['local', 'testing'])) {
            throw ApiException::make(503, 'D2_UNAVAILABLE', 'Katalog integrasi D-2 hanya tersedia secara lokal');
        }
        $connection = null;
        try {
            $runtime = Runtime::read($this->repo);
            if (($runtime['setup']['complete'] ?? false) !== true || ($runtime['setup']['driver'] ?? null) !== 'http') {
                throw new \RuntimeException('Setup belum lengkap');
            }
            $connection = DB::build([
                'driver' => 'pgsql', 'host' => $runtime['db']['host'], 'port' => $runtime['db']['port'],
                'database' => $runtime['d2']['database'], 'username' => $runtime['db']['username'],
                'password' => $runtime['db']['password'], 'charset' => 'utf8', 'prefix' => '',
            ]);

            return $connection->transaction(function () use ($connection): array {
                $connection->statement('SET TRANSACTION READ ONLY');

                return $connection->table('invoices')->orderBy('invoice_id')->pluck('invoice_id')->all();
            });
        } catch (\Throwable) {
            throw ApiException::make(503, 'D2_UNAVAILABLE', 'Daftar invoice D-2 tidak dapat dimuat');
        } finally {
            $connection?->disconnect();
        }
    }
}
