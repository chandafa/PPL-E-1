<?php

namespace App\Services\Invoices;

use App\Models\Contract;
use App\Models\Invoice;

/**
 * D-2 tiruan untuk pengembangan lokal sampai kontrak D-2 tersedia. Data invoice
 * D-2 dibaca dari tabel `invoices` di database E-1 (diisi melalui migrasi dan
 * ModulD2Seeder dari D-2), bukan dari berkas JSON. E-1 hanya membaca; tidak
 * mengubah data di sumber asli D-2.
 */
class MockInvoiceProvider implements InvoiceProvider
{
    /** @return list<array<string, mixed>> */
    public function catalog(): array
    {
        return Invoice::query()
            ->join('termins', 'invoices.termin_id', '=', 'termins.termin_id')
            ->join('contracts', 'termins.contract_id', '=', 'contracts.contract_id')
            ->select(
                'invoices.invoice_id',
                'invoices.invoice_number',
                'invoices.invoice_date',
                'invoices.dpp_amount',
                'invoices.ppn_amount',
                'invoices.status',
                'contracts.project_id',
            )
            ->orderBy('invoices.invoice_id')
            ->get()
            ->map(fn ($row) => $this->rowToArray($row))
            ->all();
    }

    public function find(string $invoiceId): ?InvoiceData
    {
        $row = Invoice::query()
            ->join('termins', 'invoices.termin_id', '=', 'termins.termin_id')
            ->join('contracts', 'termins.contract_id', '=', 'contracts.contract_id')
            ->select(
                'invoices.invoice_id',
                'invoices.invoice_number',
                'invoices.invoice_date',
                'invoices.dpp_amount',
                'invoices.ppn_amount',
                'invoices.status',
                'contracts.project_id',
            )
            ->where('invoices.invoice_id', $invoiceId)
            ->first();

        if ($row === null) {
            return null;
        }

        return new InvoiceData(
            invoiceId: $row->invoice_id,
            invoiceNumber: $row->invoice_number,
            invoiceDate: $row->invoice_date ? $row->invoice_date->format('Y-m-d') : null,
            dppAmount: $row->dpp_amount !== null ? (string) $row->dpp_amount : null,
            ppnAmount: $row->ppn_amount !== null ? (string) $row->ppn_amount : null,
            status: $row->status,
            projectId: $row->project_id,
            projectName: null,
            clientId: null,
            clientName: null,
        );
    }

    public function projectIdsMatching(string $term): array
    {
        $like = '%'.strtolower(addcslashes($term, '%_\\')).'%';

        return Contract::query()
            ->select('project_id')
            ->whereRaw('LOWER(project_id) LIKE ?', [$like])
            ->distinct()
            ->pluck('project_id')
            ->all();
    }

    public function clientIdsMatching(string $term): array
    {
        return [];
    }

    public function projectExists(string $projectId): bool
    {
        return Contract::query()->where('project_id', $projectId)->exists();
    }

    /** @return array<string, mixed> */
    private function rowToArray(mixed $row): array
    {
        return [
            'invoiceId' => $row->invoice_id,
            'invoiceNumber' => $row->invoice_number,
            'invoiceDate' => $row->invoice_date ? $row->invoice_date->format('Y-m-d') : null,
            'dppAmount' => $row->dpp_amount !== null ? (string) $row->dpp_amount : null,
            'ppnAmount' => $row->ppn_amount !== null ? (string) $row->ppn_amount : null,
            'status' => $row->status,
            'projectId' => $row->project_id,
            'projectName' => null,
            'clientId' => null,
            'clientName' => null,
        ];
    }
}
