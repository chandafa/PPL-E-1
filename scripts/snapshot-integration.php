<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.integration');
$app->make(Kernel::class)->bootstrap();
if (! str_starts_with((string) config('database.connections.pgsql.database'), 'e1_integrasi_')) {
    throw new RuntimeException('Snapshot ini hanya untuk DB integrasi terpisah');
}
$tables = [];
foreach (['faktur_pajak', 'item_faktur', 'riwayat_perubahan_faktur', 'pembayaran_pajak', 'riwayat_perubahan_pembayaran', 'rekap_pajak_bulanan'] as $table) {
    $rows = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
    usort($rows, fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
    $tables[$table] = ['count' => count($rows), 'sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR))];
}
$files = [];
foreach (Storage::disk('local')->allFiles('tax') as $file) {
    $files[$file] = hash('sha256', Storage::disk('local')->get($file));
}
ksort($files);
echo json_encode(['tables' => $tables, 'files' => $files], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
