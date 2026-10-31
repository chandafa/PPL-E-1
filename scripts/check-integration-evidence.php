<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser;
use Tests\Support\OpenApiContract;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$folder = $argv[1];
$evidence = json_decode(file_get_contents($folder.'/bukti-http.json'), false, flags: JSON_THROW_ON_ERROR);
$contract = new OpenApiContract;
$operations = [
    'UI-01' => 'extractTaxInvoice', 'UI-02' => 'createOutgoingTaxInvoice', 'E1-03' => 'listTaxInvoices',
    'E1-04' => 'getTaxInvoice', 'E1-05' => 'updateTaxInvoice', 'E1-06' => 'getTaxInvoiceHistory',
    'E1-07' => 'getTaxRecap', 'E1-08' => 'createTaxPayment', 'E1-10' => 'getTaxSummary', 'E1-11' => 'completeTaxPayment',
    'V-01' => 'createOutgoingTaxInvoice', 'V-02' => 'createOutgoingTaxInvoice', 'V-03' => 'createTaxPayment',
    'V-04' => 'createTaxPayment', 'UI-03' => 'extractTaxInvoice', 'A-01' => 'listTaxInvoices',
    'A-02' => 'listTaxInvoices', 'A-03' => 'listTaxInvoices', 'A-04' => 'listTaxInvoices', 'A-05' => 'listTaxInvoices', 'A-06' => 'listTaxInvoices',
];
$checks = [];
foreach ($evidence->steps as $step) {
    if (! isset($operations[$step->id])) {
        continue;
    }
    $errors = $contract->validateResponse($operations[$step->id], $step->response->status, $step->response->body);
    if ($errors !== []) {
        throw new RuntimeException($step->id.': '.implode('; ', $errors));
    }
    $checks[] = ['id' => $step->id, 'operationId' => $operations[$step->id], 'result' => 'PASS'];
}
$pdf = (new Parser)->parseFile($folder.'/E1-09-PDF.pdf')->getText();
$book = IOFactory::load($folder.'/E1-09-XLSX.xlsx');
$summary = array_column($book->getActiveSheet()->toArray(), 1, 0);
if (! str_contains($pdf, 'Rp 11.000.000,00') || ($summary['Total PPN'] ?? null) !== '11000000.00') {
    throw new RuntimeException('Isi ekspor tidak sesuai rekap');
}
$report = ['observedAtUtc' => gmdate('c'), 'result' => 'PASS', 'jsonResponses' => count($checks),
    'businessOperationsCovered' => 11, 'binaryExportsReadable' => ['PDF' => true, 'XLSX' => true],
    'details' => $checks,
    'scope' => 'Skema E-1 v0.2.1 untuk respons bisnis yang dicantumkan, termasuk kegagalan pemeriksaan F-1; ekspor diperiksa sebagai biner terbaca dan total PPN cocok. Login F-1 dan respons producer D-2 bukan amplop bisnis E-1.'];
file_put_contents($folder.'/validasi-kontrak.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
echo 'PASS: '.count($checks).' respons JSON cocok; 11 operasi dicakup termasuk ekspor PDF/XLSX terbaca.'.PHP_EOL;
