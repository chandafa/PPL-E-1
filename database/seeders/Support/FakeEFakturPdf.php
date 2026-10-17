<?php

namespace Database\Seeders\Support;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Pembuat PDF e-Faktur FIKTIF untuk uji dan seed (contoh dokumen asli dari
 * mitra, Q-17, belum diterima). Tata letak meniru Faktur Pajak e-Faktur:
 * kode dan nomor seri, penjual, pembeli, tabel barang/jasa, lalu total.
 * Angka memakai format Indonesia (titik ribuan, koma desimal).
 */
final class FakeEFakturPdf
{
    /**
     * @param  array{
     *   nsfp?: string, date?: string, place?: string,
     *   items?: list<array{code: string, name: string, amount: string, discount?: string}>,
     *   discount?: string, advance?: string, dpp?: string, ppn?: string, ppnbm?: string|null,
     *   seller?: string, buyer?: string
     * }  $o  nominal diberikan sebagai string "100000000.00"
     */
    public static function make(array $o = []): string
    {
        $o += [
            'nsfp' => '010.000-26.12345678',
            'date' => '1 November 2026',
            'place' => 'JAKARTA',
            'items' => [['code' => '620100', 'name' => 'Jasa Pengembangan Perangkat Lunak Termin 1', 'amount' => '100000000.00']],
            'discount' => '0.00',
            'advance' => '0.00',
            'dpp' => '100000000.00',
            'ppn' => '11000000.00',
            'ppnbm' => '0.00',
            'seller' => 'PT Anaheim Nimbus Universal',
            'buyer' => 'PT Lintas Rel Nusantara',
        ];

        $fmt = fn (string $v): string => self::id($v);
        $sum = '0.00';
        $rows = '';
        foreach ($o['items'] as $i => $item) {
            $sum = bcadd($sum, $item['amount'], 2);
            $rows .= '<tr><td>'.($i + 1).'</td><td>'.$item['code'].'</td><td>'.htmlspecialchars($item['name'])
                .'<br>Rp '.$fmt($item['amount']).' x 1,00 Unit'
                .'<br>Potongan Harga = Rp '.$fmt($item['discount'] ?? '0.00')
                .'<br>PPnBM (0,00%) = Rp 0,00</td><td class="r">'.$fmt($item['amount']).'</td></tr>';
        }

        $ppnbmRow = $o['ppnbm'] === null ? '' : '<tr><td>Jumlah PPnBM (Pajak Penjualan atas Barang Mewah)</td><td class="r">'.$fmt($o['ppnbm']).'</td></tr>';

        $html = '<html><head><meta charset="utf-8"><style>
            body{font-family:Helvetica,Arial,sans-serif;font-size:10px}
            table{width:100%;border-collapse:collapse} td,th{border:1px solid #444;padding:4px;vertical-align:top}
            .r{text-align:right} h2{text-align:center}
        </style></head><body>
            <h2>FAKTUR PAJAK</h2>
            <p>Kode dan Nomor Seri Faktur Pajak : '.$o['nsfp'].'</p>
            <p><b>Pengusaha Kena Pajak</b><br>Nama : '.htmlspecialchars($o['seller']).'<br>Alamat : Jl. Fiktif No. 1, Jakarta<br>NPWP : 00.000.000.0-000.000</p>
            <p><b>Pembeli Barang Kena Pajak / Penerima Jasa Kena Pajak</b><br>Nama : '.htmlspecialchars($o['buyer']).'<br>Alamat : Jl. Contoh No. 2, Bandung<br>NPWP : 11.111.111.1-111.111</p>
            <table>
                <tr><th>No.</th><th>Kode Barang / Jasa</th><th>Nama Barang Kena Pajak / Jasa Kena Pajak</th><th>Harga Jual / Penggantian / Uang Muka / Termin (Rp)</th></tr>
                '.$rows.'
                <tr><td colspan="3">Harga Jual / Penggantian / Uang Muka / Termin *)</td><td class="r">'.$fmt($sum).'</td></tr>
                <tr><td colspan="3">Dikurangi Potongan Harga</td><td class="r">'.$fmt($o['discount']).'</td></tr>
                <tr><td colspan="3">Dikurangi Uang Muka yang telah diterima</td><td class="r">'.$fmt($o['advance']).'</td></tr>
                <tr><td colspan="3">Dasar Pengenaan Pajak</td><td class="r">'.$fmt($o['dpp']).'</td></tr>
                <tr><td colspan="3">Jumlah PPN (Pajak Pertambahan Nilai)</td><td class="r">'.$fmt($o['ppn']).'</td></tr>
                '.($o['ppnbm'] === null ? '' : '<tr><td colspan="3">Jumlah PPnBM (Pajak Penjualan atas Barang Mewah)</td><td class="r">'.$fmt($o['ppnbm']).'</td></tr>').'
            </table>
            <p>'.$o['place'].', '.$o['date'].'</p>
            <p>Ditandatangani secara elektronik<br>Direktur Utama</p>
        </body></html>';

        unset($ppnbmRow);

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    /** PDF sah yang bukan e-Faktur (tanpa label pajak). */
    public static function unrelated(): string
    {
        $dompdf = new Dompdf;
        $dompdf->loadHtml('<html><body><h1>Surat penawaran</h1><p>Bukan faktur pajak.</p></body></html>');
        $dompdf->render();

        return $dompdf->output();
    }

    /** Lampiran pembayaran fiktif dengan label demo yang terlihat di dokumen. */
    public static function attachment(string $title, array $fields = []): string
    {
        $html = '<html><body><h1>'.htmlspecialchars($title).'</h1><p>DOKUMEN DEMO FIKTIF. Bukan bukti transaksi nyata.</p><dl>';
        foreach ($fields as $key => $value) {
            $html .= '<dt>'.htmlspecialchars((string) $key).'</dt><dd>'.htmlspecialchars((string) $value).'</dd>';
        }
        $dompdf = new Dompdf;
        $dompdf->loadHtml($html.'</dl></body></html>');
        $dompdf->render();

        return $dompdf->output();
    }

    /** "100000000.00" -> "100.000.000,00" */
    public static function id(string $value): string
    {
        [$int, $dec] = array_pad(explode('.', $value, 2), 2, '00');

        return number_format((int) $int, 0, ',', '.').','.str_pad($dec, 2, '0');
    }
}
