<?php

namespace Database\Seeders\Support;

use App\Models\Invoice;
use App\Support\Money;
use Carbon\CarbonImmutable;
use RuntimeException;

/** Builds E-1 demo documents from the current D-2 invoice records. */
final class DemoDataset
{
    public static function invoices(?array $invoiceIds = null): array
    {
        return Invoice::query()
            ->with(['termin.contract', 'sourceItems'])
            ->when($invoiceIds !== null, fn ($query) => $query->whereIn('invoice_id', $invoiceIds))
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
                    'file' => 'efaktur-'.$id.'.pdf',
                    'pdf' => [
                        'nsfp' => sprintf('010.000-%s.%08d', $date->format('y'), $serial),
                        'date' => $date->locale('id')->translatedFormat('j F Y'),
                        'invoiceNumber' => $number,
                        'items' => $invoice->sourceItems->isNotEmpty() ? $invoice->sourceItems->map(fn ($item) => [
                            'code' => $item->code, 'name' => $item->description, 'amount' => (string) $item->amount,
                        ])->all() : [[
                            'code' => '620100',
                            'name' => config('e1.d2.mock_dataset') === 'sqlite' ? 'Item uji E-1 (bukan data D-2)' : 'Jasa demo '.$number,
                            'amount' => $dpp,
                        ]],
                        'dpp' => $dpp,
                        'ppn' => $ppn,
                        'ppnbm' => '0.00',
                        'buyer' => $invoice->termin?->contract?->client_name ?? $invoice->termin?->contract?->project_id ?? 'Referensi D-2',
                    ],
                ];

                return [$id => $fixture];
            })
            ->all();
    }

    /** Build fictitious tax PDFs using the dates and commercial values returned by the active D-2 provider. */
    public static function fromReferences(array $references): array
    {
        $fixtures = [];
        foreach ($references as $row) {
            foreach (['invoiceId', 'invoiceNumber', 'invoiceDate', 'dppAmount', 'ppnAmount'] as $field) {
                if (! is_string($row[$field] ?? null) || $row[$field] === '') {
                    throw new RuntimeException('Invoice D-2 belum memiliki '.$field.' yang diperlukan untuk PDF demo.');
                }
            }
            $id = $row['invoiceId'];
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $row['invoiceDate']);
            if ($date->format('Y-m-d') !== $row['invoiceDate']) {
                throw new RuntimeException('Tanggal invoice D-2 tidak valid: '.$id);
            }
            $serial = 10000000 + (hexdec(substr(hash('sha256', $id), 0, 7)) % 89999999);
            $fixtures[$id] = [
                'invoiceId' => $id, 'invoiceNumber' => $row['invoiceNumber'],
                'invoiceDate' => $date->format('Y-m-d'), 'taxPeriod' => $date->format('Y-m'),
                'file' => 'efaktur-'.$id.'.pdf',
                'pdf' => [
                    'nsfp' => sprintf('010.000-%s.%08d', $date->format('y'), $serial),
                    'date' => $date->locale('id')->translatedFormat('j F Y'),
                    'invoiceNumber' => $row['invoiceNumber'],
                    'items' => [['code' => '620100', 'name' => 'Item uji E-1 (bukan rincian D-2)', 'amount' => Money::normalize($row['dppAmount'])]],
                    'dpp' => Money::normalize($row['dppAmount']), 'ppn' => Money::normalize($row['ppnAmount']), 'ppnbm' => '0.00',
                    'buyer' => $row['clientName'] ?? $row['projectName'] ?? $row['projectId'] ?? 'Referensi D-2',
                ],
            ];
        }

        return $fixtures;
    }

    private static function replacementFor(array $fixture): array
    {
        $fixture['file'] = 'efaktur-'.$fixture['invoiceId'].'-pengganti.pdf';
        $fixture['pdf']['nsfp'] = sprintf(
            '010.001-%s.%08d',
            substr($fixture['invoiceDate'], 2, 2),
            (int) substr($fixture['pdf']['nsfp'], -8) + 1,
        );

        return $fixture;
    }

    public static function samples(?array $invoiceIds = null): array
    {
        $samples = [];
        foreach (self::invoices(invoiceIds: $invoiceIds) as $id => $fixture) {
            $samples[$fixture['file']] = $fixture + ['seeded' => true, 'flow' => 'create'];
            $replacement = self::replacementFor($fixture);
            $samples[$replacement['file']] = $replacement + ['seeded' => false, 'flow' => 'replace'];
        }

        return $samples;
    }

    public static function payments(?array $invoiceIds = null): array
    {
        $totals = [];
        foreach (self::invoices(invoiceIds: $invoiceIds) as $invoice) {
            $period = $invoice['taxPeriod'];
            $totals[$period] = isset($totals[$period])
                ? Money::add($totals[$period], $invoice['pdf']['ppn'])
                : Money::normalize($invoice['pdf']['ppn']);
        }
        ksort($totals);

        $payments = [];
        foreach (array_values(array_keys($totals)) as $index => $period) {
            $suffix = str_replace('-', '', $period);
            $sequence = sprintf('%03d', $index + 1);
            $payments[] = [
                'taxPeriod' => $period,
                'taxYear' => (int) substr($period, 0, 4),
                'amountPaid' => $totals[$period],
                'billingId' => "BILL-DEMO-{$suffix}-{$sequence}",
                'ntpn' => "NTPN-DEMO-{$suffix}-{$sequence}",
            ];
        }

        return $payments;
    }

    /** Lampiran sesuai Q-09: pembayaran contoh pada tanggal 10 bulan berikutnya. */
    public static function paymentDocumentFields(array $payment, bool $proof = false): array
    {
        $date = CarbonImmutable::createFromFormat('!Y-m', $payment['taxPeriod'])->addMonth()->day(10);

        $fields = [
            'Masa pajak' => $payment['taxPeriod'],
            'Tahun pajak' => (string) $payment['taxYear'],
            'ID Billing' => $payment['billingId'],
            'Jumlah setor' => 'Rp '.Money::display($payment['amountPaid']),
        ];
        if ($proof) {
            $fields['NTPN'] = $payment['ntpn'];
            $fields['Tanggal pembayaran'] = $date->locale('id')->translatedFormat('j F Y');
        } else {
            $fields['Tanggal e-Billing'] = $date->subDay()->locale('id')->translatedFormat('j F Y');
        }

        return $fields;
    }

    public static function paymentDocuments(?array $invoiceIds = null): array
    {
        $documents = [];
        foreach (self::payments($invoiceIds) as $sequence => $payment) {
            $types = ['e-billing' => 'e-Billing Demo'];
            if (isset($payment['ntpn'])) {
                $types['bukti-pembayaran'] = 'Bukti Pembayaran Demo';
            }
            foreach ($types as $prefix => $title) {
                $fields = self::paymentDocumentFields($payment, proof: $prefix === 'bukti-pembayaran');
                $name = sprintf('%s-%s-%03d.pdf', $prefix, $payment['taxPeriod'], $sequence + 1);
                $documents[$name] = compact('title', 'fields');
            }
        }

        return $documents;
    }
}
