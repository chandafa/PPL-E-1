<?php

namespace App\Services\Export;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Berkas Excel rekap: tiga lembar (Ringkasan, Faktur, Pembayaran) dari RecapReport
 * yang sama dengan PDF. Nominal ditulis sebagai angka berformat #,##0.00.
 */
class RecapWorkbook implements Export, WithMultipleSheets
{
    public function __construct(private readonly RecapReport $report) {}

    public function sheets(): array
    {
        $r = $this->report;

        return [
            new class($r) implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
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
                    $money = ['Total DPP', 'Total PPN', 'Total PPnBM', 'Jumlah setor', 'Selisih (jumlah setor - total PPN)'];

                    return array_map(
                        fn (array $row) => [$row[0], in_array($row[0], $money, true) ? (float) $row[1] : $row[1]],
                        $this->r->summaryRows(),
                    );
                }

                public function columnFormats(): array
                {
                    return ['B' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1];
                }

                public function styles(Worksheet $sheet): array
                {
                    return [1 => ['font' => ['bold' => true]]];
                }
            },
            new class($r) implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
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
                        $i['status'], (float) $i['dpp'], (float) $i['ppn'], (float) $i['ppnbm'], $i['isCounted'],
                    ], $this->r->invoices);
                }

                public function columnFormats(): array
                {
                    $f = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;

                    return ['G' => $f, 'H' => $f, 'I' => $f];
                }

                public function styles(Worksheet $sheet): array
                {
                    return [1 => ['font' => ['bold' => true]]];
                }
            },
            new class($r) implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
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
                        $p['billingId'], $p['ntpn'], (float) $p['amountPaid'], $p['status'], $p['createdAt'],
                    ], $this->r->payments);
                }

                public function columnFormats(): array
                {
                    return ['C' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1];
                }

                public function styles(Worksheet $sheet): array
                {
                    return [1 => ['font' => ['bold' => true]]];
                }
            },
        ];
    }
}
