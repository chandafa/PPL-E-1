<?php

namespace App\Services\EFaktur;

use App\Exceptions\ApiException;
use App\Support\Money;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Mengekstrak data dari PDF e-Faktur (Faktur Pajak Keluaran).
 *
 * Parser berbasis label, bukan koordinat, agar tahan terhadap variasi tata
 * letak: label dan nominal boleh dipisah tab, spasi, atau baris baru, dan angka
 * boleh berformat Indonesia (100.000.000,00) atau internasional.
 *
 * CATATAN: contoh dokumen asli dari mitra belum diterima (Q-17). Label yang
 * dipakai mengikuti Faktur Pajak e-Faktur: "Kode dan Nomor Seri Faktur Pajak",
 * "Harga Jual / Penggantian / Uang Muka / Termin", "Dikurangi Potongan Harga",
 * "Dikurangi Uang Muka yang telah diterima", "Dasar Pengenaan Pajak",
 * "Jumlah PPN", "Jumlah PPnBM". Sesuaikan setelah contoh asli tersedia.
 */
class EFakturPdfParser
{
    private const AMOUNT = '(?:Rp\.?\s*)?(-?[0-9][0-9.,]*)';

    private const MONTHS = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6,
        'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
        'january' => 1, 'february' => 2, 'march' => 3, 'may' => 5, 'june' => 6, 'july' => 7,
        'august' => 8, 'october' => 10, 'december' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6, 'jul' => 7, 'agu' => 8, 'agt' => 8,
        'aug' => 8, 'sep' => 9, 'sept' => 9, 'okt' => 10, 'oct' => 10, 'nov' => 11, 'des' => 12, 'dec' => 12,
    ];

    /**
     * @throws ApiException 400 EXTRACTION_FAILED bila PDF tidak dapat diproses
     */
    public function parse(string $pdfBinary): ExtractedTaxInvoice
    {
        $lines = $this->lines($pdfBinary);
        $flat = implode(' ', $lines);

        $nsfp = $this->nsfp($lines, $flat);
        $date = $this->date($flat);
        $dpp = $this->amountAfter($flat, '(?:Dasar\s+Pengenaan\s+Pajak|DPP)');
        $ppn = $this->amountAfter($flat, '(?:Jumlah|Total)\s+PPN(?!\s*BM)(?:\s*\([^)]*\))?');
        $ppnbm = $this->amountAfter($flat, '(?:Jumlah|Total)\s+PPn?BM(?:\s*\([^)]*\))?');
        $discount = $this->amountAfter($flat, 'Dikurangi\s+Potongan\s+Harga');
        $advance = $this->amountAfter($flat, 'Dikurangi\s+Uang\s+Muka(?:\s+yang\s+telah\s+diterima)?');

        $items = $this->items($lines, $discount ?? '0.00', $advance ?? '0.00');

        if ($nsfp === null || $date === null || $dpp === null || $ppn === null || $items === []) {
            throw $this->failed();
        }

        return new ExtractedTaxInvoice(
            taxInvoiceNumber: $nsfp,
            taxInvoiceDate: $date,
            taxPeriod: substr($date, 0, 7),
            dpp: $dpp,
            ppn: $ppn,
            ppnbm: $ppnbm ?? '0.00',
            items: $items,
        );
    }

    /** @return list<string> baris teks bersih dan tidak kosong */
    private function lines(string $pdfBinary): array
    {
        try {
            $text = (new Parser)->parseContent($pdfBinary)->getText();
        } catch (Throwable) {
            throw $this->failed();
        }

        $text = str_replace(["\u{00A0}", "\r"], [' ', "\n"], $text);
        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim((string) preg_replace('/[ \t]+/u', ' ', $line));
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        if ($lines === []) {
            throw $this->failed();
        }

        return $lines;
    }

    private function nsfp(array $lines, string $flat): ?string
    {
        if (preg_match('/Kode\s+dan\s+Nomor\s+Seri\s+Faktur\s+Pajak\s*:?\s*([0-9][0-9.\-]{11,30})/iu', $flat, $m) === 1) {
            return rtrim($m[1], '.-');
        }

        if (preg_match('/\b[0-9]{3}[.\-][0-9]{3}[.\-][0-9]{2}[.\-][0-9]{8}\b/u', $flat, $m) === 1) {
            return $m[0];
        }

        return null;
    }

    /** Tanggal faktur (baris tanda tangan "Kota, 1 November 2026"); yang terakhir dipakai. */
    private function date(string $flat): ?string
    {
        $months = implode('|', array_keys(self::MONTHS));

        if (preg_match_all('/\b([0-9]{1,2})\s+('.$months.')\.?\s+([0-9]{4})\b/iu', $flat, $all, PREG_SET_ORDER) > 0) {
            $m = end($all);
            $month = self::MONTHS[mb_strtolower($m[2])];

            return $this->validDate((int) $m[3], $month, (int) $m[1]);
        }

        if (preg_match_all('/\b([0-9]{4})-([0-9]{2})-([0-9]{2})\b/u', $flat, $all, PREG_SET_ORDER) > 0) {
            $m = end($all);

            return $this->validDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (preg_match_all('/\b([0-9]{2})[\/-]([0-9]{2})[\/-]([0-9]{4})\b/u', $flat, $all, PREG_SET_ORDER) > 0) {
            $m = end($all);

            return $this->validDate((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    private function validDate(int $year, int $month, int $day): ?string
    {
        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }

    /** Label boleh tidak ada; label yang ada tetapi nominalnya rusak wajib ditolak. */
    private function amountAfter(string $flat, string $labelPattern): ?string
    {
        if (preg_match('/'.$labelPattern.'/iu', $flat, $label, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $after = substr($flat, $label[0][1] + strlen($label[0][0]));
        if (preg_match('/^\s*[:=*)\s]*(?:Rp\.?\s*)?([^\s]+)/iu', $after, $m) !== 1) {
            throw $this->failed();
        }

        return $this->amount($m[1]) ?? throw $this->failed();
    }

    /**
     * Item barang/jasa: baris diawali "No Kode Nama", lalu sub-baris
     * ("Rp X x Q", "Potongan Harga = Rp X", "PPnBM (..) = Rp X") dan nominal kolom Harga Jual.
     *
     * Potongan harga dan uang muka tingkat faktur diisikan pada item pertama bila
     * item tidak memuat potongan sendiri (03 menaruhnya di ItemFaktur).
     *
     * @return list<array{itemCode: string, itemName: string, saleReplacementAdvanceTermAmount: string, discountAmount: string, receivedAdvanceAmount: string}>
     */
    private function items(array $lines, string $docDiscount, string $docAdvance): array
    {
        $start = 0;
        foreach ($lines as $i => $line) {
            if (preg_match('/Termin\s*\(Rp\)/iu', $line) === 1) {
                $start = $i + 1;
                break;
            }
        }

        $items = [];
        $current = null;
        $amount = self::AMOUNT;

        $flush = function () use (&$items, &$current): void {
            if ($current !== null && $current['amount'] !== null && trim($current['name']) !== '') {
                $items[] = $current;
            }
            $current = null;
        };

        for ($i = $start; $i < count($lines); $i++) {
            $line = $lines[$i];

            // akhir tabel: baris total (Harga Jual / ... *) atau label total lain
            if (preg_match('/^Harga\s+Jual\s*\/.*(\*|Termin\s*$)/iu', $line) === 1
                || preg_match('/^(Dikurangi|Dasar\s+Pengenaan|Jumlah\s+PPN|Total\s+PPN)/iu', $line) === 1) {
                break;
            }

            if (preg_match('/^Potongan\s+Harga\s*=\s*'.$amount.'/iu', $line, $m) === 1) {
                if ($current !== null) {
                    $current['discount'] = $this->amount($m[1]) ?? throw $this->failed();
                }

                continue;
            }

            if (preg_match('/^(Rp\.?\s*[0-9][0-9.,]*\s*x\s|PPn?BM\b)/iu', $line) === 1) {
                continue;
            }

            if (preg_match('/^([0-9]{1,3})\s+([A-Za-z0-9][A-Za-z0-9.\-\/]{1,24})\s+(.+)$/u', $line, $m) === 1) {
                $flush();
                $name = $m[3];
                $rowAmount = null;
                if (preg_match('/^(.*?)\s+'.self::AMOUNT.'$/u', $name, $n) === 1 && $this->looksMoney($n[2])) {
                    $name = $n[1];
                    $rowAmount = $this->amount($n[2]) ?? throw $this->failed();
                }
                $current = ['code' => $m[2], 'name' => $name, 'amount' => $rowAmount, 'discount' => null];

                continue;
            }

            if ($current !== null) {
                if (preg_match('/^'.$amount.'$/u', $line, $m) === 1 && $this->looksMoney($m[1])) {
                    $current['amount'] = $this->amount($m[1]) ?? throw $this->failed();
                } else {
                    $current['name'] .= ' '.$line;
                }
            }
        }
        $flush();

        $result = [];
        foreach ($items as $index => $item) {
            $discount = $item['discount'] ?? ($index === 0 ? $docDiscount : '0.00');
            $result[] = [
                'itemCode' => $item['code'],
                'itemName' => trim($item['name']),
                'saleReplacementAdvanceTermAmount' => $item['amount'],
                'discountAmount' => $discount,
                'receivedAdvanceAmount' => $index === 0 ? $docAdvance : '0.00',
            ];
        }

        return $result;
    }

    /** Nominal berformat uang (ada pemisah desimal/ribuan atau minimal 3 digit), bukan nomor urut. */
    private function looksMoney(string $raw): bool
    {
        return preg_match('/[.,]/', $raw) === 1 || strlen($raw) >= 4;
    }

    /**
     * "100.000.000,00" -> "100000000.00"; "100,000,000.50" -> "100000000.50";
     * "1.500.000" -> "1500000.00".
     */
    private function amount(string $raw): ?string
    {
        $raw = trim($raw);
        if (preg_match('/^([0-9]+)(?:[.,]([0-9]{1,2}))?$/D', $raw, $parts) === 1) {
            $integer = $parts[1];
            $fraction = $parts[2] ?? '00';
        } elseif (preg_match('/^([0-9]{1,3}(?:\.[0-9]{3})+)(?:,([0-9]{1,2}))?$/D', $raw, $parts) === 1) {
            $integer = str_replace('.', '', $parts[1]);
            $fraction = $parts[2] ?? '00';
        } elseif (preg_match('/^([0-9]{1,3}(?:,[0-9]{3})+)(?:\.([0-9]{1,2}))?$/D', $raw, $parts) === 1) {
            $integer = str_replace(',', '', $parts[1]);
            $fraction = $parts[2] ?? '00';
        } else {
            return null;
        }

        $value = $integer.'.'.str_pad($fraction, 2, '0');

        return Money::isValid($value) ? Money::normalize($value) : null;
    }

    private function failed(): ApiException
    {
        return ApiException::badRequest('EXTRACTION_FAILED', 'PDF e-Faktur tidak dapat diproses');
    }
}
