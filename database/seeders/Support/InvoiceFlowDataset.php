<?php

namespace Database\Seeders\Support;

final class InvoiceFlowDataset
{
    public static function make(string $batch, int $serial): array
    {
        $references = $samples = [];
        foreach ([1 => '10000000.00', 2 => '20000000.00', 3 => '10000000.00', 4 => '5000000.00'] as $number => $dpp) {
            $id = sprintf('inv-flow-%s-%03d', $batch, $number);
            $date = sprintf('2026-09-%02d', 14 + $number);
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
                'nsfp' => sprintf('010.000-26.%08d', $serial + $number), 'date' => (14 + $number).' September 2026',
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
            $samples[sprintf('%02d-faktur-%03d.pdf', $number, $number)] = ['invoiceId' => $id, 'flow' => $number === 4 ? 'reserve' : 'create', 'pdf' => $pdf];
        }
        $replacement = $samples['01-faktur-001.pdf'];
        $replacement['flow'] = 'replace';
        $replacement['pdf'] = array_replace($replacement['pdf'], [
            'nsfp' => sprintf('011.000-26.%08d', $serial + 5), 'date' => '20 September 2026',
            'dpp' => '9000000.00', 'ppn' => '990000.00',
            'items' => [['code' => '620100', 'name' => 'Jasa dummy pengganti invoice 001', 'amount' => '9000000.00']],
        ]);
        $samples['05-pengganti-001.pdf'] = $replacement;
        $reissued = $samples['02-faktur-002.pdf'];
        $reissued['flow'] = 'reissue_after_cancel';
        $reissued['pdf'] = array_replace($reissued['pdf'], [
            'nsfp' => sprintf('010.000-26.%08d', $serial + 6), 'date' => '21 September 2026',
            'dpp' => '18000000.00', 'ppn' => '1980000.00',
            'items' => [['code' => '620100', 'name' => 'Jasa dummy pencatatan ulang invoice 002', 'amount' => '18000000.00']],
        ]);
        $samples['06-catat-ulang-002.pdf'] = $reissued;

        return ['batch' => $batch, 'references' => $references, 'samples' => $samples,
            'taxPeriod' => '2026-09', 'projectId' => 'prj-flow-'.$batch,
            'billingId' => 'BILL-FLOW-'.$batch, 'ntpn' => 'NTPN-FLOW-'.$batch,
            'expected' => ['invoiceCount' => 3, 'totalDpp' => '37000000.00', 'totalPpn' => '4070000.00', 'totalPpnbm' => '100000.00', 'amountPaid' => '4070000.00'],
        ];
    }
}
