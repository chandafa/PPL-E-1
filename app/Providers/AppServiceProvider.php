<?php

namespace App\Providers;

use App\Services\Auth\F1TokenVerifier;
use App\Services\Invoices\HttpInvoiceProvider;
use App\Services\Invoices\InvoiceProvider;
use App\Services\Invoices\MockInvoiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Dibaca tiap resolusi (bukan singleton) agar konfigurasi uji dapat diubah.
        $this->app->bind(F1TokenVerifier::class, fn () => F1TokenVerifier::fromConfig());

        $this->app->bind(InvoiceProvider::class, function ($app) {
            $config = (array) config('e1.d2');

            if (($config['driver'] ?? 'mock') === 'http') {
                /** @var Request $request */
                $request = $app->make('request');

                return new HttpInvoiceProvider(
                    baseUrl: (string) $config['base_url'],
                    invoicePath: (string) $config['invoice_path'],
                    timeout: (int) $config['timeout'],
                    bearerToken: $request->bearerToken(),
                );
            }

            return new MockInvoiceProvider((string) $config['mock_file']);
        });
    }

    public function boot(): void
    {
        //
    }
}
