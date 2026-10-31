<?php

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Models\FakturPajak;
use App\Services\Auth\F1Identity;
use App\Services\Tax\RecapService;
use App\Services\Tax\TaxInvoiceService;
use App\Services\Tax\TaxPaymentService;
use Database\Seeders\ModulD2Seeder;
use Database\Seeders\Support\FakeEFakturPdf;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

$repo = dirname(__DIR__);
require $repo.'/vendor/autoload.php';
$app = require $repo.'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.integration');
$app->make(Kernel::class)->bootstrap();
$runtime = json_decode(file_get_contents($repo.'/storage/app/private/integration/current.json'), true, flags: JSON_THROW_ON_ERROR);
$database = $runtime['tests']['database'];
if (! str_starts_with($database, 'e1_integrasi_') || ! str_ends_with($database, '_tests')) {
    throw new RuntimeException('Hanya database tests yang dibuat prepare-integration.php');
}
config(['database.connections.pgsql.database' => $database, 'e1.d2.driver' => 'mock',
    'filesystems.disks.local.root' => $runtime['tests']['storage'].'/app/private']);
DB::purge('pgsql');
$folder = $runtime['directory'].'/concurrency';
if (! is_dir($folder)) {
    mkdir($folder, 0777, true);
}
$period = '2026-10';
$mode = $argv[1] ?? '';
$recap = app(RecapService::class);
if ($mode === 'worker-refresh') {
    DB::connection()->beforeExecuting(function (string $query) use ($folder): void {
        if (str_contains($query, 'sum(ppn)')) {
            file_put_contents($folder.'/computed', '1');
        }
    });
    file_put_contents($folder.'/started', '1');
    $row = $recap->refresh($period);
    echo json_encode(['totalPpn' => $row->total_ppn], JSON_THROW_ON_ERROR);
    exit;
}
if ($mode === 'worker-payment') {
    try {
        $input = ['taxPeriod' => $period, 'taxYear' => 2026, 'amountPaid' => '6000000.00', 'billingId' => $argv[2]];
        app(TaxPaymentService::class)->create($input, new UploadedFile($folder.'/billing.pdf', 'billing.pdf', 'application/pdf', UPLOAD_ERR_OK, true), null, new F1Identity('fixture-concurrency', 'Admin'));
        echo json_encode(['status' => 200]);
    } catch (ApiException $e) {
        echo json_encode(['status' => $e->status, 'errorCode' => $e->errorCode]);
    }
    exit;
}
if (Schema::hasTable('faktur_pajak') && FakturPajak::query()->exists()) {
    throw new RuntimeException('Database konkurensi harus baru/kosong; siapkan runtime baru, jangan reset data.');
}
$app->make(Kernel::class)->call('migrate', ['--force' => true]);
(new ModulD2Seeder)->run();
file_put_contents($folder.'/invoice.pdf', FakeEFakturPdf::make(['date' => '11 Oktober 2026']));
file_put_contents($folder.'/billing.pdf', FakeEFakturPdf::unrelated());
$faktur = app(TaxInvoiceService::class)->createOutgoing('inv-demo-sample', new UploadedFile($folder.'/invoice.pdf', 'invoice.pdf', 'application/pdf', UPLOAD_ERR_OK, true), null, new F1Identity('fixture-concurrency', 'Admin'));
$worker = null;
try {
    DB::beginTransaction();
    $recap->lockPeriods([$period]);
    $worker = new Process([PHP_BINARY, __FILE__, 'worker-refresh'], $repo, timeout: 20);
    $worker->start();
    $deadline = microtime(true) + 8;
    while (! is_file($folder.'/started')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Worker tidak siap');
        }
        usleep(10000);
        clearstatcache();
    }
    usleep(250000);
    clearstatcache();
    if (is_file($folder.'/computed')) {
        throw new RuntimeException('Worker membaca snapshot sebelum kunci periode tersedia');
    }
    FakturPajak::query()->whereKey($faktur->tax_invoice_id)->update(['ppn' => '9900000.00']);
    $recap->refresh($period);
    DB::commit();
    $worker->wait();
    if (! $worker->isSuccessful()) {
        throw new RuntimeException('Worker refresh gagal');
    }
    $result = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    if ($result['totalPpn'] !== '9900000.00' || DB::table('rekap_pajak_bulanan')->where('tax_period', $period)->value('total_ppn') !== '9900000.00') {
        throw new RuntimeException('Snapshot lama menimpa rekap terbaru');
    }
    $a = new Process([PHP_BINARY, __FILE__, 'worker-payment', 'BILL-RACE-A'], $repo, timeout: 20);
    $b = new Process([PHP_BINARY, __FILE__, 'worker-payment', 'BILL-RACE-B'], $repo, timeout: 20);
    $a->start();
    $b->start();
    $a->wait();
    $b->wait();
    if (! $a->isSuccessful() || ! $b->isSuccessful()) {
        throw new RuntimeException('Worker pembayaran gagal');
    }
    $results = [json_decode($a->getOutput(), true, flags: JSON_THROW_ON_ERROR), json_decode($b->getOutput(), true, flags: JSON_THROW_ON_ERROR)];
    $statuses = array_column($results, 'status');
    sort($statuses);
    if ($statuses !== [200, 400] || DB::table('pembayaran_pajak')->count() !== 1 || DB::table('riwayat_perubahan_pembayaran')->count() !== 1) {
        throw new RuntimeException('Dua pembayaran melampaui batas atau audit tidak konsisten');
    }
    $proof = ['observedAtUtc' => gmdate('c'), 'result' => 'PASS', 'database' => $database,
        'staleRefresh' => ['workerBlockedBeforeCompute' => true, 'latestTotalPpn' => '9900000.00'],
        'simultaneousPayments' => ['requestedPerPayment' => '6000000.00', 'responses' => $results, 'storedPayments' => 1, 'auditRows' => 1],
        'scope' => 'Dua proses PHP nyata dengan koneksi PostgreSQL terpisah pada DB uji baru; bukan stress test beban produksi'];
    file_put_contents($argv[1], json_encode($proof, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    echo 'PASS: refresh menunggu kunci sebelum compute; pembayaran beririsan menghasilkan 200 dan 400 tanpa lebih setor.'.PHP_EOL;
} finally {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    if ($worker?->isRunning()) {
        $worker->stop();
    }
}
