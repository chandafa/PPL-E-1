<?php

namespace Database\Seeders\Support;

use App\Models\Invoice;
use App\Support\Money;
use Carbon\CarbonImmutable;
use RuntimeException;

/** Builds E-1 demo documents from the current D-2 invoice records. */
final class DemoDataset
{
    public static function invoices(bool $includeManual = false): array
    {
        return Invoice::query()
            ->with('termin.contract')
            ->orderBy('invoice_id')
            ->get()
            ->mapWithKeys(function (Invoice $invoice): array {
                $date = CarbonImmutable::parse($invoice->invoice_date);
                $id = $invoice->invoice_id;
                $number = $invoice->invoice_number;
                $serial = 10000000 + (hexdec(substr(hash('sha256', $id), 0, 7)) % 89999999);
                $dpp = (string) $invoice->dpp_amount;
                $ppn = (string) $invoice->ppn_amount;
                $fixture = [
                    'invoiceId' => $id,
                    'invoiceNumber' => $number,
                    'invoiceDate' => $date->format('Y-m-d'),
                    'taxPeriod' => $date->format('Y-m'),
                    'manualUploadOnly' => false,
                    'file' => 'efaktur-' . $id . '.pdf',
                    'pdf' => [
                        'nsfp' => sprintf('010.000-%s.%08d', $date->format('y'), $serial),
                        'date' => $date->locale('id')->translatedFormat('j F Y'),
                        'invoiceNumber' => $number,
                        'items' => [[
                            'code' => '620100',
                            'name' => 'Jasa demo ' . $number,
                            'amount' => $dpp,
                        ]],
                        'dpp' => $dpp,
                        'ppn' => $ppn,
                        'ppnbm' => '0.00',
                        'buyer' => $invoice->termin?->contract?->project_id ?? 'Referensi D-2',
                    ],
                ];

                return [$id => $fixture];
            })
            ->all();
    }

    public static function seededInvoices(): array
    {
        return self::invoices();
    }

    public static function originalReplacement(): array
    {
        $invoices = self::invoices();
        $fixture = reset($invoices);
        if (! is_array($fixture)) {
            throw new RuntimeException('Belum ada invoice D-2 untuk membuat PDF demo.');
        }

        return self::replacementFor($fixture);
    }

    private static function replacementFor(array $fixture): array
    {
        $fixture['file'] = 'efaktur-' . $fixture['invoiceId'] . '-pengganti.pdf';
        $fixture['pdf']['nsfp'] = sprintf(
            '010.001-%s.%08d',
            substr($fixture['invoiceDate'], 2, 2),
            (int) substr($fixture['pdf']['nsfp'], -8) + 1,
        );

        return $fixture;
    }

    public static function samples(): array
    {
        $samples = [];
        foreach (self::invoices() as $id => $fixture) {
            $samples[$fixture['file']] = $fixture + ['seeded' => true, 'flow' => 'create'];
            $replacement = self::replacementFor($fixture);
            $samples[$replacement['file']] = $replacement + ['seeded' => false, 'flow' => 'replace'];
        }

        return $samples;
    }

    public static function payments(): array
    {
        return [
            ['taxPeriod' => '2026-11', 'taxYear' => 2026, 'amountPaid' => '46200000.00', 'billingId' => 'BILL-202611-001'],
            ['taxPeriod' => '2026-10', 'taxYear' => 2026, 'amountPaid' => '1000000.00', 'billingId' => 'BILL-DEMO-202610-001', 'ntpn' => 'NTPN-DEMO-202610-001'],
            ['taxPeriod' => '2026-10', 'taxYear' => 2026, 'amountPaid' => '4940000.00', 'billingId' => 'BILL-DEMO-202610-002', 'ntpn' => 'NTPN-DEMO-202610-002'],
            ['taxPeriod' => '2027-01', 'taxYear' => 2027, 'amountPaid' => '1000000.00', 'billingId' => 'BILL-DEMO-202701-001', 'ntpn' => 'NTPN-DEMO-202701-001'],
            ['taxPeriod' => '2027-02', 'taxYear' => 2027, 'amountPaid' => '1100000.00', 'billingId' => 'BILL-DEMO-202702-001', 'ntpn' => 'NTPN-DEMO-202702-001'],
        ];
    }

    /** Lampiran sesuai Q-09: pembayaran contoh pada tanggal 10 bulan berikutnya. */
    public static function paymentDocumentFields(array $payment, bool $proof = false): array
    {
        $date = CarbonImmutable::createFromFormat('!Y-m', $payment['taxPeriod'])->addMonth()->day(10);

        $fields = [
            'Masa pajak' => $payment['taxPeriod'],
            'Tahun pajak' => (string) $payment['taxYear'],
            'ID Billing' => $payment['billingId'],
            'Jumlah setor' => 'Rp ' . Money::display($payment['amountPaid']),
        ];
        if ($proof) {
            $fields['NTPN'] = $payment['ntpn'];
            $fields['Tanggal pembayaran'] = $date->locale('id')->translatedFormat('j F Y');
        } else {
            $fields['Tanggal e-Billing'] = $date->subDay()->locale('id')->translatedFormat('j F Y');
        }

        return $fields;
    }

    public static function paymentDocuments(): array
    {
        $documents = [];
        foreach (self::payments() as $sequence => $payment) {
            foreach (['e-billing' => 'e-Billing Demo', 'bukti-pembayaran' => 'Bukti Pembayaran Demo'] as $prefix => $title) {
                $fields = self::paymentDocumentFields($payment, proof: $prefix === 'bukti-pembayaran');
                $name = sprintf('%s-%s-%03d.pdf', $prefix, $payment['taxPeriod'], $sequence + 1);
                $documents[$name] = compact('title', 'fields');
            }
        }

        return $documents;
    }
}
