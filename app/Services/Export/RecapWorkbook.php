<?php

namespace App\Services\Export;

use App\Support\DateDisplay;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Nominal dan ID tetap teks untuk menjaga presisi desimal dan nol di awal. */
class RecapWorkbook extends StringValueBinder implements Export, FromArray, WithColumnWidths, WithCustomValueBinder, WithStyles, WithTitle
{
    public function __construct(private readonly RecapReport $report) {}

    public function title(): string
    {
        return 'Rekap Pajak';
    }

    public function array(): array
    {
        return [
            ['Rekap Pajak Masa '.DateDisplay::period($this->report->taxPeriod)],
            ['PT Anaheim Nimbus Universal · Modul E-1 Pencatatan e-Faktur dan pajak proyek'],
            [null],
            ['Ringkasan'],
            ...$this->report->summaryRows(),
            [null],
            ['Daftar Faktur'],
            ['NSFP', 'Tanggal', 'Invoice', 'Proyek', 'Klien', 'Status', 'DPP', 'PPN', 'PPnBM', 'Dihitung'],
            ...($this->report->invoices ? array_map(fn (array $i) => [
                $i['taxInvoiceNumber'], $i['taxInvoiceDate'], $i['invoiceId'], $i['projectId'], $i['clientId'],
                $i['status'], $i['dpp'], $i['ppn'], $i['ppnbm'], $i['isCounted'],
            ], $this->report->invoices) : [['Belum ada faktur pada masa pajak ini.']]),
            [null],
            ['Pembayaran'],
            ['ID Billing', 'NTPN', 'Jumlah setor', 'Status', 'Dicatat'],
            ...($this->report->payments ? array_map(fn (array $p) => [
                $p['billingId'], $p['ntpn'] !== '' ? $p['ntpn'] : '-', $p['amountPaid'], $p['status'], $p['createdAt'],
            ], $this->report->payments) : [['Belum ada pembayaran dicatat.']]),
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 34, 'B' => 24, 'C' => 26, 'D' => 25, 'E' => 25, 'F' => 23, 'G' => 24, 'H' => 24, 'I' => 24, 'J' => 12];
    }

    public function styles(Worksheet $sheet): array
    {
        $invoiceEnd = 16 + max(1, count($this->report->invoices));
        $paymentTitle = $invoiceEnd + 2;
        $paymentHeader = $paymentTitle + 1;
        $paymentStart = $paymentHeader + 1;
        $lastRow = $paymentHeader + max(1, count($this->report->payments));

        $sheet->getStyle("A1:J{$lastRow}")->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 10],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            'numberFormat' => ['formatCode' => NumberFormat::FORMAT_TEXT],
        ]);
        foreach ([1, 2, 4, 15, $paymentTitle] as $row) {
            $sheet->mergeCells("A{$row}:J{$row}");
        }
        for ($row = 5; $row <= 13; $row++) {
            $sheet->mergeCells("B{$row}:J{$row}");
        }
        $sheet->getStyle('B5:J13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension(1)->setRowHeight(25);
        $sheet->getRowDimension(2)->setRowHeight(20);
        for ($row = 5; $row <= $lastRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(30);
        }
        $sheet->getStyle("G17:I{$invoiceEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("C{$paymentStart}:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        foreach (['A5:J13', "A16:J{$invoiceEnd}", "A{$paymentHeader}:E{$lastRow}"] as $range) {
            $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('999999');
        }
        foreach ([4, 15, 16, $paymentTitle, $paymentHeader] as $row) {
            $end = $row === $paymentHeader ? 'E' : 'J';
            $sheet->getStyle("A{$row}:{$end}{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEEEEE']],
            ]);
        }
        if (! $this->report->invoices) {
            $sheet->mergeCells('A17:J17');
        }
        if (! $this->report->payments) {
            $sheet->mergeCells("A{$paymentStart}:E{$paymentStart}");
        }
        $sheet->freezePane('A17');
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0)
            ->setPrintArea("A1:J{$lastRow}");

        return [1 => ['font' => ['bold' => true, 'size' => 16]], 2 => ['font' => ['color' => ['rgb' => '555555']]]];
    }
}
