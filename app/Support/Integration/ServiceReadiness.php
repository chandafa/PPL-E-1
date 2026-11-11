<?php

namespace App\Support\Integration;

use App\Exceptions\ApiException;
use App\Services\Invoices\InvoiceProvider;
use Illuminate\Support\Facades\Http;

class ServiceReadiness
{
    public function check(): void
    {
        $response = Http::withoutRedirecting()->timeout(5)->get('http://127.0.0.1:8087/login');
        if ($response->status() !== 200 || ! str_contains($response->body(), 'id="f1-login"')) {
            throw ApiException::make(503, 'E1_NOT_READY', 'Halaman login E-1 belum siap (HTTP '.$response->status().'). Periksa build frontend dan log e1.err.log.');
        }
        if (config('e1.d2.driver') !== 'http') {
            return;
        }

        $identity = app(DemoIdentity::class)->authenticate();
        $authorization = request()->headers->get('Authorization');
        $catalog = config('e1.d2.integration_catalog');
        try {
            request()->headers->set('Authorization', 'Bearer '.$identity['accessToken']);
            config(['e1.d2.integration_catalog' => true]);
            $invoices = app(InvoiceProvider::class)->catalog();
            if ($invoices === []) {
                throw ApiException::make(503, 'D2_UNAVAILABLE', 'API D-2 belum menyediakan invoice demo. Periksa seed D-2 dan d2-requests.ndjson.');
            }
        } finally {
            $authorization === null ? request()->headers->remove('Authorization') : request()->headers->set('Authorization', $authorization);
            config(['e1.d2.integration_catalog' => $catalog]);
        }
    }
}
