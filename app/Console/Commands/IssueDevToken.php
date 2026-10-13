<?php

namespace App\Console\Commands;

use App\Exceptions\ApiException;
use App\Services\Auth\DevTokenIssuer;
use Illuminate\Console\Command;

/**
 * Menerbitkan token F-1 PALSU untuk pengembangan dan demo lokal. Tidak
 * tersedia di production; di lingkungan nyata token diterbitkan layanan F-1.
 */
class IssueDevToken extends Command
{
    protected $signature = 'f1:token
        {akunId=AKN-0002 : akunId pada token (contoh AKN-0002)}
        {role=Admin : kodePeran pada token (Admin, CEO, COO, atau peran lain untuk uji 403)}
        {--ttl=3600 : masa berlaku dalam detik}';

    protected $description = 'Terbitkan token Bearer F-1 palsu untuk pengembangan lokal';

    public function handle(DevTokenIssuer $issuer): int
    {
        if (app()->isProduction()) {
            $this->error('Perintah ini dinonaktifkan di production.');

            return self::FAILURE;
        }

        try {
            $issued = $issuer->issue((string) $this->argument('akunId'), (string) $this->argument('role'), (int) $this->option('ttl'));
        } catch (ApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line($issued['token']);

        return self::SUCCESS;
    }
}
