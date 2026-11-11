<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RestoredD2DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('RestoredD2DemoSeeder hanya tersedia di local/testing.');
        }
        $rows = json_decode(file_get_contents(__DIR__.'/../data/d2-invoices.json'), true, flags: JSON_THROW_ON_ERROR);
        $rows = array_filter($rows, static function (array $row): bool {
            if (preg_match('/^inv-demo-(\d{3})$/D', $row['invoiceId'], $match) !== 1) {
                return false;
            }
            $number = (int) $match[1];

            return ($number >= 1 && $number <= 5) || ($number >= 10 && $number <= 35);
        });
        if (count($rows) !== 31) {
            throw new RuntimeException('Dataset pemulihan D-2 harus berisi 31 invoice demo.');
        }
        DB::transaction(function () use ($rows): void {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['e1.restore-d2-demo']);
            }
            $timestamp = '2026-10-11 02:00:00';
            foreach ($rows as $row) {
                // ID 001 can already belong to the SQLite snapshot; preserve its entire source chain.
                if (DB::table('invoices')->where('invoice_id', $row['invoiceId'])->exists()) {
                    continue;
                }
                if (DB::table('invoices')->where('invoice_number', $row['invoiceNumber'])->exists()) {
                    $this->command?->warn('Lewati '.$row['invoiceId'].': nomor '.$row['invoiceNumber'].' sudah digunakan invoice lain.');

                    continue;
                }
                $contractId = 'ktr-restore-'.$row['invoiceId'];
                $terminId = 'trm-restore-'.$row['invoiceId'];
                $taxId = 'pjk-restore-'.$row['invoiceId'];
                $timestamps = ['created_at' => $timestamp, 'updated_at' => $timestamp];
                $this->insertMissing('contracts', 'contract_id', $contractId, [
                    'project_id' => $row['projectId'], 'contract_number' => 'KTR/RESTORE/'.$row['invoiceNumber'],
                    'contract_value' => $row['totalAmount'], 'created_by' => 'e1-demo-seeder',
                ] + $timestamps);
                $this->insertMissing('termins', 'termin_id', $terminId, [
                    'contract_id' => $contractId, 'termin_number' => 1, 'termin_value' => $row['dppAmount'],
                    'due_date' => $row['invoiceDate'], 'status' => 'SUDAH_DITAGIHKAN',
                ] + $timestamps);
                $this->insertMissing('tax_parameters', 'tax_param_id', $taxId, [
                    'contract_id' => $contractId, 'ppn_rate' => '11.00', 'pph_rate' => '2.00',
                    'effective_from' => '2026-01-01', 'effective_to' => null,
                ] + $timestamps);
                DB::table('invoices')->insert([
                    'invoice_id' => $row['invoiceId'], 'invoice_number' => $row['invoiceNumber'],
                    'termin_id' => $terminId, 'tax_param_id' => $taxId, 'invoice_date' => $row['invoiceDate'],
                    'dpp_amount' => $row['dppAmount'], 'ppn_amount' => $row['ppnAmount'],
                    'pph_amount' => $row['pphAmount'], 'total_amount' => $row['totalAmount'],
                    'status' => $row['status'], 'created_by' => 'e1-demo-seeder',
                ] + $timestamps);
                DB::table('invoice_histories')->insert([
                    'invoice_id' => $row['invoiceId'], 'status' => $row['status'], 'changed_at' => $timestamp,
                    'changed_by' => 'e1-demo-seeder', 'note' => 'Pemulihan dataset demo E-1 dari riwayat Git fa49be3',
                ] + $timestamps);
            }
        });
        $this->command?->info('Invoice demo D-2 dipulihkan; invoice yang sudah ada dipertahankan.');
    }

    private function insertMissing(string $table, string $key, string $id, array $values): void
    {
        if (! DB::table($table)->where($key, $id)->exists()) {
            DB::table($table)->insert([$key => $id] + $values);
        }
    }
}
