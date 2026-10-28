<?php

/** WAPT lokal: dua proses nyata, schema PostgreSQL sekali pakai, tanpa data produksi. */

use App\Exceptions\ApiException;
use App\Services\Auth\F1Identity;
use App\Services\Tax\RecapService;
use App\Services\Tax\TaxInvoiceService;
use App\Services\Tax\TaxPaymentService;
use Database\Seeders\Support\FakeEFakturPdf;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

require dirname(__DIR__).'/vendor/autoload.php';
$root = dirname(__DIR__);
foreach (simplexml_load_file($root.'/phpunit.xml')->php->env as $entry) {
    $name = (string) $entry['name'];
    $value = (string) $entry['value'];
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, 'Audit gagal: '.get_class($error).PHP_EOL);
    exit(1);
});
$base = DB::connection();
if (! $app->environment('testing') || ! str_ends_with($base->getDatabaseName(), '_test')
    || ! in_array($base->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Hanya diizinkan pada database lokal berakhiran _test.');
}
$worker = ($argv[1] ?? '') === 'worker';
$schema = $worker ? ($argv[2] ?? '') : 'audit_'.bin2hex(random_bytes(8));
$temp = $worker ? ($argv[3] ?? '') : sys_get_temp_dir().'/'.$schema;
if (! preg_match('/^audit_[a-f0-9]{16}$/D', $schema) || $temp !== sys_get_temp_dir().'/'.$schema) {
    throw new RuntimeException('Schema atau direktori uji tidak valid.');
}
config(['database.connections.audit' => array_replace($base->getConfig(), ['name' => 'audit', 'search_path' => $schema])]);
config(['filesystems.disks.local.root' => $temp.'/uploads']);
DB::setDefaultConnection('audit');
$db = DB::connection();

function awaitFile(string $path): void
{
    $deadline = microtime(true) + 10;
    while (! is_file($path)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Timeout sinkronisasi proses audit.');
        }
        usleep(10000);
        clearstatcache(true, $path);
    }
}

if ($worker) {
    if (($argv[4] ?? '') === 'payment') {
        // Request A telah melewati preflight; request B commit sebelum A mengambil lock.
        $db->beforeExecuting(function (string $query) use ($temp): void {
            if (str_contains($query, 'rekap_pajak_bulanan') && str_contains($query, 'for update')) {
                file_put_contents($temp.'/ready', '1');
                awaitFile($temp.'/release');
            }
        });
        try {
            $app->make(TaxPaymentService::class)->create([
                'taxPeriod' => '2026-11', 'taxYear' => 2026, 'amountPaid' => $argv[5], 'billingId' => 'q9z3k7m2w8',
            ], new UploadedFile($temp.'/invoice.pdf', 'billing.pdf', 'application/pdf', UPLOAD_ERR_OK, true), null, new F1Identity('AKN-0002', 'Admin'));
            echo json_encode(['status' => 200], JSON_THROW_ON_ERROR);
        } catch (ApiException $error) {
            echo json_encode(['status' => $error->status, 'errorCode' => $error->errorCode], JSON_THROW_ON_ERROR);
        }
        exit(0);
    }
    // Jeda tepat sebelum UPSERT, setelah compute membaca snapshot nyata dari DB.
    $db->beforeExecuting(function (string $query) use ($temp): void {
        if (str_contains($query, 'insert into "rekap_pajak_bulanan"')) {
            file_put_contents($temp.'/ready', '1');
            awaitFile($temp.'/release');
        }
    });
    $app->make(RecapService::class)->refresh('2026-11');
    exit(0);
}

$process = null;
try {
    File::ensureDirectoryExists($temp);
    $base->statement('CREATE SCHEMA '.$schema);
    foreach (['faktur_pajak', 'item_faktur', 'rekap_pajak_bulanan', 'pembayaran_pajak',
        'riwayat_perubahan_faktur', 'riwayat_perubahan_pembayaran'] as $table) {
        $base->statement("CREATE TABLE {$schema}.{$table} (LIKE public.{$table} INCLUDING ALL)");
    }
    // LIKE menyalin kolom/index/CHECK; FK dan trigger tidak dibutuhkan oleh race snapshot ini.
    $pdf = $temp.'/invoice.pdf';
    file_put_contents($pdf, FakeEFakturPdf::make());
    $service = $app->make(TaxInvoiceService::class);
    $actor = new F1Identity('AKN-0002', 'Admin');
    $invoice = $service->createOutgoing('inv-demo-001', new UploadedFile($pdf, 'invoice.pdf', 'application/pdf', UPLOAD_ERR_OK, true), null, $actor);
    $recap = $app->make(RecapService::class);
    $results = [];
    for ($round = 1; $round <= 5; $round++) {
        @unlink($temp.'/ready');
        @unlink($temp.'/release');
        $recap->refresh('2026-11');
        $before = $recap->compute('2026-11')->totalPpn;
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $schema, $temp], [
            0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
        ], $pipes, $root);
        if (! is_resource($process)) {
            throw new RuntimeException('Worker tidak dapat dimulai.');
        }
        awaitFile($temp.'/ready');
        $target = bcmul((string) ($round + 1), '11000000', 2);
        $service->update($invoice->tax_invoice_id, ['ppn' => $target], 'WAPT concurrency', $actor);
        file_put_contents($temp.'/release', '1');
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $process = null;
        if ($exit !== 0) {
            throw new RuntimeException('Worker gagal; exit '.$exit);
        }
        $stored = (string) $db->table('rekap_pajak_bulanan')->where('tax_period', '2026-11')->value('total_ppn');
        $live = $recap->compute('2026-11')->totalPpn;
        $results[] = ['round' => $round, 'snapshot_before' => $before, 'stored_after' => $stored,
            'live_after' => $live, 'stale_write_proven' => $stored === $before && $live === $target];
    }
    if (count(array_filter($results, fn ($r) => $r['stale_write_proven'])) !== 5) {
        throw new RuntimeException('Tidak semua ronde mereproduksi stale write.');
    }
    @unlink($temp.'/ready');
    @unlink($temp.'/release');
    $recap->refresh('2026-11');
    $amount = $recap->compute('2026-11')->totalPpn;
    $process = proc_open([PHP_BINARY, __FILE__, 'worker', $schema, $temp, 'payment', $amount], [
        0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes, $root);
    if (! is_resource($process)) {
        throw new RuntimeException('Worker pembayaran tidak dapat dimulai.');
    }
    awaitFile($temp.'/ready');
    $app->make(TaxPaymentService::class)->create([
        'taxPeriod' => '2026-11', 'taxYear' => 2026, 'amountPaid' => $amount, 'billingId' => 'h7n2v8m4r9',
    ], new UploadedFile($pdf, 'billing.pdf', 'application/pdf', UPLOAD_ERR_OK, true), null, $actor);
    file_put_contents($temp.'/release', '1');
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    $process = null;
    $loser = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
    $count = $db->table('pembayaran_pajak')->count();
    $total = $recap->compute('2026-11')->amountPaid;
    if ($exit !== 0 || ($loser['status'] ?? null) !== 400 || $count !== 1 || $total !== $amount) {
        throw new RuntimeException('Hasil guard pembayaran serentak tidak sesuai.');
    }
    echo json_encode(['database_profile' => 'local_test_disposable_schema', 'rounds' => $results,
        'parallel_payment_guard' => ['loser' => $loser, 'stored_payment_count' => $count, 'amount_paid' => $total,
            'guard_held' => true]], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    DB::disconnect('audit');
    $base->statement('DROP SCHEMA IF EXISTS '.$schema.' CASCADE');
    File::deleteDirectory($temp);
}
