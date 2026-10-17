<?php

require __DIR__.'/../vendor/autoload.php';

use Dompdf\Dompdf;
use Illuminate\Support\Str;

$root = dirname(__DIR__);
$source = $root.'/docs/acuan/ADENDUM_DEMO_E1_v0.3.md';
$target = $root.'/docs/acuan/ADENDUM_DEMO_E1_v0.3.pdf';
$content = Str::markdown(file_get_contents($source));
$css = <<<'CSS'
@page { margin: 40pt 42pt; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.4; color: #222; }
h1 { font-size: 18pt; line-height: 1.2; margin: 0 0 12pt; }
h2 { font-size: 12pt; margin-top: 18pt; page-break-after: avoid; }
p { margin: 0 0 8pt; }
li { margin-bottom: 5pt; }
table { width: 100%; border-collapse: collapse; font-size: 8pt; margin-bottom: 12pt; }
thead { display: table-header-group; }
th, td { border: 0.5pt solid #999; padding: 5pt; vertical-align: top; }
th { background: #eee; text-align: left; }
tr { page-break-inside: avoid; }
code { font-family: DejaVu Sans Mono, monospace; font-size: 8pt; overflow-wrap: break-word; }
pre { background: #f5f5f5; border: 0.5pt solid #ccc; padding: 8pt; white-space: pre-wrap; page-break-inside: avoid; }
CSS;
$pdf = new Dompdf;
$pdf->loadHtml('<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Adendum E-1 v0.3</title><style>'.$css.'</style></head><body>'.$content.'</body></html>', 'UTF-8');
$pdf->setPaper('a4', 'portrait');
$pdf->render();
$pdf->getCanvas()->page_text(42, 810, 'Adendum E-1 v0.3 | {PAGE_NUM}/{PAGE_COUNT}', null, 8, [0.35, 0.35, 0.35]);
file_put_contents($target, $pdf->output());
echo "docs/acuan/ADENDUM_DEMO_E1_v0.3.pdf diperbarui.\n";
