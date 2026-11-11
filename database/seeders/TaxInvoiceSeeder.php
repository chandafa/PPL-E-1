<?php

namespace Database\Seeders;

use App\Services\Auth\F1Identity;
use App\Services\Invoices\InvoiceProvider;
use App\Support\Demo\DemoFixtureWriter;
use App\Support\Integration\DemoIdentity;
use Database\Seeders\Support\DemoDataset;
use Illuminate\Database\Seeder;
use RuntimeException;

class TaxInvoiceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('TaxInvoiceSeeder hanya tersedia di local/testing.');

            return;
        }
        $driver = config('e1.d2.driver');
        if (! in_array($driver, ['mock', 'http'], true)) {
            throw new RuntimeException('Driver D-2 tidak dikenal untuk seed faktur.');
        }
        $request = request();
        $authorization = $request->headers->get('Authorization');
        $catalogSetting = config('e1.d2.integration_catalog');
        $actor = new F1Identity('AKN-0002', 'Admin');
        try {
            if ($driver === 'http') {
                $identity = app(DemoIdentity::class)->authenticate();
                $actor = new F1Identity($identity['accountId'], $identity['roleCode']);
                $request->headers->set('Authorization', 'Bearer '.$identity['accessToken']);
                config(['e1.d2.integration_catalog' => true]);
            }
            $fixtures = DemoDataset::fromReferences(app(InvoiceProvider::class)->catalog());
            // Resolve after authentication: the writer's invoice client captures the current Bearer token.
            $writer = app(DemoFixtureWriter::class);
            $counts = $writer->write($fixtures, [], $actor);
            $this->command?->info("Demo E-1: {$counts['invoices']} faktur ditambahkan; data yang sudah ada dipertahankan.");
        } finally {
            $authorization === null ? $request->headers->remove('Authorization') : $request->headers->set('Authorization', $authorization);
            config(['e1.d2.integration_catalog' => $catalogSetting]);
        }
    }
}
