<?php

namespace Database\Seeders\Support;

final class InvoiceFlowDataset
{
    public static function make(string $batch, int $serial): array
    {
        $references = $samples = [];
        $scenarios = [
            1 => ['2026-10-15', '2026-10-15', '15 Oktober 2026', 'same_month'],
            2 => ['2026-11-16', '2026-10-16', '16 Oktober 2026', 'next_month'],
            3 => ['2026-12-17', '2026-10-17', '17 Oktober 2026', 'two_months_later'],
            4 => ['2026-10-18', '2026-10-18', '18 Oktober 2026', 'reserve'],
            5 => ['2026-12-19', '2026-11-19', '19 November 2026', 'next_month'],
            6 => ['2027-01-20', '2026-12-20', '20 Desember 2026', 'next_year'],
        ];
        foreach ([1 => '10000000.00', 2 => '20000000.00', 3 => '10000000.00', 4 => '5000000.00', 5 => '7000000.00', 6 => '8000000.00'] as $number => $dpp) {
            $id = sprintf('inv-flow-%s-%03d', $batch, $number);
            [$date, $taxDate, $pdfDate, $scenario] = $scenarios[$number];
            $ppn = bcmul($dpp, '0.11', 2);
            $reference = [
                'invoiceId' => $id, 'invoiceNumber' => sprintf('INV-FLOW-%s-%03d', strtoupper($batch), $number),
                'manualUploadOnly' => true, 'uploadFlowBatch' => $batch,
                'terminId' => sprintf('trm-flow-%s-%03d', $batch, $number), 'taxParamId' => 'pjk-demo-001',
                'invoiceDate' => $date, 'dppAmount' => $dpp, 'ppnAmount' => $ppn, 'pphAmount' => '0.00',
                'totalAmount' => bcadd($dpp, $ppn, 2), 'status' => 'TERTAGIH',
                'projectId' => 'prj-flow-'.$batch, 'projectName' => 'Proyek Uji Flow '.$batch,
                'clientId' => 'client-flow-'.$batch, 'clientName' => 'Klien Uji Flow '.$batch,
            ];
            $references[] = $reference;
            $pdf = [
                'nsfp' => sprintf('010.000-26.%08d', $serial + $number), 'date' => $pdfDate,
                'seller' => 'PT Anaheim Nimbus Universal (DUMMY - BUKAN DOKUMEN PAJAK RESMI)',
                'buyer' => $reference['clientName'].' (DUMMY)',
                'items' => [['code' => '620100', 'name' => 'Jasa uji '.$reference['invoiceNumber'], 'amount' => $dpp]],
                'dpp' => $dpp, 'ppn' => $ppn, 'ppnbm' => '0.00',
            ];
            if ($number === 3) {
                $pdf['items'] = [
                    ['code' => '620100', 'name' => 'Jasa konfigurasi dummy', 'amount' => '6000000.00', 'discount' => '100000.00'],
                    ['code' => '620200', 'name' => 'Jasa pendampingan dummy', 'amount' => '4600000.00'],
                ];
                $pdf += ['discount' => '100000.00', 'advance' => '500000.00'];
                $pdf['ppnbm'] = '100000.00';
            }
            $fileNumber = $number <= 4 ? $number : $number + 6;
            $samples[sprintf('%02d-faktur-%03d.pdf', $fileNumber, $number)] = [
                'invoiceId' => $id, 'invoiceDate' => $date, 'taxInvoiceDate' => $taxDate,
                'taxPeriod' => substr($taxDate, 0, 7), 'scenario' => $scenario,
                'flow' => $number === 4 ? 'reserve' : 'create', 'pdf' => $pdf,
            ];
        }
        $replacement = $samples['01-faktur-001.pdf'];
        $replacement['flow'] = 'replace';
        $replacement['pdf'] = array_replace($replacement['pdf'], [
            'nsfp' => sprintf('011.000-26.%08d', $serial + 7), 'date' => '20 Oktober 2026',
            'dpp' => '9000000.00', 'ppn' => '990000.00',
            'items' => [['code' => '620100', 'name' => 'Jasa dummy pengganti invoice 001', 'amount' => '9000000.00']],
        ]);
        $replacement['taxInvoiceDate'] = '2026-10-20';
        $samples['05-pengganti-001.pdf'] = $replacement;
        $reissued = $samples['02-faktur-002.pdf'];
        $reissued['flow'] = 'reissue_after_cancel';
        $reissued['pdf'] = array_replace($reissued['pdf'], [
            'nsfp' => sprintf('010.000-26.%08d', $serial + 8), 'date' => '21 Oktober 2026',
            'dpp' => '18000000.00', 'ppn' => '1980000.00',
            'items' => [['code' => '620100', 'name' => 'Jasa dummy pencatatan ulang invoice 002', 'amount' => '18000000.00']],
        ]);
        $reissued['taxInvoiceDate'] = '2026-10-21';
        $samples['06-catat-ulang-002.pdf'] = $reissued;

        return ['batch' => $batch, 'references' => $references, 'samples' => $samples,
            'taxPeriod' => '2026-10', 'projectId' => 'prj-flow-'.$batch,
            'billingId' => 'BILL-FLOW-'.$batch, 'ntpn' => 'NTPN-FLOW-'.$batch,
            'expected' => [
                '2026-10' => ['invoiceCount' => 3, 'totalDpp' => '37000000.00', 'totalPpn' => '4070000.00', 'totalPpnbm' => '100000.00', 'amountPaid' => '4070000.00'],
                '2026-11' => ['invoiceCount' => 1, 'totalDpp' => '7000000.00', 'totalPpn' => '770000.00', 'totalPpnbm' => '0.00', 'amountPaid' => '0.00'],
                '2026-12' => ['invoiceCount' => 1, 'totalDpp' => '8000000.00', 'totalPpn' => '880000.00', 'totalPpnbm' => '0.00', 'amountPaid' => '0.00'],
                '2027-01' => ['invoiceCount' => 0, 'totalDpp' => '0.00', 'totalPpn' => '0.00', 'totalPpnbm' => '0.00', 'amountPaid' => '0.00'],
            ],
        ];
    }
}
