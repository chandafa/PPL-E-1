<?php

namespace App\Support\Integration;

use Illuminate\Database\Connection;

class SeedRepair
{
    private const KEYS = [
        'contracts' => 'contract_id', 'termins' => 'termin_id', 'tax_parameters' => 'tax_param_id',
        'invoices' => 'invoice_id', 'invoice_histories' => 'id',
    ];

    public static function run(Connection $database, callable $seed): void
    {
        $database->transaction(function () use ($database, $seed): void {
            $existing = [];
            foreach (self::KEYS as $table => $key) {
                $existing[$table] = $database->table($table)->orderBy($key)->lockForUpdate()->get();
            }
            $seed();
            // Upstream disables model events, so restore original rows inside the same transaction.
            foreach (self::KEYS as $table => $key) {
                foreach ($existing[$table] as $row) {
                    $database->table($table)->where($key, $row->$key)->update((array) $row);
                }
            }
        });
    }
}
