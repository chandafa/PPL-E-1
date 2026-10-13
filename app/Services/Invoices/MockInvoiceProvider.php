<?php

namespace App\Services\Invoices;

use RuntimeException;

/**
 * D-2 tiruan untuk pengembangan lokal sampai kontrak D-2 tersedia. Data fiktif
 * berada di database/data/d2-invoices.json dan mengikuti Skema_Data_Awal_D2
 * (ENT-K02-04 Invoice; ID demo inv-demo-001, prj-demo-001).
 */
class MockInvoiceProvider implements InvoiceProvider
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $invoices = null;

    public function __construct(private readonly string $file) {}

    public function find(string $invoiceId): ?InvoiceData
    {
        $row = $this->all()[$invoiceId] ?? null;

        return $row === null ? null : new InvoiceData(
            invoiceId: $row['invoiceId'],
            invoiceNumber: $row['invoiceNumber'] ?? null,
            invoiceDate: $row['invoiceDate'] ?? null,
            dppAmount: $row['dppAmount'] ?? null,
            ppnAmount: $row['ppnAmount'] ?? null,
            status: $row['status'] ?? null,
            projectId: $row['projectId'] ?? null,
            projectName: $row['projectName'] ?? null,
            clientId: $row['clientId'] ?? null,
            clientName: $row['clientName'] ?? null,
        );
    }

    public function projectIdsMatching(string $term): array
    {
        return $this->matching($term, 'projectId', 'projectName');
    }

    public function clientIdsMatching(string $term): array
    {
        return $this->matching($term, 'clientId', 'clientName');
    }

    public function projectExists(string $projectId): bool
    {
        foreach ($this->all() as $row) {
            if (($row['projectId'] ?? null) === $projectId) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function matching(string $term, string $idKey, string $nameKey): array
    {
        $needle = mb_strtolower($term);
        $ids = [];

        foreach ($this->all() as $row) {
            $id = $row[$idKey] ?? null;
            if ($id === null) {
                continue;
            }
            if (str_contains(mb_strtolower((string) $id), $needle)
                || str_contains(mb_strtolower((string) ($row[$nameKey] ?? '')), $needle)) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /** @return array<string, array<string, mixed>> */
    private function all(): array
    {
        if ($this->invoices === null) {
            if (! is_file($this->file)) {
                throw new RuntimeException("Berkas mock D-2 tidak ditemukan: {$this->file}");
            }

            $decoded = json_decode((string) file_get_contents($this->file), true, flags: JSON_THROW_ON_ERROR);
            $this->invoices = [];
            foreach ($decoded as $row) {
                $this->invoices[$row['invoiceId']] = $row;
            }
        }

        return $this->invoices;
    }
}
