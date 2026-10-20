<?php

namespace Database\Seeders\Support;

use Carbon\CarbonImmutable;

/** Satu katalog untuk seeder dan PDF demo; seluruh identitas adalah data fiktif. */
final class DemoDataset
{
    public static function invoices(): array
    {
        // Tanggal pajak adalah fixture tersendiri, tidak diturunkan dari invoice D-2.
        $taxDates = [
            1 => '2026-11-01', 2 => '2026-11-05', 3 => '2026-11-12',
            4 => '2026-12-02', 5 => '2026-12-15', 6 => '2026-10-25', 7 => '2026-10-26',
            34 => '2027-01-10', 35 => '2027-02-10',
        ];
        foreach (range(10, 33) as $number) {
            $taxDates[$number] = sprintf('2026-10-%02d', $number - 9);
        }
        $references = json_decode(file_get_contents(database_path('data/d2-invoices.json')), true, flags: JSON_THROW_ON_ERROR);
        $fixtures = [];
        foreach ($references as $reference) {
            if ($reference['manualUploadOnly'] ?? false) {
                continue;
            }
            $id = $reference['invoiceId'];
            $number = (int) substr($id, -3);
            $date = CarbonImmutable::parse($taxDates[$number]);
            $fixtures[$id] = [
                'invoiceId' => $id,
                'invoiceDate' => $reference['invoiceDate'],
                'taxInvoiceDate' => $date->format('Y-m-d'),
                'file' => 'efaktur-'.$id.'.pdf',
                'pdf' => [
                    'nsfp' => sprintf('010.000-%s.%08d', $date->format('y'), 88000000 + $number),
                    'date' => $date->locale('id')->translatedFormat('j F Y'),
                    'items' => [['code' => '620100', 'name' => 'Jasa demo '.$reference['invoiceNumber'], 'amount' => $reference['dppAmount']]],
                    'dpp' => $reference['dppAmount'], 'ppn' => $reference['ppnAmount'],
                    'buyer' => $reference['clientName'] ?? 'Klien demo tanpa referensi',
                ],
            ];
        }
        $fixtures['inv-demo-001']['pdf']['nsfp'] = '010.000-26.12345678';
        $fixtures['inv-demo-001']['pdf']['items'] = [['code' => '620100', 'name' => 'Jasa Pengembangan Portal ERP Termin 1', 'amount' => '100000000.00']];
        $fixtures['inv-demo-001']['seedDate'] = '10 November 2026';
        $fixtures['inv-demo-001']['correctDate'] = '2026-11-01';
        $fixtures['inv-demo-002']['pdf']['nsfp'] = '010.000-26.12345679';
        $fixtures['inv-demo-002']['pdf']['items'] = [
            ['code' => '620200', 'name' => 'Lisensi Aplikasi Gudang', 'amount' => '150000000.00'],
            ['code' => '620300', 'name' => 'Jasa Implementasi dan Pelatihan', 'amount' => '100000000.00'],
        ];
        $fixtures['inv-demo-003']['pdf']['nsfp'] = '010.000-26.12345680';
        $fixtures['inv-demo-003']['pdf']['items'] = [['code' => '620200', 'name' => 'Jasa Integrasi Modul Gudang', 'amount' => '75000000.00']];
        $fixtures['inv-demo-004']['pdf']['nsfp'] = '010.000-26.12345682';
        $fixtures['inv-demo-004']['cancel'] = true;
        $fixtures['inv-demo-005']['pdf']['nsfp'] = '010.000-26.12345683';
        // Harga 1.160.000 dikurangi potongan 10.000 dan uang muka 50.000 = DPP 1.100.000.
        $fixtures['inv-demo-010']['pdf'] += ['discount' => '10000.00', 'advance' => '50000.00', 'ppnbm' => '100000.00'];
        $fixtures['inv-demo-010']['pdf']['items'] = [
            ['code' => '620100', 'name' => 'Jasa demo konfigurasi', 'amount' => '600000.00', 'discount' => '10000.00'],
            ['code' => '620200', 'name' => 'Jasa demo pendampingan', 'amount' => '560000.00'],
        ];

        return $fixtures;
    }

    /** Invoice 006 dan 007 sengaja disisakan untuk pencatatan melalui UI. */
    public static function seededInvoices(): array
    {
        return array_diff_key(self::invoices(), array_flip(['inv-demo-006', 'inv-demo-007']));
    }

    public static function originalReplacement(): array
    {
        return [
            'file' => 'efaktur-inv-demo-003-pengganti.pdf', 'invoiceId' => 'inv-demo-003',
            'pdf' => [
                'nsfp' => '010.001-26.12345681', 'date' => '20 November 2026',
                'items' => [['code' => '620200', 'name' => 'Jasa Integrasi Modul Gudang (pengganti)', 'amount' => '70000000.00']],
                'dpp' => '70000000.00', 'ppn' => '7700000.00', 'buyer' => 'PT Lintas Rel Nusantara',
            ],
        ];
    }

    public static function samples(): array
    {
        $invoices = self::invoices();
        $samples = [];
        foreach ($invoices as $id => $fixture) {
            $samples[$fixture['file']] = $fixture + ['seeded' => ! in_array($id, ['inv-demo-006', 'inv-demo-007'], true), 'flow' => 'create'];
        }
        $replacement = self::originalReplacement();
        $samples[$replacement['file']] = $replacement + ['seeded' => true, 'flow' => 'replace'];
        foreach (['inv-demo-001', 'inv-demo-006'] as $id) {
            $fixture = $invoices[$id];
            $fixture['file'] = 'efaktur-'.$id.($id === 'inv-demo-001' ? '-pengganti-baru' : '-pengganti').'.pdf';
            $fixture['pdf']['nsfp'] = sprintf('011.000-26.%08d', 88000000 + (int) substr($id, -3));
            $fixture['pdf']['dpp'] = '9000000.00';
            $fixture['pdf']['ppn'] = '990000.00';
            $fixture['pdf']['items'] = [['code' => '620100', 'name' => 'Jasa demo pengganti', 'amount' => '9000000.00']];
            $samples[$fixture['file']] = $fixture + ['seeded' => false, 'flow' => 'replace'];
        }
        $reissued = $invoices['inv-demo-004'];
        $reissued['file'] = 'efaktur-inv-demo-004-baru.pdf';
        $reissued['pdf']['nsfp'] = '010.000-26.88000004';
        $samples[$reissued['file']] = $reissued + ['seeded' => false, 'flow' => 'create'];

        return $samples;
    }

    public static function payments(): array
    {
        return [
            ['taxPeriod' => '2026-11', 'taxYear' => 2026, 'amountPaid' => '46200000.00', 'billingId' => 'BILL-202611-001'],
            ['taxPeriod' => '2026-10', 'taxYear' => 2026, 'amountPaid' => '1000000.00', 'billingId' => 'BILL-DEMO-202610-001', 'ntpn' => 'NTPN-DEMO-202610-001'],
            ['taxPeriod' => '2026-10', 'taxYear' => 2026, 'amountPaid' => '4940000.00', 'billingId' => 'BILL-DEMO-202610-002', 'ntpn' => 'NTPN-DEMO-202610-002'],
            ['taxPeriod' => '2027-01', 'taxYear' => 2027, 'amountPaid' => '1000000.00', 'billingId' => 'BILL-DEMO-202701-001', 'ntpn' => 'NTPN-DEMO-202701-001'],
            ['taxPeriod' => '2027-02', 'taxYear' => 2027, 'amountPaid' => '1200000.00', 'billingId' => 'BILL-DEMO-202702-001', 'ntpn' => 'NTPN-DEMO-202702-001'],
        ];
    }
}
