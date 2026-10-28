<?php

namespace App\Services\Tax;

use App\Models\PembayaranPajak;
use App\Models\RiwayatPerubahanPembayaran;
use Illuminate\Support\Carbon;

class PaymentHistoryRecorder
{
    /** @param array<string, mixed>|null $before */
    public function record(PembayaranPajak $payment, string $action, string $actorAkunId, ?array $before): RiwayatPerubahanPembayaran
    {
        return RiwayatPerubahanPembayaran::create([
            'tax_payment_id' => $payment->tax_payment_id,
            'action' => $action,
            'actor_akun_id' => $actorAkunId,
            'changed_at' => Carbon::now('UTC'),
            'before_snapshot' => $before,
            'after_snapshot' => $this->snapshot($payment),
        ]);
    }

    /** @return array<string, mixed> */
    public function snapshot(PembayaranPajak $payment): array
    {
        return [
            'taxPeriod' => $payment->tax_period,
            'taxYear' => $payment->tax_year,
            'amountPaid' => $payment->amount_paid,
            'billingId' => $payment->billing_id,
            'ntpn' => $payment->ntpn,
            'eBillingUri' => $payment->e_billing_uri,
            'paymentProofUri' => $payment->payment_proof_uri,
            'status' => $payment->status,
            'createdByAkunId' => $payment->created_by_akun_id,
        ];
    }
}
