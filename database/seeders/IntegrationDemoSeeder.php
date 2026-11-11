<?php

namespace Database\Seeders;

use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IntegrationDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || config('e1.d2.driver') !== 'http') {
            throw new RuntimeException('IntegrationDemoSeeder hanya untuk integrasi HTTP local/testing.');
        }
        DB::transaction(function (): void {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['e1.integration-demo']);
            }
            $timestamp = '2026-10-11 02:00:00';
            $this->insertMissing('contracts', ['contract_id' => 'ktr-demo-sample'], [
                'project_id' => 'prj-demo-001', 'contract_number' => 'KTR/2026/SAMPLE',
                'contract_value' => '200000000.00', 'created_by' => 'emp-demo-007',
                'created_at' => '2026-10-01 00:00:00', 'updated_at' => $timestamp,
            ], ['project_id', 'contract_number']);
            $this->insertMissing('tax_parameters', ['tax_param_id' => 'pjk-demo-001'], [
                'contract_id' => null, 'ppn_rate' => '11.00', 'pph_rate' => '2.00',
                'effective_from' => '2026-01-01', 'effective_to' => null,
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ], ['contract_id']);
            $this->insertMissing('termins', ['termin_id' => 'trm-demo-sample'], [
                'contract_id' => 'ktr-demo-sample', 'termin_number' => 1,
                'termin_value' => '100000000.00', 'due_date' => '2026-10-10',
                'status' => 'SUDAH_DITAGIHKAN', 'created_at' => $timestamp, 'updated_at' => $timestamp,
            ], ['contract_id', 'termin_number']);
            $this->insertMissing('invoices', ['invoice_id' => 'inv-demo-sample'], [
                'invoice_number' => 'INV-2026-SAMPLE', 'termin_id' => 'trm-demo-sample',
                'tax_param_id' => 'pjk-demo-001', 'invoice_date' => '2026-10-11',
                'dpp_amount' => '100000000.00', 'ppn_amount' => '11000000.00',
                'pph_amount' => '2000000.00', 'total_amount' => '109000000.00',
                'status' => 'TERTAGIH', 'created_by' => 'emp-demo-012',
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ], ['invoice_number', 'termin_id', 'tax_param_id']);
            $this->insertMissing('invoice_histories', ['invoice_id' => 'inv-demo-sample', 'status' => 'TERTAGIH'], [
                'changed_at' => $timestamp, 'changed_by' => 'emp-demo-012',
                'note' => 'Invoice diterbitkan oleh petugas keuangan',
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ], []);
        });
    }

    private function insertMissing(string $table, array $key, array $row, array $identity): void
    {
        $existing = DB::table($table)->where($key)->first();
        if ($existing !== null) {
            foreach ($identity as $field) {
                if ((string) $existing->$field !== (string) $row[$field]) {
                    throw new RuntimeException('Konflik seed integrasi: '.$table.'.'.implode('/', $key).'.'.$field.'. Data lama dipertahankan.');
                }
            }

            return;
        }
        try {
            DB::table($table)->insert($key + $row);
        } catch (QueryException $error) {
            throw new RuntimeException('Konflik seed integrasi: '.$table.'.'.implode('/', $key).'. Seluruh seed dibatalkan.', 0, $error);
        }
    }
}
