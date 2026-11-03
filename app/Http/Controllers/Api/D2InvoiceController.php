<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\Invoices\InvoiceProvider;
use App\Services\Invoices\MockInvoiceProvider;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Daftar invoice D-2 untuk rujukan pencatatan e-Faktur di E-1.
 * Data nilai komersial (DPP, PPN, status, dll.) berasal langsung dari D-2;
 * E-1 hanya menampilkannya sebagai rujukan tanpa menghitung ulang.
 */
class D2InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceProvider $provider) {}

    public function __invoke(): JsonResponse
    {
        return ApiResponse::ok(['invoices' => array_values($this->provider->catalog())], 'Daftar invoice D-2');
    }

    public function show(string $invoiceId): JsonResponse
    {
        $detail = $this->provider instanceof MockInvoiceProvider
            ? $this->provider->detail($invoiceId)
            : (($invoice = $this->provider->find($invoiceId)) !== null ? ['source' => 'D2_HTTP', 'invoice' => get_object_vars($invoice)] : null);
        if ($detail === null) {
            throw ApiException::notFound('INVOICE_NOT_FOUND', 'Invoice D-2 tidak ditemukan');
        }

        return ApiResponse::ok($detail, 'Detail referensi invoice D-2');
    }
}
