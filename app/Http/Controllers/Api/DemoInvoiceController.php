<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\FakturPajak;
use App\Services\Invoices\MockInvoiceProvider;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DemoInvoiceController extends Controller
{
    public function __invoke(): JsonResponse
    {
        if (config('e1.d2.driver') !== 'mock') {
            throw ApiException::notFound('NOT_FOUND', 'Pilihan invoice dummy hanya tersedia untuk sumber D-2 mock');
        }

        $provider = new MockInvoiceProvider;
        $active = FakturPajak::query()->active()
            ->where('type', FakturPajak::TYPE_OUTGOING)
            ->pluck('invoice_id')->flip();
        $invoices = array_values(array_filter($provider->catalog(), fn (array $invoice): bool => ! $active->has($invoice['invoiceId'])));

        return ApiResponse::ok(['invoices' => $invoices], 'Invoice D-2 tanpa faktur keluaran aktif');
    }
}
