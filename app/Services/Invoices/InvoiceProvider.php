<?php

namespace App\Services\Invoices;

/**
 * Batas integrasi E-1 -> D-2 (invoice). E-1 hanya membaca rujukan invoice; ia tidak
 * menghitung ulang nilai komersial invoice (tanggung jawab D-2).
 */
interface InvoiceProvider
{
    /** Daftar semua invoice yang diketahui D-2 (katalog referensi). */
    public function catalog(): array;

    /** Invoice D-2 atau null bila ID tidak dikenal. */
    public function find(string $invoiceId): ?InvoiceData;

    /**
     * ID proyek yang ID atau namanya memuat $term (huruf besar/kecil diabaikan).
     * Dipakai filter daftar faktur "proyek" karena E-1 hanya menyimpan projectId.
     *
     * @return list<string>
     */
    public function projectIdsMatching(string $term): array;

    /**
     * ID klien yang ID atau namanya memuat $term.
     *
     * @return list<string>
     */
    public function clientIdsMatching(string $term): array;

    /** Apakah projectId dikenal oleh D-2. */
    public function projectExists(string $projectId): bool;
}
