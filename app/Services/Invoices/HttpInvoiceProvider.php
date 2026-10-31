<?php

namespace App\Services\Invoices;

use App\Exceptions\ApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Pemanggil layanan D-2 sebenarnya (D2_DRIVER=http). Path dan bentuk respons
 * invoice belum final (OPEN-07 dengan Kelas D Kelompok 2), jadi pemetaan dibuat
 * toleran: badan boleh `{data: {...}}` atau objek invoice langsung, dengan nama
 * field mengikuti Skema_Data_Awal_D2 (invoiceId, dppAmount, ppnAmount, ...).
 */
class HttpInvoiceProvider implements InvoiceProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $invoicePath,
        private readonly int $timeout,
        private readonly ?string $bearerToken = null,
    ) {}

    public function find(string $invoiceId): ?InvoiceData
    {
        $path = str_replace('{invoiceId}', rawurlencode($invoiceId), $this->invoicePath);

        try {
            $response = $this->client()->get(rtrim($this->baseUrl, '/').$path);
        } catch (ConnectionException) {
            throw $this->unavailable();
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw $this->unavailable();
        }

        $json = $response->json();
        $row = is_array($json) ? ($json['data'] ?? $json) : null;

        if (! is_array($row) || ($row['invoiceId'] ?? null) !== $invoiceId) {
            throw $this->unavailable();
        }

        if (isset($row['terkait']) && ! is_array($row['terkait'])) {
            throw $this->unavailable();
        }
        $related = $row['terkait'] ?? [];
        foreach (['projectId', 'projectName', 'clientId', 'clientName'] as $field) {
            if (isset($related[$field])) {
                if (! is_string($related[$field]) || (isset($row[$field]) && $row[$field] !== $related[$field])) {
                    throw $this->unavailable();
                }
                $row[$field] = $related[$field];
            }
        }

        foreach (['invoiceNumber', 'invoiceDate', 'status', 'projectId', 'projectName', 'clientId', 'clientName', 'dppAmount', 'ppnAmount'] as $field) {
            if (isset($row[$field]) && ! is_string($row[$field])) {
                throw $this->unavailable();
            }
        }

        return new InvoiceData(
            invoiceId: (string) $row['invoiceId'],
            invoiceNumber: $row['invoiceNumber'] ?? null,
            invoiceDate: $row['invoiceDate'] ?? null,
            dppAmount: isset($row['dppAmount']) ? (string) $row['dppAmount'] : null,
            ppnAmount: isset($row['ppnAmount']) ? (string) $row['ppnAmount'] : null,
            status: $row['status'] ?? null,
            projectId: $row['projectId'] ?? null,
            projectName: $row['projectName'] ?? null,
            clientId: $row['clientId'] ?? null,
            clientName: $row['clientName'] ?? null,
        );
    }

    /**
     * D-2 tidak menyediakan endpoint daftar invoice (hanya detail per ID), sehingga
     * Katalog tidak dapat diisi melalui layanan langsung tanpa mengubah D-2.
     * Kembalikan daftar kosong; pemanggil bertanggung jawab menampilkan keterbatasan ini.
     *
     * @return list<array<string, mixed>>
     */
    public function catalog(): array
    {
        return [];
    }

    // D-2 belum menyediakan pencarian proyek/klien; filter nama berdasar ID persis.
    public function projectIdsMatching(string $term): array
    {
        return [$term];
    }

    public function clientIdsMatching(string $term): array
    {
        return [$term];
    }

    public function projectExists(string $projectId): bool
    {
        return false;
    }

    private function client(): PendingRequest
    {
        // Jangan meneruskan Bearer ke tujuan redirect dari upstream.
        $client = Http::acceptJson()->withoutRedirecting()->timeout($this->timeout);
        $requestId = (string) request()->header('X-Request-ID', '');
        $client = $client->withHeaders(['X-Request-ID' => preg_match('/^[A-Za-z0-9._:-]{1,100}$/D', $requestId) === 1
            ? $requestId : (string) Str::uuid7()]);

        return $this->bearerToken !== null ? $client->withToken($this->bearerToken) : $client;
    }

    private function unavailable(): ApiException
    {
        return ApiException::make(503, 'D2_UNAVAILABLE', 'Layanan D-2 tidak dapat dihubungi');
    }
}
