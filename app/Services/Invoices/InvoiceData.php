<?php

namespace App\Services\Invoices;

/**
 * Data invoice D-2 yang dibutuhkan E-1 (rujukan; nilai komersial tetap milik D-2).
 * projectId/clientId/nama mengikuti kontrak D-2 yang disepakati (Q-D2-01: clientId
 * belum ada di D-2, sehingga boleh null).
 */
final class InvoiceData
{
    public function __construct(
        public readonly string $invoiceId,
        public readonly ?string $invoiceNumber,
        public readonly ?string $invoiceDate,   // YYYY-MM-DD
        public readonly ?string $dppAmount,
        public readonly ?string $ppnAmount,
        public readonly ?string $status,        // TERTAGIH / TERBAYAR (D-2)
        public readonly ?string $projectId,
        public readonly ?string $projectName,
        public readonly ?string $clientId,
        public readonly ?string $clientName,
    ) {}
}
