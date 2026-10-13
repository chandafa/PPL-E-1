<?php

namespace App\Services\Tax;

use App\Exceptions\ApiException;
use App\Models\PembayaranPajak;
use App\Services\Auth\F1Identity;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pencatatan dan pelaporan pembayaran pajak (FTR-KE01-05).
 *
 * Aturan (Q-10): CONFIRMED hanya bila NTPN dan bukti pembayaran ada; selain itu
 * INCOMPLETE (Draft/Belum Konfirmasi). Selisih jumlah setor terhadap total PPN
 * tetap tersimpan dan ditampilkan (tidak menolak; kompensasi menunggu Q-24).
 */
class TaxPaymentService
{
    public function __construct(
        private readonly RecapService $recap,
        private readonly FileStore $files,
    ) {}

    /**
     * API-KE01-08. @param array<string, mixed> $input taxPeriod, taxYear, amountPaid, billingId, ntpn
     *
     * @return array{payment: PembayaranPajak, paymentDifference: string}
     */
    public function create(array $input, mixed $eBillingFile, mixed $proofFile, F1Identity $actor): array
    {
        $taxPeriod = $input['taxPeriod'] ?? null;
        if (! RecapService::isValidPeriod($taxPeriod)) {
            throw $this->invalid('Masa pajak harus berformat YYYY-MM (contoh 2026-11)');
        }

        $taxYear = $this->taxYear($input['taxYear'] ?? null, $taxPeriod);
        $amountPaid = $this->amount($input['amountPaid'] ?? null);
        $billingId = $this->requiredText($input['billingId'] ?? null, 'billingId', 100);
        $ntpn = $this->optionalText($input['ntpn'] ?? null, 'ntpn', 100);

        $eBilling = $this->files->readPdf($eBillingFile, 'INVALID_PAYMENT', 'PDF e-Billing wajib diunggah dan harus berformat PDF');
        $proof = $proofFile === null
            ? null
            : $this->files->readPdf($proofFile, 'INVALID_PAYMENT', 'Bukti pembayaran harus berformat PDF');

        if (! $this->recap->exists($taxPeriod)) {
            throw ApiException::notFound('TAX_RECAP_NOT_FOUND', 'Masa pajak belum memiliki rekap');
        }

        $id = (string) Str::uuid7();
        $eBillingUri = $this->files->put('e-billing', $id, $eBilling);
        $proofUri = $proof !== null ? $this->files->put('payment-proof', $id, $proof) : null;

        try {
            return DB::transaction(function () use ($id, $taxPeriod, $taxYear, $amountPaid, $billingId, $ntpn, $eBillingUri, $proofUri, $actor): array {
                $payment = new PembayaranPajak;
                $payment->forceFill([
                    'tax_payment_id' => $id,
                    'tax_period' => $taxPeriod,
                    'tax_year' => $taxYear,
                    'amount_paid' => $amountPaid,
                    'billing_id' => $billingId,
                    'ntpn' => $ntpn,
                    'e_billing_uri' => $eBillingUri,
                    'payment_proof_uri' => $proofUri,
                    'status' => $ntpn !== null && $proofUri !== null
                        ? PembayaranPajak::STATUS_CONFIRMED
                        : PembayaranPajak::STATUS_INCOMPLETE,
                    'created_by_akun_id' => $actor->akunId,
                ])->save();

                $recap = $this->recap->refresh($taxPeriod);

                return ['payment' => $payment->fresh(), 'paymentDifference' => $recap->payment_difference];
            });
        } catch (Throwable $e) {
            $this->files->delete($eBillingUri);
            if ($proofUri !== null) {
                $this->files->delete($proofUri);
            }
            throw $e;
        }
    }

    /**
     * API-KE01-11: lengkapi pembayaran (NTPN + bukti) menjadi CONFIRMED.
     *
     * @return array{payment: PembayaranPajak, paymentStatus: string}
     */
    public function complete(mixed $taxPaymentId, mixed $ntpn, mixed $proofFile, F1Identity $actor): array
    {
        $payment = is_string($taxPaymentId) && Str::isUuid($taxPaymentId)
            ? PembayaranPajak::query()->find($taxPaymentId)
            : null;

        if ($payment === null) {
            throw ApiException::notFound('TAX_PAYMENT_NOT_FOUND', 'Catatan pembayaran tidak ditemukan');
        }

        $ntpn = $this->optionalText($ntpn, 'ntpn', 100);
        if ($ntpn === null || $proofFile === null) {
            throw $this->invalid('NTPN dan bukti pembayaran wajib diisi untuk Terkonfirmasi');
        }

        $proof = $this->files->readPdf($proofFile, 'INVALID_PAYMENT', 'Bukti pembayaran harus berformat PDF');
        $proofUri = $this->files->put('payment-proof', $payment->tax_payment_id.'-'.Str::lower(Str::random(8)), $proof);

        try {
            return DB::transaction(function () use ($payment, $ntpn, $proofUri): array {
                // Berkas bukti lama tidak dihapus saat diganti (jejak audit).
                $payment->forceFill([
                    'ntpn' => $ntpn,
                    'payment_proof_uri' => $proofUri,
                    'status' => PembayaranPajak::STATUS_CONFIRMED,
                ])->save();

                $recap = $this->recap->refresh($payment->tax_period);

                return ['payment' => $payment->fresh(), 'paymentStatus' => $recap->payment_status];
            });
        } catch (Throwable $e) {
            $this->files->delete($proofUri);
            throw $e;
        }
    }

    private function taxYear(mixed $value, string $taxPeriod): int
    {
        $year = is_numeric($value) && (string) (int) $value === (string) $value ? (int) $value : null;

        if ($year === null || $year < 2000 || $year > 2100) {
            throw $this->invalid('taxYear harus berupa tahun 4 digit, contoh 2026');
        }

        if ($year !== (int) substr($taxPeriod, 0, 4)) {
            throw $this->invalid('taxYear harus sama dengan tahun pada masa pajak');
        }

        return $year;
    }

    private function amount(mixed $value): string
    {
        if (! Money::isValid($value)) {
            throw $this->invalid('amountPaid harus berupa nominal desimal tidak negatif, contoh 11000000.00');
        }

        return Money::normalize($value);
    }

    private function requiredText(mixed $value, string $field, int $max): string
    {
        $text = $this->optionalText($value, $field, $max);

        return $text ?? throw $this->invalid("{$field} wajib diisi");
    }

    private function optionalText(mixed $value, string $field, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw $this->invalid("{$field} harus berupa teks");
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $max) {
            throw $this->invalid("{$field} maksimum {$max} karakter");
        }

        return $value;
    }

    private function invalid(string $message): ApiException
    {
        return ApiException::badRequest('INVALID_PAYMENT', $message);
    }
}
