<?php

namespace App\Services\Tax;

use App\Exceptions\ApiException;
use App\Models\FakturPajak;
use App\Models\PembayaranPajak;
use App\Models\RekapPajakBulanan;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rekap pajak per masa pajak (FTR-KE01-04): jumlah faktur dihitung, total DPP/PPN/
 * PPnBM, jumlah setor, selisih, dan status pembayaran.
 *
 * Rancangan UI v0.2: NORMAL dan REPLACEMENT keluaran yang belum diganti
 * dijumlahkan. CANCELLED dan asal terganti tetap tampil, isCounted=false.
 * Status pembayaran per masa pajak: tidak ada catatan = NOT_RECORDED; ada catatan
 * INCOMPLETE = INCOMPLETE; semua catatan CONFIRMED = CONFIRMED.
 */
class RecapService
{
    public const PERIOD_PATTERN = '/^[0-9]{4}-(0[1-9]|1[0-2])$/';

    public static function isValidPeriod(mixed $period): bool
    {
        return is_string($period) && preg_match(self::PERIOD_PATTERN, $period) === 1;
    }

    /** @throws ApiException 400 INVALID_TAX_PERIOD */
    public function assertPeriod(mixed $period): string
    {
        if (! self::isValidPeriod($period)) {
            throw ApiException::badRequest('INVALID_TAX_PERIOD', 'Masa pajak harus berformat YYYY-MM (contoh 2026-11)');
        }

        return $period;
    }

    /**
     * Hitung rekap (tanpa menyimpan). Bila $projectId diberikan, hanya faktur
     * proyek itu yang dijumlahkan; status pembayaran tetap per masa pajak.
     */
    public function compute(string $taxPeriod, ?string $projectId = null, ?Carbon $computedAt = null): RecapData
    {
        $query = FakturPajak::query()->counted()->where('tax_period', $taxPeriod);
        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        $totals = $query->selectRaw(
            'count(*) as invoice_count, coalesce(sum(dpp), 0) as total_dpp, coalesce(sum(ppn), 0) as total_ppn, coalesce(sum(coalesce(ppnbm, 0)), 0) as total_ppnbm'
        )->first();

        $payments = PembayaranPajak::query()->where('tax_period', $taxPeriod)->get(['amount_paid', 'status']);

        $totalPpn = Money::normalize((string) $totals->total_ppn);
        $amountPaid = Money::sum($payments->pluck('amount_paid'));

        return new RecapData(
            taxPeriod: $taxPeriod,
            invoiceCount: (int) $totals->invoice_count,
            totalDpp: Money::normalize((string) $totals->total_dpp),
            totalPpn: $totalPpn,
            totalPpnbm: Money::normalize((string) $totals->total_ppnbm),
            amountPaid: $amountPaid,
            paymentDifference: Money::sub($amountPaid, $totalPpn),
            paymentStatus: $this->paymentStatus($payments),
            computedAt: $computedAt ?? Carbon::now('UTC'),
        );
    }

    /**
     * Hitung dan simpan (upsert) baris RekapPajakBulanan untuk satu masa pajak.
     * Dipanggil setiap faktur atau pembayaran berubah.
     */
    public function refresh(string $taxPeriod): RekapPajakBulanan
    {
        $recap = $this->compute($taxPeriod);

        DB::table('rekap_pajak_bulanan')->upsert([[
            'recap_id' => (string) Str::uuid7(),
            'tax_period' => $taxPeriod,
            'invoice_count' => $recap->invoiceCount,
            'total_dpp' => $recap->totalDpp,
            'total_ppn' => $recap->totalPpn,
            'total_ppnbm' => $recap->totalPpnbm,
            'amount_paid' => $recap->amountPaid,
            'payment_difference' => $recap->paymentDifference,
            'payment_status' => $recap->paymentStatus,
            'computed_at' => $recap->computedAt->toDateTimeString(),
        ]], ['tax_period'], [
            'invoice_count', 'total_dpp', 'total_ppn', 'total_ppnbm',
            'amount_paid', 'payment_difference', 'payment_status', 'computed_at',
        ]);

        return RekapPajakBulanan::query()->where('tax_period', $taxPeriod)->firstOrFail();
    }

    public function exists(string $taxPeriod): bool
    {
        return RekapPajakBulanan::query()->where('tax_period', $taxPeriod)->exists();
    }

    /**
     * Seluruh faktur masa pajak (termasuk Dibatalkan dan terganti) dengan penanda
     * isCounted, untuk daftar pada rekap dan ekspor.
     *
     * @return Collection<int, array{faktur: FakturPajak, isCounted: bool}>
     */
    public function invoices(string $taxPeriod): Collection
    {
        return FakturPajak::query()
            ->with('replacedBy:tax_invoice_id,replaces_tax_invoice_id')
            ->where('tax_period', $taxPeriod)
            ->orderBy('tax_invoice_date')
            ->orderBy('tax_invoice_number')
            ->get()
            ->map(fn (FakturPajak $f) => [
                'faktur' => $f,
                'isCounted' => in_array($f->status, [FakturPajak::STATUS_NORMAL, FakturPajak::STATUS_REPLACEMENT], true)
                    && $f->type === FakturPajak::TYPE_OUTGOING
                    && $f->replacedBy === null,
            ]);
    }

    /** @return Collection<int, PembayaranPajak> */
    public function payments(string $taxPeriod): Collection
    {
        return PembayaranPajak::query()
            ->where('tax_period', $taxPeriod)
            ->orderBy('created_at')
            ->orderBy('tax_payment_id')
            ->get();
    }

    private function paymentStatus(Collection $payments): string
    {
        if ($payments->isEmpty()) {
            return RekapPajakBulanan::PAYMENT_NOT_RECORDED;
        }

        return $payments->contains('status', PembayaranPajak::STATUS_INCOMPLETE)
            ? RekapPajakBulanan::PAYMENT_INCOMPLETE
            : RekapPajakBulanan::PAYMENT_CONFIRMED;
    }
}
