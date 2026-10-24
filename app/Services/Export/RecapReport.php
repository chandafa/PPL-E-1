<?php

namespace App\Services\Export;

use App\Models\FakturPajak;
use App\Models\PembayaranPajak;
use App\Models\RekapPajakBulanan;
use App\Services\Tax\RecapData;
use App\Services\Tax\RecapService;
use App\Support\DateDisplay;
use Carbon\CarbonInterface;

/**
 * Isi laporan rekap yang SAMA untuk Excel dan PDF (FTR-KE01-06: isi file identik
 * dengan rekap di layar). Nominal tetap string decimal; label berbahasa Indonesia.
 */
final class RecapReport
{
    /**
     * @param  list<array<string, string>>  $invoices
     * @param  list<array<string, string>>  $payments
     */
    public function __construct(
        public readonly string $taxPeriod,
        public readonly RecapData $recap,
        public readonly array $invoices,
        public readonly array $payments,
        public readonly CarbonInterface $generatedAt,
    ) {}

    public static function build(RecapService $recapService, string $taxPeriod): self
    {
        $recap = $recapService->compute($taxPeriod);

        $invoices = $recapService->invoices($taxPeriod)->map(function (array $row): array {
            /** @var FakturPajak $f */
            $f = $row['faktur'];

            return [
                'taxInvoiceNumber' => $f->tax_invoice_number,
                'taxInvoiceDate' => DateDisplay::date($f->tax_invoice_date),
                'invoiceId' => (string) $f->invoice_id,
                'projectId' => (string) $f->project_id,
                'clientId' => (string) $f->client_id,
                'status' => self::invoiceStatusLabel($f),
                'dpp' => $f->dpp,
                'ppn' => $f->ppn,
                'ppnbm' => $f->ppnbm ?? '0.00',
                'isCounted' => $row['isCounted'] ? 'Ya' : 'Tidak',
            ];
        })->all();

        $payments = $recapService->payments($taxPeriod)->map(fn (PembayaranPajak $p): array => [
            'billingId' => $p->billing_id,
            'ntpn' => (string) $p->ntpn,
            'amountPaid' => $p->amount_paid,
            'status' => self::paymentStatusLabel($p->status),
            'createdAt' => DateDisplay::dateTime($p->created_at),
        ])->all();

        return new self($taxPeriod, $recap, $invoices, $payments, $recap->computedAt);
    }

    /** @return list<array{0: string, 1: string}> pasangan keterangan - nilai untuk ringkasan */
    public function summaryRows(): array
    {
        return [
            ['Masa pajak', DateDisplay::period($this->taxPeriod)],
            ['Jumlah faktur dihitung', (string) $this->recap->invoiceCount],
            ['Total DPP', $this->recap->totalDpp],
            ['Total PPN', $this->recap->totalPpn],
            ['Total PPnBM', $this->recap->totalPpnbm],
            ['Jumlah setor', $this->recap->amountPaid],
            ['Selisih (jumlah setor - total PPN)', $this->recap->paymentDifference],
            ['Status pembayaran', self::recapStatusLabel($this->recap->paymentStatus)],
            ['Dihitung pada', DateDisplay::dateTime($this->generatedAt)],
        ];
    }

    public static function invoiceStatusLabel(FakturPajak $f): string
    {
        $label = match ($f->status) {
            FakturPajak::STATUS_REPLACEMENT => 'Pengganti',
            FakturPajak::STATUS_CANCELLED => 'Dibatalkan',
            default => 'Normal',
        };

        return $f->replacedBy !== null && ! $f->isCancelled() ? $label.' (terganti)' : $label;
    }

    public static function paymentStatusLabel(string $status): string
    {
        return $status === PembayaranPajak::STATUS_CONFIRMED ? 'Confirmed' : 'Incompleted';
    }

    public static function recapStatusLabel(string $status): string
    {
        return match ($status) {
            RekapPajakBulanan::PAYMENT_CONFIRMED => 'Confirmed',
            RekapPajakBulanan::PAYMENT_INCOMPLETE => 'Incompleted',
            default => 'Belum dicatat',
        };
    }
}
