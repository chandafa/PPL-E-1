<?php

namespace Database\Seeders\Support;

use App\Services\EFaktur\EFakturPdfParser;
use App\Support\Money;
use RuntimeException;

/** Membaca kembali byte PDF yang dihasilkan, memakai parser yang dipakai tombol Periksa PDF. */
final class DemoSampleValidator
{
    public function __construct(private readonly EFakturPdfParser $parser) {}

    public function validate(string $binary, array $fixture): array
    {
        $parsed = $this->parser->parse($binary);
        $expected = [
            'taxInvoiceNumber' => $fixture['pdf']['nsfp'],
            'taxInvoiceDate' => $fixture['invoiceDate'],
            'taxPeriod' => $fixture['taxPeriod'],
            'dpp' => Money::normalize($fixture['pdf']['dpp']),
            'ppn' => Money::normalize($fixture['pdf']['ppn']),
            'ppnbm' => Money::normalize($fixture['pdf']['ppnbm'] ?? '0.00'),
        ];
        foreach ($expected as $key => $value) {
            if ($parsed->{$key} !== $value) {
                throw new RuntimeException("PDF {$fixture['file']}: {$key} hasil ekstraksi berbeda dari katalog invoice.");
            }
        }
        if (preg_match('/^[0-9]{3}\.[0-9]{3}-([0-9]{2})\.[0-9]{8}$/D', $parsed->taxInvoiceNumber, $matches) !== 1
            || $matches[1] !== substr($parsed->taxInvoiceDate, 2, 2)) {
            throw new RuntimeException("Tahun NSFP {$fixture['file']} tidak sesuai tanggal e-Faktur.");
        }
        $items = $fixture['pdf']['items'];
        if (count($parsed->items) !== count($items)) {
            throw new RuntimeException("Jumlah item {$fixture['file']} tidak terbaca lengkap.");
        }
        foreach ($items as $index => $item) {
            $expectedItem = [
                'itemCode' => $item['code'],
                'itemName' => $item['name'],
                'saleReplacementAdvanceTermAmount' => Money::normalize($item['amount']),
                'discountAmount' => Money::normalize($item['discount'] ?? '0.00'),
                'receivedAdvanceAmount' => $index === 0 ? Money::normalize($fixture['pdf']['advance'] ?? '0.00') : '0.00',
            ];
            if ($parsed->items[$index] !== $expectedItem) {
                throw new RuntimeException('Item '.($index + 1)." pada {$fixture['file']} tidak terbaca utuh.");
            }
        }

        return $expected + ['itemCount' => count($parsed->items), 'sha256' => hash('sha256', $binary), 'validation' => 'PASSED'];
    }
}
