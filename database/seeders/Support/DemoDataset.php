<?php

namespace Database\Seeders\Support;

use App\Support\Money;
use Carbon\CarbonImmutable;
use RuntimeException;

/** Satu katalog untuk seeder dan PDF demo; seluruh identitas adalah data fiktif. */
final class DemoDataset
{
    public static function invoices(bool $includeManual = false): array
    {
        $references = json_decode(file_get_contents((string) config('e1.d2.mock_file')), true, flags: JSON_THROW_ON_ERROR);
        $fixtures = [];
        foreach ($references as $reference) {
            $manual = $reference['manualUploadOnly'] ?? false;
            if ($manual && ! $includeManual) {
                continue;
            }
            $id = $reference['invoiceId'];
            if (! is_string($id) || ! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]{0,99}$/D', $id) || isset($fixtures[$id])) {
                throw new RuntimeException('ID invoice demo tidak valid atau ganda.');
            }
            $number = (int) substr($id, -3);
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $reference['invoiceDate']);
            if ($date === false || $date->format('Y-m-d') !== $reference['invoiceDate']) {
                throw new RuntimeException("Tanggal invoice {$id} harus berupa tanggal kalender YYYY-MM-DD yang valid.");
            }
            $serial = $manual
                ? 90000000 + (hexdec(substr(hash('sha256', $id), 0, 7)) % 9000000)
                : 88000000 + $number;
            $fixtures[$id] = [
                'invoiceId' => $id,
                'invoiceNumber' => $reference['invoiceNumber'],
                'invoiceDate' => $date->format('Y-m-d'),
                'taxPeriod' => $date->format('Y-m'),
                'manualUploadOnly' => $manual,
                'file' => 'efaktur-'.$id.'.pdf',
                'pdf' => [
                    'nsfp' => sprintf('010.000-%s.%08d', $date->format('y'), $serial),
                    'date' => $date->locale('id')->translatedFormat('j F Y'),
                    'invoiceNumber' => $reference['invoiceNumber'],
                    'items' => [['code' => '620100', 'name' => 'Jasa demo '.$reference['invoiceNumber'], 'amount' => $reference['dppAmount']]],
                    'dpp' => $reference['dppAmount'], 'ppn' => $reference['ppnAmount'],
                    'ppnbm' => $reference['ppnbmAmount'] ?? '0.00',
                    'buyer' => $reference['clientName'] ?? 'Klien demo tanpa referensi',
                ],
            ];
        }
        $fixtures['inv-demo-001']['pdf']['nsfp'] = '010.000-26.12345678';
        $fixtures['inv-demo-001']['pdf']['items'] = [['code' => '620100', 'name' => 'Jasa Pengembangan Portal ERP Termin 1', 'amount' => '100000000.00']];
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
        $fixtures['inv-demo-010']['pdf'] += ['discount' => '10000.00', 'advance' => '50000.00'];
        $fixtures['inv-demo-010']['pdf']['items'] = [
            ['code' => '620100', 'name' => 'Jasa demo konfigurasi', 'amount' => '600000.00', 'discount' => '10000.00'],
            ['code' => '620200', 'name' => 'Jasa demo pendampingan', 'amount' => '560000.00'],
        ];
        // Skenario opsional barang mewah dan jasa pada proyek yang sama.
        // Tidak dimasukkan seeder standar jasa TI, dan bukan usaha nyata mitra.
        if (isset($fixtures['inv-manual-ppnbm-001'])) {
            $fixtures['inv-manual-ppnbm-001']['pdf']['seller'] = 'PT Produsen Yacht Demo (Fiktif)';
            $fixtures['inv-manual-ppnbm-001']['pdf']['items'] = [
                ['code' => 'YACHT-01', 'name' => 'Yacht rekreasi privat produksi sendiri, bukan angkutan umum/pariwisata (simulasi)', 'amount' => '1000000000.00'],
            ];
        }
        if (isset($fixtures['inv-manual-jasa-001'])) {
            $fixtures['inv-manual-jasa-001']['pdf']['items'] = [
                ['code' => 'JASA-01', 'name' => 'Jasa konfigurasi sistem inventaris yacht (simulasi)', 'amount' => '10000000.00'],
            ];
        }

        return $fixtures;
    }

    /** Invoice 006 dan 007 sengaja disisakan untuk pencatatan melalui UI. */
    public static function seededInvoices(): array
    {
        return array_diff_key(self::invoices(), array_flip(['inv-demo-006', 'inv-demo-007']));
    }

    public static function originalReplacement(): array
    {
        $fixture = self::invoices()['inv-demo-003'];
        $fixture['file'] = 'efaktur-inv-demo-003-pengganti.pdf';
        $fixture['pdf'] = array_replace($fixture['pdf'], [
            'nsfp' => '010.001-26.12345681',
            'items' => [['code' => '620200', 'name' => 'Jasa Integrasi Modul Gudang (pengganti)', 'amount' => '70000000.00']],
            'dpp' => '70000000.00', 'ppn' => '7700000.00',
        ]);

        return $fixture;
    }

    public static function samples(): array
    {
        $invoices = self::invoices(includeManual: true);
        $samples = [];
        foreach ($invoices as $id => $fixture) {
            $samples[$fixture['file']] = $fixture + [
                'seeded' => ! $fixture['manualUploadOnly'] && ! in_array($id, ['inv-demo-006', 'inv-demo-007'], true),
                'flow' => 'create',
            ];
        }
        $replacement = self::originalReplacement();
        $samples[$replacement['file']] = $replacement + ['seeded' => true, 'flow' => 'replace'];
        foreach (['inv-demo-001', 'inv-demo-006'] as $id) {
            $fixture = $invoices[$id];
            $fixture['file'] = 'efaktur-'.$id.($id === 'inv-demo-001' ? '-pengganti-baru' : '-pengganti').'.pdf';
            $fixture['pdf']['nsfp'] = sprintf('011.000-%s.%08d', substr($fixture['invoiceDate'], 2, 2), 88000000 + (int) substr($id, -3));
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
            'Jumlah setor' => 'Rp '.Money::display($payment['amountPaid']),
        ];
        if ($proof) {
            $fields['NTPN'] = $payment['ntpn'] ?? 'NTPN-DEMO-'.str_replace('-', '', $payment['taxPeriod']).'-001';
            $fields['Tanggal pembayaran'] = $date->locale('id')->translatedFormat('j F Y');
        } else {
            $fields['Tanggal e-Billing'] = $date->subDay()->locale('id')->translatedFormat('j F Y');
        }

        return $fields;
    }

    public static function paymentDocuments(): array
    {
        $documents = [];
        $sequences = [];
        // Desember menyediakan dokumen unggah tanpa menanam pembayaran pada seeder.
        $examples = array_merge(self::payments(), [[
            'taxPeriod' => '2026-12', 'taxYear' => 2026, 'amountPaid' => '6600000.00',
            'billingId' => 'BILL-DEMO-202612-001',
        ]]);
        foreach ($examples as $payment) {
            $period = $payment['taxPeriod'];
            $sequence = ($sequences[$period] ?? 0) + 1;
            $sequences[$period] = $sequence;
            foreach (['e-billing' => 'e-Billing Demo', 'bukti-pembayaran' => 'Bukti Pembayaran Demo'] as $prefix => $title) {
                $fields = self::paymentDocumentFields($payment, proof: $prefix === 'bukti-pembayaran');
                $documents[sprintf('%s-%s-%03d.pdf', $prefix, $period, $sequence)] = compact('title', 'fields');
                // Nama pendek dipertahankan untuk koleksi Postman November 2026.
                if ($period === '2026-11' && $sequence === 1) {
                    $documents[$prefix.'.pdf'] = compact('title', 'fields');
                }
            }
        }

        return $documents;
    }
}
