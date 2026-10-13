<?php

namespace App\Services\Tax;

use Carbon\CarbonInterface;

/**
 * Hasil hitung rekap satu masa pajak (read model ENT-KE01-03).
 */
final class RecapData
{
    public function __construct(
        public readonly string $taxPeriod,
        public readonly int $invoiceCount,
        public readonly string $totalDpp,
        public readonly string $totalPpn,
        public readonly string $totalPpnbm,
        public readonly string $amountPaid,
        public readonly string $paymentDifference,
        public readonly string $paymentStatus,   // NOT_RECORDED / INCOMPLETE / CONFIRMED
        public readonly CarbonInterface $computedAt,
    ) {}
}
