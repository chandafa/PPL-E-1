<?php

declare(strict_types=1);
use Carbon\CarbonImmutable;
use Database\Seeders\Support\DemoDataset;
use Database\Seeders\Support\FakeEFakturPdf;
use Illuminate\Contracts\Console\Kernel;
use Smalot\PdfParser\Parser;

// Input adalah JSON respons detail invoice D-2 yang sudah diamati melalui HTTP.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$input = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$row = $input['data'] ?? $input;
$date = CarbonImmutable::createFromFormat('!Y-m-d', $row['invoiceDate']);
$directory = $argv[2];
if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}
$options = [
    'date' => $date->locale('id')->translatedFormat('j F Y'),
    'invoiceNumber' => $row['invoiceNumber'], 'nsfp' => '010.000-'.$date->format('y').'.66000101',
    'dpp' => $row['dppAmount'], 'ppn' => $row['ppnAmount'], 'ppnbm' => '0.00',
    'items' => [['code' => '620100', 'name' => 'Jasa implementasi ERP proyek', 'amount' => $row['dppAmount']]],
];
$payment = ['taxPeriod' => $date->format('Y-m'), 'taxYear' => (int) $date->format('Y'),
    'amountPaid' => '5500000.00', 'billingId' => 'BILL-INTEGRASI-001', 'ntpn' => 'NTPN-INTEGRASI-001'];
$documents = [
    'efaktur.pdf' => FakeEFakturPdf::make($options),
    'efaktur-pengganti.pdf' => FakeEFakturPdf::make(array_merge($options, ['nsfp' => '010.001-'.$date->format('y').'.66000102'])),
    'e-billing.pdf' => FakeEFakturPdf::attachment('e-Billing Fiktif Integrasi', DemoDataset::paymentDocumentFields($payment)),
    'bukti-pembayaran.pdf' => FakeEFakturPdf::attachment('Bukti Pembayaran Fiktif Integrasi', DemoDataset::paymentDocumentFields($payment, proof: true)),
];
$manifest = [];
foreach ($documents as $file => $binary) {
    $path = $directory.'/'.$file;
    if (is_file($path)) {
        throw new RuntimeException('Tidak menimpa PDF yang sudah ada: '.$file);
    }
    $document = (new Parser)->parseContent($binary);
    if ($document->getPages() === [] || trim($document->getText()) === '') {
        throw new RuntimeException('PDF harus dapat diperiksa dan dibaca');
    }
    file_put_contents($path, $binary);
    $manifest[] = ['file' => $file, 'invoiceId' => $row['invoiceId'], 'invoiceDate' => $row['invoiceDate'],
        'taxPeriod' => $date->format('Y-m'), 'sha256' => hash('sha256', $binary), 'textReadable' => true];
}
file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
echo 'Empat PDF fiktif terbaca dibuat dari invoice HTTP D-2; tanggal dan masa pajak mengikuti invoice.'.PHP_EOL;
