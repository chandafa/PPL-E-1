<?php

namespace Tests\Support;

use Dompdf\Dompdf;

/** PDF dengan nominal teks mentah untuk menguji kesalahan dokumen masukan. */
final class TaxPdf
{
    public static function make(array $amounts = []): string
    {
        $amounts += ['dpp' => '100.000.000,00', 'ppn' => '12.000.000,00', 'ppnbm' => '75.000.000,00'];
        $html = '<p>Kode dan Nomor Seri Faktur Pajak : 010.000-26.98765432</p>'
            .'<p>1 A-1 Yacht rekreasi privat produksi sendiri (simulasi)</p><p>100.000.000,00</p>';
        foreach (['dpp' => 'Dasar Pengenaan Pajak', 'ppn' => 'Jumlah PPN', 'ppnbm' => 'Jumlah PPnBM'] as $field => $label) {
            if ($amounts[$field] !== null) {
                $html .= '<p>'.$label.' : '.htmlspecialchars($amounts[$field]).'</p>';
            }
        }
        $pdf = new Dompdf;
        $pdf->loadHtml($html.'<p>JAKARTA, 1 November 2026</p>');
        $pdf->render();

        return $pdf->output();
    }
}
