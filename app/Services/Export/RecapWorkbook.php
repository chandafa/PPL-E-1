<?php

namespace App\Services\Export;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Berkas Excel rekap: tiga lembar (Ringkasan, Faktur, Pembayaran) dari RecapReport
 * yang sama dengan PDF. Nominal ditulis sebagai teks decimal tepat dua angka desimal agar
 * seluruh presisi decimal(18,2) dan ID berawalan nol tetap utuh di Excel.
 */
class RecapWorkbook implements Export, WithMultipleSheets
{
    public function __construct(private readonly RecapReport $report) {}

    public function sheets(): array
    {
        $r = $this->report;

        return [
            new class($r) extends StringValueBinder implements WithCustomValueBinder, FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
            {
                public function __construct(private readonly RecapReport $r) {}

                public function title(): string
                {
                    return 'Ringkasan';
                }

                public function headings(): array
                {
                    return ['Keterangan', 'Nilai'];
                }

                public function array(): array
                {
                    return $this->r->summaryRows();
                }

                public function columnFormats(): array
                {
                    return ['B' => NumberFormat::FORMAT_TEXT];
                }

                public function styles(Worksheet $sheet): array
                {
                    return [1 => ['font' => ['bold' => true]]];
                }
            },
            new class($r) extends StringValueBinder implements WithCustomValueBinder, FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
            {
                public function __construct(private readonly RecapReport $r) {}

                public function title(): string
                {
                    return 'Faktur';
                }

                public function headings(): array
                {
                    return ['NSFP', 'Tanggal', 'Invoice', 'Proyek', 'Klien', 'Status', 'DPP', 'PPN', 'PPnBM', 'Dihitung'];
                }

                public function array(): array
                {
                    return array_map(fn (array $i) => [
                        $i['taxInvoiceNumber'], $i['taxInvoiceDate'], $i['invoiceId'], $i['projectId'], $i['clientId'],
                        $i['status'], $i['dpp'], $i['ppn'], $i['ppnbm'], $i['isCounted'],
                    ], $this->r->invoices);
                }

                public function columnFormats(): array
                {
                    $f = NumberFormat::FORMAT_TEXT;

                    return ['G' => $f, 'H' => $f, 'I' => $f];
                }

                public function styles(Worksheet $sheet): array
                {
                    return [1 => ['font' => ['bold' => true]]];
                }
            },
            new class($r) extends StringValueBinder implements WithCustomValueBinder, FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
            {
                public function __construct(private readonly RecapReport $r) {}

                public function title(): string
                {
                    return 'Pembayaran';
                }

                public function headings(): array
                {
                    return ['ID Billing', 'NTPN', 'Jumlah setor', 'Status', 'Dicatat'];
                }

                public function array(): array
                {
                    return array_map(fn (array $p) => [
                        $p['billingId'], $p['ntpn'], $p['amountPaid'], $p['status'], $p['createdAt'],
                    ], $this->r->payments);
                }

                public function columnFormats(): array
                {
                    return ['C' => NumberFormat::FORMAT_TEXT];
                }

                public function styles(Worksheet $sheet): array
                {
                    return [1 => ['font' => ['bold' => true]]];
                }
            },
        ];
    }
}
