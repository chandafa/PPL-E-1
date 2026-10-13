<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaxPresenter;
use App\Models\FakturPajak;
use App\Models\PembayaranPajak;
use App\Services\Export\RecapExporter;
use App\Services\Tax\RecapService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Rekap pajak per masa pajak: API-KE01-07 (FTR-KE01-04) dan ekspor API-KE01-09 (FTR-KE01-06).
 */
class TaxRecapController extends Controller
{
    public function __construct(
        private readonly RecapService $recap,
        private readonly RecapExporter $exporter,
    ) {}

    /** API-KE01-07 rekap masa pajak (periode kosong = rekap nol, bukan 404). */
    public function show(string $taxPeriod): JsonResponse
    {
        $this->recap->assertPeriod($taxPeriod);

        $invoices = $this->recap->invoices($taxPeriod)->map(function (array $row): array {
            /** @var FakturPajak $f */
            $f = $row['faktur'];

            return [
                'taxInvoiceId' => $f->tax_invoice_id,
                'taxInvoiceNumber' => $f->tax_invoice_number,
                'invoiceId' => $f->invoice_id,
                'projectId' => $f->project_id,
                'clientId' => $f->client_id,
                'status' => $f->status,
                'dpp' => $f->dpp,
                'ppn' => $f->ppn,
                'ppnbm' => $f->ppnbm,
                'isCounted' => $row['isCounted'],
            ];
        })->values()->all();

        return ApiResponse::ok([
            'recap' => TaxPresenter::recap($this->recap->compute($taxPeriod)),
            'invoices' => $invoices,
            'payments' => $this->recap->payments($taxPeriod)
                ->map(fn (PembayaranPajak $p) => TaxPresenter::payment($p))->values()->all(),
        ], 'Rekap masa pajak');
    }

    /** API-KE01-09 ekspor rekap (berkas biner tanpa amplop). */
    public function export(Request $request, string $taxPeriod): Response
    {
        $file = $this->exporter->export($taxPeriod, $request->query('format'));

        return response($file['content'], 200, [
            'Content-Type' => $file['contentType'],
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
            'Content-Length' => (string) strlen($file['content']),
            'Cache-Control' => 'no-store',
        ]);
    }
}
