<?php

namespace App\Services\EFaktur;

/**
 * Hasil ekstraksi PDF e-Faktur (Q-02): NSFP, tanggal, DPP, PPN, PPnBM, dan item.
 * Semua nominal berupa string decimal 2 digit ("100000000.00").
 *
 * @phpstan-type Item array{itemCode: string, itemName: string, saleReplacementAdvanceTermAmount: string, discountAmount: string, receivedAdvanceAmount: string}
 */
final class ExtractedTaxInvoice
{
    /**
     * @param  list<array{itemCode: string, itemName: string, saleReplacementAdvanceTermAmount: string, discountAmount: string, receivedAdvanceAmount: string}>  $items
     */
    public function __construct(
        public readonly string $taxInvoiceNumber,
        public readonly string $taxInvoiceDate,   // YYYY-MM-DD
        public readonly string $taxPeriod,        // YYYY-MM (bulan tanggal faktur, Q-12)
        public readonly string $dpp,
        public readonly string $ppn,
        public readonly string $ppnbm,
        public readonly array $items,
    ) {}
}
