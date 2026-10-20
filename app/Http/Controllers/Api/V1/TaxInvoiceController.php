<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateF1Token;
use App\Http\Resources\TaxPresenter;
use App\Models\FakturPajak;
use App\Models\RiwayatPerubahanFaktur;
use App\Services\Auth\F1Identity;
use App\Services\Invoices\InvoiceProvider;
use App\Services\Tax\TaxInvoiceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Faktur pajak keluaran: API-KE01-01 s.d. API-KE01-06 (FTR-KE01-01, 02, 03).
 */
class TaxInvoiceController extends Controller
{
    public function __construct(private readonly TaxInvoiceService $service) {}

    /** API-KE01-01 pratinjau ekstraksi PDF (tidak menyimpan). */
    public function extract(Request $request): JsonResponse
    {
        $data = $this->service->extract($request->input('invoiceId'), $request->file('eFakturFile'));

        return ApiResponse::ok($data, 'Hasil ekstraksi (belum tersimpan)');
    }

    /** API-KE01-02 simpan faktur keluaran atau faktur pengganti. */
    public function storeOutgoing(Request $request): JsonResponse
    {
        $faktur = $this->service->createOutgoing(
            $request->input('invoiceId'),
            $request->file('eFakturFile'),
            $request->input('replacesTaxInvoiceId'),
            $this->actor($request),
        );

        return ApiResponse::ok(TaxPresenter::created($faktur), 'Faktur tersimpan');
    }

    /** API-KE01-03 cari dan filter faktur. */
    public function index(Request $request): JsonResponse
    {
        $page = $this->service->search(
            $request->only(['taxInvoiceNumber', 'project', 'client', 'taxPeriod', 'status']),
            $request->query('page'),
            $request->query('pageSize'),
        );

        return ApiResponse::ok([
            'items' => $page->getCollection()->map(fn (FakturPajak $f) => TaxPresenter::summary($f))->values()->all(),
            'pagination' => [
                'page' => $page->currentPage(),
                'pageSize' => $page->perPage(),
                'totalItems' => $page->total(),
            ],
        ], 'Daftar faktur');
    }

    /** API-KE01-04 detail faktur. */
    public function show(string $taxInvoiceId, InvoiceProvider $invoices): JsonResponse
    {
        $faktur = $this->service->find($taxInvoiceId);
        $data = TaxPresenter::detail($faktur);
        // Referensi D-2 terkini, bukan snapshot historis atau dasar Masa Pajak.
        $data['invoiceReference'] = null;
        try {
            $reference = $invoices->find($faktur->invoice_id);
            if ($reference !== null) {
                $data['invoiceReference'] = ['invoiceDate' => $reference->invoiceDate, 'invoiceNumber' => $reference->invoiceNumber];
            }
        } catch (ApiException $exception) {
            report($exception); // Gangguan D-2 tidak menghalangi pembacaan faktur tersimpan.
        }

        return ApiResponse::ok($data, 'Detail faktur');
    }

    /** API-KE01-05 koreksi atau batalkan faktur. */
    public function update(Request $request, string $taxInvoiceId): JsonResponse
    {
        $body = $request->isJson() ? $request->json()->all() : $request->all();

        $result = $this->service->update(
            $taxInvoiceId,
            $body['changes'] ?? null,
            $body['note'] ?? null,
            $this->actor($request),
        );

        return ApiResponse::ok([
            'taxInvoice' => TaxPresenter::invoice($result['faktur']),
            'auditId' => $result['auditId'],
        ], 'Faktur diperbarui');
    }

    /** API-KE01-06 riwayat perubahan. */
    public function history(string $taxInvoiceId): JsonResponse
    {
        $history = $this->service->history($taxInvoiceId)
            ->map(fn (RiwayatPerubahanFaktur $h) => TaxPresenter::history($h))
            ->values()
            ->all();

        return ApiResponse::ok(['history' => $history], 'Riwayat faktur');
    }

    private function actor(Request $request): F1Identity
    {
        return $request->attributes->get(AuthenticateF1Token::ATTRIBUTE);
    }
}
