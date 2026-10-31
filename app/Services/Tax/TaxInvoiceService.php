<?php

namespace App\Services\Tax;

use App\Exceptions\ApiException;
use App\Models\FakturPajak;
use App\Models\ItemFaktur;
use App\Models\RiwayatPerubahanFaktur;
use App\Services\Auth\F1Identity;
use App\Services\EFaktur\EFakturPdfParser;
use App\Services\EFaktur\ExtractedTaxInvoice;
use App\Services\Invoices\InvoiceData;
use App\Services\Invoices\InvoiceProvider;
use App\Support\Money;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pencatatan, pencarian, koreksi, dan pembatalan Faktur Pajak Keluaran
 * (FTR-KE01-01, 02, 03). Aturan kunci:
 *  - 1 invoice : 1 faktur aktif (Q-04); NSFP unik (Q-02);
 *  - faktur pengganti menunjuk faktur asal yang lalu menjadi non-aktif (Q-07);
 *  - koreksi dan pembatalan selalu menulis riwayat; tidak ada hard delete (Q-05, Q-06).
 */
class TaxInvoiceService
{
    private const CHANGE_FIELDS = ['taxInvoiceNumber', 'taxInvoiceDate', 'taxPeriod', 'dpp', 'ppn', 'ppnbm', 'items', 'status'];

    public function __construct(
        private readonly InvoiceProvider $invoices,
        private readonly EFakturPdfParser $parser,
        private readonly FileStore $files,
        private readonly HistoryRecorder $history,
        private readonly RecapService $recap,
    ) {}

    // ---------------------------------------------------------------------
    // API-KE01-01 Pratinjau ekstraksi (tidak menyimpan)
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed> ExtractionResult
     */
    public function extract(mixed $invoiceId, mixed $file): array
    {
        $invoiceId = $this->requireInvoiceId($invoiceId);
        $binary = $this->files->readPdf($file, 'INVALID_FILE', 'Berkas harus berformat PDF');
        $this->requireInvoice($invoiceId);
        $this->assertNoActiveFaktur($invoiceId);

        $data = $this->parser->parse($binary);
        $this->assertNsfpFree($data->taxInvoiceNumber);

        return [
            'invoiceId' => $invoiceId,
            'taxInvoiceNumber' => $data->taxInvoiceNumber,
            'taxInvoiceDate' => $data->taxInvoiceDate,
            'taxPeriod' => $data->taxPeriod,
            'dpp' => $data->dpp,
            'ppn' => $data->ppn,
            'ppnbm' => $data->ppnbm,
            'items' => $data->items,
        ];
    }

    // ---------------------------------------------------------------------
    // API-KE01-02 Simpan faktur keluaran atau faktur pengganti
    // ---------------------------------------------------------------------

    public function createOutgoing(mixed $invoiceId, mixed $file, mixed $replacesId, F1Identity $actor): FakturPajak
    {
        $invoiceId = $this->requireInvoiceId($invoiceId);
        $binary = $this->files->readPdf($file, 'INVALID_FILE', 'Berkas harus berformat PDF');
        $invoice = $this->requireInvoice($invoiceId);

        $replacesId = is_string($replacesId) && trim($replacesId) !== '' ? trim($replacesId) : null;
        $original = null;

        if ($replacesId !== null) {
            $original = $this->requireFaktur($replacesId);
            if ($original->invoice_id !== $invoiceId) {
                throw ApiException::badRequest('REPLACEMENT_INVOICE_MISMATCH', 'invoiceId faktur pengganti harus sama dengan faktur asal');
            }
            $this->assertReplaceable($original);
        } else {
            $this->assertNoActiveFaktur($invoiceId);
        }

        $data = $this->parser->parse($binary);
        $this->assertNsfpFree($data->taxInvoiceNumber);

        $id = (string) Str::uuid7();
        $pdfUri = $this->files->put('e-faktur', $id, $binary);

        try {
            return DB::transaction(function () use ($id, $pdfUri, $data, $invoice, $original, $actor): FakturPajak {
                // Serialisasi per invoice: dua permintaan serentak tidak boleh melahirkan dua faktur aktif.
                DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [$invoice->invoiceId]);

                if ($original !== null) {
                    $original = $original->fresh();
                    $this->assertReplaceable($original);
                } else {
                    $this->assertNoActiveFaktur($invoice->invoiceId);
                }
                $this->assertNsfpFree($data->taxInvoiceNumber);
                $this->recap->lockPeriods([$data->taxPeriod, $original?->tax_period]);

                $faktur = $this->persist($id, $pdfUri, $data, $invoice, $original, $actor);

                $this->history->record(
                    $faktur->tax_invoice_id,
                    RiwayatPerubahanFaktur::ACTION_CREATE,
                    $actor->akunId,
                    null,
                    $this->history->snapshot($faktur, true)
                        + ['invoiceId' => $faktur->invoice_id]
                        + ($original !== null ? ['replacesTaxInvoiceId' => $original->tax_invoice_id] : []),
                );

                if ($original !== null) {
                    // Faktur asal menjadi non-aktif/terganti dan tertaut ke faktur pengganti (Q-07).
                    $this->history->record(
                        $original->tax_invoice_id,
                        RiwayatPerubahanFaktur::ACTION_STATUS_CHANGE,
                        $actor->akunId,
                        ['replacedByTaxInvoiceId' => null],
                        ['replacedByTaxInvoiceId' => $faktur->tax_invoice_id],
                        'Digantikan oleh faktur pengganti '.$faktur->tax_invoice_number,
                    );
                }

                $this->refreshPeriods($faktur->tax_period, $original?->tax_period);

                return $faktur->fresh(['items', 'replacedBy']);
            });
        } catch (UniqueConstraintViolationException) {
            $this->files->delete($pdfUri);
            throw ApiException::conflict('DUPLICATE_NSFP', 'NSFP sudah terdaftar');
        } catch (Throwable $e) {
            $this->files->delete($pdfUri);
            throw $e;
        }
    }

    // ---------------------------------------------------------------------
    // API-KE01-03 Cari dan filter
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $filters  taxInvoiceNumber, project, client, taxPeriod, status
     */
    public function search(array $filters, mixed $page, mixed $pageSize): LengthAwarePaginator
    {
        $defaultSize = (int) config('e1.pagination.default', 20);
        $maxSize = (int) config('e1.pagination.max', 100);

        $page = $this->intInRange($page, 1, PHP_INT_MAX, 1, 'page harus bilangan bulat >= 1');
        $pageSize = $this->intInRange($pageSize, 1, $maxSize, $defaultSize, "pageSize harus bilangan bulat 1 sampai {$maxSize}");

        $query = FakturPajak::query()->with(['replacedBy:tax_invoice_id,replaces_tax_invoice_id']);

        if (($number = $this->text($filters['taxInvoiceNumber'] ?? null)) !== null) {
            $like = '%'.addcslashes($number, '\\%_').'%';
            $query->where('tax_invoice_number', 'ilike', $like);
        }

        if (($period = $filters['taxPeriod'] ?? null) !== null && $period !== '') {
            if (! RecapService::isValidPeriod($period)) {
                throw ApiException::badRequest('INVALID_FILTER', 'Filter tidak valid: taxPeriod harus berformat YYYY-MM');
            }
            $query->where('tax_period', $period);
        }

        if (($status = $filters['status'] ?? null) !== null && $status !== '') {
            if (! in_array($status, [FakturPajak::STATUS_NORMAL, FakturPajak::STATUS_REPLACEMENT, FakturPajak::STATUS_CANCELLED], true)) {
                throw ApiException::badRequest('INVALID_FILTER', 'Filter tidak valid: status harus NORMAL, REPLACEMENT, atau CANCELLED');
            }
            $query->where('status', $status);
        }

        if (($project = $this->text($filters['project'] ?? null)) !== null) {
            $matches = $this->invoices->projectIdsMatching($project);
            $query->where(fn ($q) => $q->where('project_id', $project)->orWhereIn('project_id', $matches));
        }

        if (($client = $this->text($filters['client'] ?? null)) !== null) {
            $matches = $this->invoices->clientIdsMatching($client);
            $query->where(fn ($q) => $q->where('client_id', $client)->orWhereIn('client_id', $matches));
        }

        return $query
            ->orderByDesc('tax_invoice_date')
            ->orderByDesc('created_at')
            ->orderByDesc('tax_invoice_id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    // ---------------------------------------------------------------------
    // API-KE01-04 Detail
    // ---------------------------------------------------------------------

    public function find(mixed $taxInvoiceId): FakturPajak
    {
        return $this->requireFaktur($taxInvoiceId);
    }

    // ---------------------------------------------------------------------
    // API-KE01-05 Koreksi atau batalkan
    // ---------------------------------------------------------------------

    /**
     * @return array{faktur: FakturPajak, auditId: string}
     */
    public function update(mixed $taxInvoiceId, mixed $changes, mixed $note, F1Identity $actor): array
    {
        $existing = $this->requireFaktur($taxInvoiceId);   // 404 lebih dulu dari 400 dan 409
        $changes = $this->normalizeChanges($changes);
        $note = $this->normalizeNote($note);

        try {
            return DB::transaction(function () use ($taxInvoiceId, $changes, $note, $actor, $existing): array {
                DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [$existing->invoice_id]);
                /** @var FakturPajak $faktur */
                $faktur = FakturPajak::query()->whereKey($taxInvoiceId)->lockForUpdate()->firstOrFail();

                if ($faktur->isCancelled()) {
                    throw ApiException::conflict('TAX_INVOICE_ALREADY_CANCELLED', 'Faktur sudah berstatus Dibatalkan');
                }

                $faktur->load('items');
                $oldPeriod = $faktur->tax_period;
                $originalPeriod = $faktur->replaces_tax_invoice_id !== null
                    ? FakturPajak::query()->whereKey($faktur->replaces_tax_invoice_id)->value('tax_period') : null;
                $this->recap->lockPeriods([$oldPeriod, $changes['taxPeriod'] ?? null, $originalPeriod]);

                $audit = isset($changes['status'])
                    ? $this->cancel($faktur, $note, $actor)
                    : $this->correct($faktur, $changes, $note, $actor);

                $this->refreshPeriods($faktur->tax_period, $oldPeriod);

                return ['faktur' => $faktur->fresh(['items', 'replacedBy']), 'auditId' => $audit->audit_id];
            });
        } catch (UniqueConstraintViolationException) {
            throw ApiException::conflict('DUPLICATE_NSFP', 'NSFP sudah terdaftar pada faktur lain');
        }
    }

    // ---------------------------------------------------------------------
    // API-KE01-06 Riwayat
    // ---------------------------------------------------------------------

    /** @return Collection<int, RiwayatPerubahanFaktur> */
    public function history(mixed $taxInvoiceId): Collection
    {
        return $this->requireFaktur($taxInvoiceId)->histories;
    }

    // ---------------------------------------------------------------------
    // Internal: koreksi, pembatalan, persistensi
    // ---------------------------------------------------------------------

    /** @param array<string, mixed> $changes */
    private function correct(FakturPajak $faktur, array $changes, ?string $note, F1Identity $actor): RiwayatPerubahanFaktur
    {
        $before = [];
        $after = [];
        $currentItems = $this->history->snapshot($faktur, true)['items'];

        $columns = [
            'taxInvoiceNumber' => 'tax_invoice_number',
            'taxInvoiceDate' => 'tax_invoice_date',
            'taxPeriod' => 'tax_period',
            'dpp' => 'dpp',
            'ppn' => 'ppn',
            'ppnbm' => 'ppnbm',
        ];

        $snapshot = $this->history->snapshot($faktur);
        $newValues = [];

        foreach ($columns as $field => $column) {
            if (! array_key_exists($field, $changes)) {
                continue;
            }
            if ($snapshot[$field] === $changes[$field]) {
                continue;
            }
            $before[$field] = $snapshot[$field];
            $after[$field] = $changes[$field];
            $newValues[$column] = $changes[$field];
        }

        $itemsChanged = array_key_exists('items', $changes) && $changes['items'] !== $currentItems;
        if ($itemsChanged) {
            $before['items'] = $currentItems;
            $after['items'] = $changes['items'];
        }

        if ($before === []) {
            throw ApiException::badRequest('INVALID_CHANGE', 'Tidak ada perubahan pada data faktur');
        }

        if (isset($newValues['tax_invoice_number'])) {
            $this->assertNsfpFree($newValues['tax_invoice_number'], $faktur->tax_invoice_id);
        }

        if ($newValues !== []) {
            $faktur->forceFill($newValues)->save();
        } else {
            $faktur->touch();
        }

        if ($itemsChanged) {
            $this->replaceItems($faktur, $changes['items']);
        }

        return $this->history->record(
            $faktur->tax_invoice_id,
            RiwayatPerubahanFaktur::ACTION_UPDATE,
            $actor->akunId,
            $before,
            $after,
            $note,
        );
    }

    private function cancel(FakturPajak $faktur, ?string $note, F1Identity $actor): RiwayatPerubahanFaktur
    {
        // Melepas B pada A -> B -> C akan mengaktifkan A dan C sekaligus.
        if ($faktur->isReplaced()) {
            throw ApiException::conflict('INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE', 'Batalkan faktur pengganti terakhir terlebih dahulu');
        }

        $originalId = $faktur->replaces_tax_invoice_id;

        $before = ['status' => $faktur->status];
        $after = ['status' => FakturPajak::STATUS_CANCELLED];

        // 03: replacesTaxInvoiceId hanya terisi bila status REPLACEMENT. Tautan dilepas saat
        // dibatalkan sehingga faktur asal kembali berlaku (riwayat tetap menyimpan tautannya).
        if ($originalId !== null) {
            $before['replacesTaxInvoiceId'] = $originalId;
            $after['replacesTaxInvoiceId'] = null;
        }

        $faktur->forceFill([
            'status' => FakturPajak::STATUS_CANCELLED,
            'replaces_tax_invoice_id' => null,
        ])->save();

        $audit = $this->history->record(
            $faktur->tax_invoice_id,
            RiwayatPerubahanFaktur::ACTION_STATUS_CHANGE,
            $actor->akunId,
            $before,
            $after,
            $note,
        );

        if ($originalId !== null) {
            $this->history->record(
                $originalId,
                RiwayatPerubahanFaktur::ACTION_STATUS_CHANGE,
                $actor->akunId,
                ['replacedByTaxInvoiceId' => $faktur->tax_invoice_id],
                ['replacedByTaxInvoiceId' => null],
                'Faktur pengganti '.$faktur->tax_invoice_number.' dibatalkan; faktur ini berlaku kembali',
            );

            $original = FakturPajak::query()->find($originalId);
            if ($original !== null) {
                $this->recap->refresh($original->tax_period);
            }
        }

        return $audit;
    }

    private function persist(string $id, string $pdfUri, ExtractedTaxInvoice $data, InvoiceData $invoice, ?FakturPajak $original, F1Identity $actor): FakturPajak
    {
        $faktur = new FakturPajak;
        $faktur->forceFill([
            'tax_invoice_id' => $id,
            'type' => FakturPajak::TYPE_OUTGOING,
            'invoice_id' => $invoice->invoiceId,
            'project_id' => $invoice->projectId,
            'client_id' => $invoice->clientId,
            'tax_invoice_number' => $data->taxInvoiceNumber,
            'status' => $original !== null ? FakturPajak::STATUS_REPLACEMENT : FakturPajak::STATUS_NORMAL,
            'replaces_tax_invoice_id' => $original?->tax_invoice_id,
            'tax_invoice_date' => $data->taxInvoiceDate,
            'tax_period' => $data->taxPeriod,
            'vendor_name' => null,
            'dpp' => $data->dpp,
            'ppn' => $data->ppn,
            'ppnbm' => $data->ppnbm,
            'pdf_uri' => $pdfUri,
            'created_by_akun_id' => $actor->akunId,
        ])->save();

        $this->replaceItems($faktur, $data->items);

        return $faktur->load('items');
    }

    /** @param list<array<string, string>> $items */
    private function replaceItems(FakturPajak $faktur, array $items): void
    {
        ItemFaktur::query()->where('tax_invoice_id', $faktur->tax_invoice_id)->delete();

        foreach (array_values($items) as $index => $item) {
            ItemFaktur::query()->create([
                'tax_invoice_id' => $faktur->tax_invoice_id,
                'line_no' => $index + 1,
                'item_code' => $item['itemCode'],
                'item_name' => $item['itemName'],
                'sale_replacement_advance_term_amount' => $item['saleReplacementAdvanceTermAmount'],
                'discount_amount' => $item['discountAmount'],
                'received_advance_amount' => $item['receivedAdvanceAmount'],
            ]);
        }
    }

    private function refreshPeriods(?string ...$periods): void
    {
        $periods = array_unique(array_filter($periods));
        sort($periods);
        foreach ($periods as $period) {
            $this->recap->refresh($period);
        }
    }

    // ---------------------------------------------------------------------
    // Internal: pemeriksaan
    // ---------------------------------------------------------------------

    private function requireInvoiceId(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '' || mb_strlen($value) > 100) {
            throw ApiException::badRequest('INVALID_INVOICE_ID', 'invoiceId wajib diisi');
        }

        return $value;
    }

    private function requireInvoice(string $invoiceId): InvoiceData
    {
        return $this->invoices->find($invoiceId)
            ?? throw ApiException::notFound('INVOICE_NOT_FOUND', 'Invoice tidak ditemukan');
    }

    private function requireFaktur(mixed $id): FakturPajak
    {
        $faktur = is_string($id) && Str::isUuid($id)
            ? FakturPajak::query()->with(['items', 'replacedBy'])->find($id)
            : null;

        return $faktur ?? throw ApiException::notFound('TAX_INVOICE_NOT_FOUND', 'Faktur pajak tidak ditemukan');
    }

    private function assertNoActiveFaktur(string $invoiceId): void
    {
        $exists = FakturPajak::query()->active()
            ->where('type', FakturPajak::TYPE_OUTGOING)
            ->where('invoice_id', $invoiceId)
            ->exists();

        if ($exists) {
            throw ApiException::conflict('INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE', 'Invoice ini sudah memiliki faktur aktif');
        }
    }

    private function assertReplaceable(FakturPajak $original): void
    {
        if ($original->isCancelled()) {
            throw ApiException::conflict('TAX_INVOICE_ALREADY_CANCELLED', 'Faktur asal sudah berstatus Dibatalkan');
        }

        if ($original->isReplaced()) {
            throw ApiException::conflict('INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE', 'Invoice ini sudah memiliki faktur aktif');
        }
    }

    private function assertNsfpFree(string $nsfp, ?string $exceptTaxInvoiceId = null): void
    {
        $query = FakturPajak::query()->where('tax_invoice_number', $nsfp);
        if ($exceptTaxInvoiceId !== null) {
            $query->where('tax_invoice_id', '<>', $exceptTaxInvoiceId);
        }

        if ($query->exists()) {
            throw ApiException::conflict('DUPLICATE_NSFP', 'NSFP sudah terdaftar');
        }
    }

    // ---------------------------------------------------------------------
    // Internal: normalisasi masukan
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed> perubahan yang sudah divalidasi dan dinormalkan
     */
    private function normalizeChanges(mixed $changes): array
    {
        if (! is_array($changes) || $changes === [] || array_is_list($changes)) {
            throw $this->invalidChange('changes wajib berupa objek berisi minimal satu field');
        }

        foreach (array_keys($changes) as $field) {
            if (! in_array($field, self::CHANGE_FIELDS, true)) {
                throw $this->invalidChange("Field {$field} tidak boleh diubah");
            }
        }

        if (array_key_exists('status', $changes)) {
            if ($changes['status'] !== FakturPajak::STATUS_CANCELLED) {
                throw $this->invalidChange('status hanya boleh CANCELLED');
            }
            if (count($changes) > 1) {
                throw $this->invalidChange('Pembatalan tidak dapat digabung dengan koreksi field lain');
            }

            return ['status' => FakturPajak::STATUS_CANCELLED];
        }

        $out = [];

        foreach ($changes as $field => $value) {
            $out[$field] = match ($field) {
                'taxInvoiceNumber' => $this->nonEmptyString($value, 'taxInvoiceNumber', 64),
                'taxInvoiceDate' => $this->date($value, 'taxInvoiceDate'),
                'taxPeriod' => RecapService::isValidPeriod($value) ? $value : throw $this->invalidChange('taxPeriod harus berformat YYYY-MM'),
                'dpp', 'ppn' => $this->money($value, $field),
                'ppnbm' => $value === null ? null : $this->money($value, 'ppnbm'),
                'items' => $this->items($value),
            };
        }

        return $out;
    }

    /** @return list<array{itemCode: string, itemName: string, saleReplacementAdvanceTermAmount: string, discountAmount: string, receivedAdvanceAmount: string}> */
    private function items(mixed $value): array
    {
        if (! is_array($value) || $value === [] || ! array_is_list($value)) {
            throw $this->invalidChange('items wajib berupa daftar berisi minimal satu item');
        }

        return array_map(function ($item): array {
            if (! is_array($item)) {
                throw $this->invalidChange('Setiap item harus berupa objek');
            }

            return [
                'itemCode' => $this->nonEmptyString($item['itemCode'] ?? null, 'itemCode', 100),
                'itemName' => $this->nonEmptyString($item['itemName'] ?? null, 'itemName', 500),
                'saleReplacementAdvanceTermAmount' => $this->money($item['saleReplacementAdvanceTermAmount'] ?? null, 'saleReplacementAdvanceTermAmount'),
                'discountAmount' => ($item['discountAmount'] ?? null) === null ? '0.00' : $this->money($item['discountAmount'], 'discountAmount'),
                'receivedAdvanceAmount' => ($item['receivedAdvanceAmount'] ?? null) === null ? '0.00' : $this->money($item['receivedAdvanceAmount'], 'receivedAdvanceAmount'),
            ];
        }, $value);
    }

    private function money(mixed $value, string $field): string
    {
        if (! Money::isValid($value)) {
            throw $this->invalidChange("{$field} harus berupa nominal string desimal tidak negatif, contoh \"100000000.00\"");
        }

        return Money::normalize($value);
    }

    private function date(mixed $value, string $field): string
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw $this->invalidChange("{$field} harus berformat YYYY-MM-DD");
        }

        return $value;
    }

    private function nonEmptyString(mixed $value, string $field, int $max): string
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '' || mb_strlen($value) > $max) {
            throw $this->invalidChange("{$field} wajib diisi (maksimum {$max} karakter)");
        }

        return $value;
    }

    private function normalizeNote(mixed $note): ?string
    {
        if ($note === null || $note === '') {
            return null;
        }

        if (! is_string($note) || mb_strlen($note) > 500) {
            throw $this->invalidChange('note harus berupa teks maksimum 500 karakter');
        }

        return trim($note);
    }

    private function invalidChange(string $detail): ApiException
    {
        return ApiException::badRequest('INVALID_CHANGE', 'Field tidak boleh diubah atau nilai tidak valid: '.$detail);
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function intInRange(mixed $value, int $min, int $max, int $default, string $message): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (! is_numeric($value) || (string) (int) $value !== (string) $value || (int) $value < $min || (int) $value > $max) {
            throw ApiException::badRequest('INVALID_FILTER', 'Filter tidak valid: '.$message);
        }

        return (int) $value;
    }
}
