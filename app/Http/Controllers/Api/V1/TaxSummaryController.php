<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\FakturPajak;
use App\Services\Invoices\InvoiceProvider;
use App\Services\Tax\RecapService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ringkasan pajak untuk modul lain (konsumen E-2): API-KE01-10 (FTR-KE01-07).
 * Hanya agregat; tanpa dokumen, rincian faktur, NTPN, atau lampiran (Q-22).
 */
class TaxSummaryController extends Controller
{
    public function __construct(
        private readonly RecapService $recap,
        private readonly InvoiceProvider $invoices,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $taxPeriod = $this->recap->assertPeriod($request->query('taxPeriod'));
        $projectId = $this->projectId($request->query('projectId'));

        $recap = $this->recap->compute($taxPeriod, $projectId);

        return ApiResponse::ok([
            'taxPeriod' => $recap->taxPeriod,
            'invoiceCount' => $recap->invoiceCount,
            'totalDpp' => $recap->totalDpp,
            'totalPpn' => $recap->totalPpn,
            'totalPpnbm' => $recap->totalPpnbm,
            'paymentStatus' => $recap->paymentStatus,
        ], 'Ringkasan pajak');
    }

    /** projectId opsional; bila ada harus berformat wajar dan dikenal E-1 atau D-2. */
    private function projectId(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $invalid = fn () => ApiException::badRequest('INVALID_PROJECT_ID', 'projectId tidak valid');

        if (! is_string($value) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\-]{0,99}$/', $value) !== 1) {
            throw $invalid();
        }

        $known = FakturPajak::query()->where('project_id', $value)->exists()
            || $this->invoices->projectExists($value);

        if (! $known) {
            throw $invalid();
        }

        return $value;
    }
}
