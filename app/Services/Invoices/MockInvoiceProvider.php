<?php

namespace App\Services\Invoices;

use App\Models\Contract;
use App\Models\Invoice;
use Database\Seeders\Support\D2SourceDataset;

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
            ->when($this->sqliteDataset(), fn ($query) => $query->whereIn('invoices.invoice_id', $this->snapshotIds()))
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
                'contracts.project_name',
                'contracts.client_id',
                'contracts.client_name',
            )
            ->orderBy('invoices.invoice_id')
            ->get()
            ->map(fn ($row) => $this->rowToArray($row))
            ->all();
    }

    public function find(string $invoiceId): ?InvoiceData
    {
        if ($this->sqliteDataset() && ! in_array($invoiceId, $this->snapshotIds(), true)) {
            return null;
        }
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
                'contracts.project_name',
                'contracts.client_id',
                'contracts.client_name',
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
            projectName: $row->project_name,
            clientId: $row->client_id,
            clientName: $row->client_name,
        );
    }

    public function projectIdsMatching(string $term): array
    {
        $like = '%'.strtolower(addcslashes($term, '%_\\')).'%';

        return Contract::query()
            ->when($this->sqliteDataset(), fn ($query) => $query->whereIn('contract_id', $this->snapshotContractIds()))
            ->select('project_id')
            ->where(fn ($query) => $query->whereRaw('LOWER(project_id) LIKE ?', [$like])->orWhereRaw('LOWER(project_name) LIKE ?', [$like]))
            ->distinct()
            ->pluck('project_id')
            ->all();
    }

    public function clientIdsMatching(string $term): array
    {
        if ($this->sqliteDataset()) {
            return [];
        }
        $like = '%'.strtolower(addcslashes($term, '%_\\')).'%';

        return Contract::query()->whereNotNull('client_id')
            ->where(fn ($query) => $query->whereRaw('LOWER(client_id) LIKE ?', [$like])->orWhereRaw('LOWER(client_name) LIKE ?', [$like]))
            ->distinct()->pluck('client_id')->all();
    }

    public function projectExists(string $projectId): bool
    {
        return Contract::query()->when($this->sqliteDataset(), fn ($query) => $query->whereIn('contract_id', $this->snapshotContractIds()))->where('project_id', $projectId)->exists();
    }

    public function detail(string $invoiceId): ?array
    {
        $reference = $this->find($invoiceId);
        if ($reference === null) {
            return null;
        }
        $invoice = Invoice::with(['termin.contract', 'taxParameter', 'sourceItems', 'histories'])->find($invoiceId);
        if ($invoice === null) {
            return null;
        }
        $termin = $invoice->termin;
        $contract = $termin->contract;
        $tax = $invoice->taxParameter;

        return [
            'source' => $this->sqliteDataset() ? 'SQLITE_SNAPSHOT' : 'SIMULATED_D2',
            'sourceSha256' => $this->sqliteDataset() ? D2SourceDataset::snapshot()['source']['sha256'] : null,
            'invoice' => get_object_vars($reference) + [
                'terminId' => $invoice->termin_id, 'taxParamId' => $invoice->tax_param_id,
                'pphAmount' => $invoice->pph_amount, 'totalAmount' => $invoice->total_amount,
                'createdBy' => $invoice->created_by,
            ],
            'contract' => [
                'contractId' => $contract->contract_id, 'contractNumber' => $contract->contract_number,
                'contractValue' => $contract->contract_value,
            ],
            'termin' => [
                'terminId' => $termin->termin_id, 'terminNumber' => $termin->termin_number,
                'terminValue' => $termin->termin_value, 'dueDate' => $termin->due_date->format('Y-m-d'), 'status' => $termin->status,
            ],
            'taxParameter' => [
                'taxParamId' => $tax->tax_param_id, 'ppnRate' => $tax->ppn_rate, 'pphRate' => $tax->pph_rate,
                'effectiveFrom' => $tax->effective_from->format('Y-m-d'), 'effectiveTo' => $tax->effective_to?->format('Y-m-d'),
            ],
            'items' => $invoice->sourceItems->map(fn ($item) => [
                'lineNumber' => $item->line_number, 'code' => $item->code, 'description' => $item->description,
                'quantity' => $item->quantity, 'unit' => $item->unit, 'unitPrice' => $item->unit_price, 'amount' => $item->amount,
            ])->all(),
            'history' => $invoice->histories->map(fn ($history) => [
                'status' => $history->status, 'changedAt' => $history->changed_at->toIso8601String(),
                'changedBy' => $history->changed_by, 'note' => $history->note,
            ])->all(),
        ];
    }

    private function sqliteDataset(): bool
    {
        return config('e1.d2.mock_dataset') === 'sqlite';
    }

    private function snapshotIds(): array
    {
        return array_column(D2SourceDataset::rows(), 'invoiceId');
    }

    private function snapshotContractIds(): array
    {
        return array_column(D2SourceDataset::snapshot()['tables']['contracts'], 'contract_id');
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
            'projectName' => $row->project_name,
            'clientId' => $row->client_id,
            'clientName' => $row->client_name,
        ];
    }
}
