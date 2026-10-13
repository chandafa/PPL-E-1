<?php

namespace App\Http\Resources;

use App\Models\FakturPajak;
use App\Models\ItemFaktur;
use App\Models\PembayaranPajak;
use App\Models\RiwayatPerubahanFaktur;
use App\Services\Tax\RecapData;
use Carbon\CarbonInterface;

/**
 * Pengubah model ke bentuk JSON kontrak (openapi.yaml): camelCase, nominal
 * sebagai string, waktu RFC 3339 UTC ("...Z").
 */
final class TaxPresenter
{
    public static function time(?CarbonInterface $time): ?string
    {
        return $time?->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /** ItemFaktur: nilai kosong disajikan "0.00" (kontrak: Money wajib). */
    public static function item(ItemFaktur $item): array
    {
        return [
            'itemCode' => $item->item_code,
            'itemName' => $item->item_name,
            'saleReplacementAdvanceTermAmount' => $item->sale_replacement_advance_term_amount,
            'discountAmount' => $item->discount_amount ?? '0.00',
            'receivedAdvanceAmount' => $item->received_advance_amount ?? '0.00',
        ];
    }

    /** TaxInvoice (detail/updated). */
    public static function invoice(FakturPajak $f): array
    {
        return [
            'taxInvoiceId' => $f->tax_invoice_id,
            'type' => $f->type,
            'invoiceId' => $f->invoice_id,
            'projectId' => $f->project_id,
            'clientId' => $f->client_id,
            'vendorName' => $f->vendor_name,
            'taxInvoiceNumber' => $f->tax_invoice_number,
            'taxInvoiceDate' => $f->tax_invoice_date->format('Y-m-d'),
            'taxPeriod' => $f->tax_period,
            'dpp' => $f->dpp,
            'ppn' => $f->ppn,
            'ppnbm' => $f->ppnbm,
            'status' => $f->status,
            'pdfUri' => $f->pdf_uri,
            'createdByAkunId' => $f->created_by_akun_id,
            'createdAt' => self::time($f->created_at),
            'updatedAt' => self::time($f->updated_at),
        ];
    }

    /** TaxInvoiceCreated (API-KE01-02). */
    public static function created(FakturPajak $f): array
    {
        return [
            'taxInvoiceId' => $f->tax_invoice_id,
            'type' => $f->type,
            'invoiceId' => $f->invoice_id,
            'vendorName' => $f->vendor_name,
            'status' => $f->status,
            'replacesTaxInvoiceId' => $f->replaces_tax_invoice_id,
            'taxInvoiceNumber' => $f->tax_invoice_number,
            'taxInvoiceDate' => $f->tax_invoice_date->format('Y-m-d'),
            'taxPeriod' => $f->tax_period,
            'dpp' => $f->dpp,
            'ppn' => $f->ppn,
            'ppnbm' => $f->ppnbm,
            'items' => $f->items->map(fn (ItemFaktur $i) => self::item($i))->values()->all(),
            'createdByAkunId' => $f->created_by_akun_id,
            'createdAt' => self::time($f->created_at),
        ];
    }

    /** TaxInvoiceSummary (baris daftar API-KE01-03); butuh relasi replacedBy dimuat. */
    public static function summary(FakturPajak $f): array
    {
        return [
            'taxInvoiceId' => $f->tax_invoice_id,
            'type' => $f->type,
            'taxInvoiceNumber' => $f->tax_invoice_number,
            'taxInvoiceDate' => $f->tax_invoice_date->format('Y-m-d'),
            'invoiceId' => $f->invoice_id,
            'projectId' => $f->project_id,
            'clientId' => $f->client_id,
            'vendorName' => $f->vendor_name,
            'taxPeriod' => $f->tax_period,
            'dpp' => $f->dpp,
            'ppn' => $f->ppn,
            'status' => $f->status,
            'replacedByTaxInvoiceId' => $f->replacedBy?->tax_invoice_id,
        ];
    }

    /** TaxInvoiceDetail (API-KE01-04). */
    public static function detail(FakturPajak $f): array
    {
        return [
            'taxInvoice' => self::invoice($f),
            'items' => $f->items->map(fn (ItemFaktur $i) => self::item($i))->values()->all(),
            'replacesTaxInvoiceId' => $f->replaces_tax_invoice_id,
            'replacedByTaxInvoiceId' => $f->replacedBy?->tax_invoice_id,
        ];
    }

    public static function history(RiwayatPerubahanFaktur $h): array
    {
        return [
            'auditId' => $h->audit_id,
            'action' => $h->action,
            'actorAkunId' => $h->actor_akun_id,
            'changedAt' => self::time($h->changed_at),
            'beforeSnapshot' => $h->before_snapshot,
            'afterSnapshot' => $h->after_snapshot,
            'note' => $h->note,
        ];
    }

    /** RecapSummary (API-KE01-07). */
    public static function recap(RecapData $r): array
    {
        return [
            'taxPeriod' => $r->taxPeriod,
            'invoiceCount' => $r->invoiceCount,
            'totalDpp' => $r->totalDpp,
            'totalPpn' => $r->totalPpn,
            'totalPpnbm' => $r->totalPpnbm,
            'amountPaid' => $r->amountPaid,
            'paymentDifference' => $r->paymentDifference,
            'paymentStatus' => $r->paymentStatus,
            'computedAt' => self::time($r->computedAt),
        ];
    }

    /** RecapPayment. */
    public static function payment(PembayaranPajak $p): array
    {
        return [
            'taxPaymentId' => $p->tax_payment_id,
            'amountPaid' => $p->amount_paid,
            'billingId' => $p->billing_id,
            'ntpn' => $p->ntpn,
            'status' => $p->status,
            'createdAt' => self::time($p->created_at),
        ];
    }
}
