<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateF1Token;
use App\Services\Auth\F1Identity;
use App\Services\Tax\TaxPaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pembayaran dan pelaporan pajak: API-KE01-08 dan API-KE01-11 (FTR-KE01-05).
 */
class TaxPaymentController extends Controller
{
    public function __construct(private readonly TaxPaymentService $service) {}

    /** API-KE01-08 catat pembayaran (Draft/INCOMPLETE bila NTPN atau bukti belum ada). */
    public function store(Request $request): JsonResponse
    {
        $result = $this->service->create(
            $request->only(['taxPeriod', 'taxYear', 'amountPaid', 'billingId', 'ntpn']),
            $request->file('eBillingFile'),
            $request->file('paymentProofFile'),
            $this->actor($request),
        );

        $payment = $result['payment'];

        return ApiResponse::ok([
            'taxPaymentId' => $payment->tax_payment_id,
            'status' => $payment->status,
            'taxPeriod' => $payment->tax_period,
            'paymentDifference' => $result['paymentDifference'],
        ], 'Pembayaran tersimpan');
    }

    /** API-KE01-11 lengkapi pembayaran menjadi CONFIRMED (NTPN dan bukti wajib). */
    public function complete(Request $request, string $taxPaymentId): JsonResponse
    {
        $result = $this->service->complete(
            $taxPaymentId,
            $request->input('ntpn'),
            $request->file('paymentProofFile'),
            $this->actor($request),
        );

        return ApiResponse::ok([
            'taxPaymentId' => $result['payment']->tax_payment_id,
            'status' => $result['payment']->status,
            'paymentStatus' => $result['paymentStatus'],
        ], 'Pembayaran Terkonfirmasi');
    }

    private function actor(Request $request): F1Identity
    {
        return $request->attributes->get(AuthenticateF1Token::ATTRIBUTE);
    }
}
