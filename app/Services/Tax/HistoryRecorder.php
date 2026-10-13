<?php

namespace App\Services\Tax;

use App\Models\FakturPajak;
use App\Models\RiwayatPerubahanFaktur;
use Illuminate\Support\Carbon;

/**
 * Menulis RiwayatPerubahanFaktur (append-only). Satu baris = satu perubahan:
 * siapa, kapan, aksi, nilai sebelum dan sesudah (Q-05..Q-07).
 */
class HistoryRecorder
{
    /**
     * @param  array<string, mixed>|null  $before  wajib kecuali CREATE
     * @param  array<string, mixed>|null  $after  wajib kecuali DELETE
     */
    public function record(
        string $taxInvoiceId,
        string $action,
        string $actorAkunId,
        ?array $before,
        ?array $after,
        ?string $note = null,
    ): RiwayatPerubahanFaktur {
        return RiwayatPerubahanFaktur::create([
            'tax_invoice_id' => $taxInvoiceId,
            'action' => $action,
            'actor_akun_id' => $actorAkunId,
            'changed_at' => Carbon::now('UTC'),
            'before_snapshot' => $before,
            'after_snapshot' => $after,
            'note' => $note,
        ]);
    }

    /**
     * Bidang faktur yang dicatat pada snapshot (bentuk API: camelCase, nominal string).
     *
     * @return array<string, mixed>
     */
    public function snapshot(FakturPajak $faktur, bool $withItems = false): array
    {
        $snapshot = [
            'taxInvoiceNumber' => $faktur->tax_invoice_number,
            'taxInvoiceDate' => $faktur->tax_invoice_date->format('Y-m-d'),
            'taxPeriod' => $faktur->tax_period,
            'dpp' => $faktur->dpp,
            'ppn' => $faktur->ppn,
            'ppnbm' => $faktur->ppnbm,
            'status' => $faktur->status,
        ];

        if ($withItems) {
            $snapshot['items'] = $faktur->items->map(fn ($item) => [
                'itemCode' => $item->item_code,
                'itemName' => $item->item_name,
                'saleReplacementAdvanceTermAmount' => $item->sale_replacement_advance_term_amount,
                'discountAmount' => $item->discount_amount ?? '0.00',
                'receivedAdvanceAmount' => $item->received_advance_amount ?? '0.00',
            ])->all();
        }

        return $snapshot;
    }
}
