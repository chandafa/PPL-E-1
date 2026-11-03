<?php

namespace Database\Seeders;

use App\Models\FakturPajak;
use Database\Seeders\Support\D2SourceDataset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class D2SourceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || config('e1.d2.driver') !== 'mock' || config('e1.d2.mock_dataset') !== 'sqlite') {
            throw new RuntimeException('D2SourceDemoSeeder hanya tersedia di local/testing dengan D2_DRIVER=mock dan D2_MOCK_DATASET=sqlite.');
        }

        DB::transaction(function (): void {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['e1.d2-source-demo']);
            }
            $tables = D2SourceDataset::snapshot()['tables'];
            foreach (['contracts' => 'contract_id', 'termins' => 'termin_id', 'tax_parameters' => 'tax_param_id', 'invoices' => 'invoice_id'] as $table => $key) {
                foreach ($tables[$table] as $row) {
                    $existing = DB::table($table)->where($key, $row[$key])->first();
                    if ($existing !== null) {
                        foreach ($row as $field => $value) {
                            if (in_array($field, ['created_at', 'updated_at'], true) || $this->same($existing->$field, $value)) {
                                continue;
                            }
                            // Seed bawaan lama belum mencatat penerbitan invoice termin kedua.
                            if ($table === 'termins' && $row[$key] === 'trm-demo-002' && $field === 'status'
                                && $existing->$field === 'SIAP_DITAGIH' && $value === 'SUDAH_DITAGIHKAN'
                                && ! FakturPajak::whereIn('invoice_id', DB::table('invoices')->where('termin_id', $row[$key])->select('invoice_id'))->exists()) {
                                continue;
                            }
                            throw new RuntimeException("Konflik snapshot SQLite: {$table}.{$row[$key]}.{$field}; seluruh perubahan dibatalkan.");
                        }
                    }
                    if ($table === 'contracts') {
                        $row += ['project_name' => null, 'client_id' => null, 'client_name' => null];
                        if ($existing !== null && ($existing->project_name !== null || $existing->client_id !== null || $existing->client_name !== null)) {
                            throw new RuntimeException('Konflik metadata kontrak snapshot SQLite: '.$row[$key]);
                        }
                    }
                    if ($existing === null || array_filter($row, fn ($value, $field) => ! $this->same($existing->$field, $value), ARRAY_FILTER_USE_BOTH) !== []) {
                        DB::table($table)->updateOrInsert([$key => $row[$key]], $row);
                    }
                }
            }
            foreach ($tables['invoice_histories'] as $row) {
                // ID riwayat adalah surrogate lokal; jangan menimpa ID milik dataset lama.
                unset($row['id']);
                $key = [
                    'invoice_id' => $row['invoice_id'], 'status' => $row['status'], 'changed_at' => $row['changed_at'],
                ];
                $existing = DB::table('invoice_histories')->where($key)->first();
                if ($existing === null || array_filter($row, fn ($value, $field) => ! $this->same($existing->$field, $value), ARRAY_FILTER_USE_BOTH) !== []) {
                    DB::table('invoice_histories')->updateOrInsert($key, $row);
                }
            }
            $ids = array_column($tables['invoices'], 'invoice_id');
            if (DB::table('d2_invoice_items')->whereIn('invoice_id', $ids)->exists()) {
                throw new RuntimeException('Snapshot SQLite tidak memiliki rincian item; ditemukan item sumber tambahan.');
            }
        });
        $this->command?->info('Snapshot database.sqlite: 3 kontrak, 4 termin, 1 parameter pajak, 2 invoice dan 2 riwayat tersedia.');
    }

    private function same(mixed $actual, mixed $expected): bool
    {
        if ($actual === null || $expected === null) {
            return $actual === $expected;
        }
        if (is_numeric($actual) && is_numeric($expected)) {
            return bccomp((string) $actual, (string) $expected, 2) === 0;
        }

        return (string) $actual === (string) $expected;
    }
}
