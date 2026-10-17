@php
    /** @var \App\Services\Export\RecapReport $report */
    $rp = fn (string $v): string => 'Rp '.\App\Support\Money::display($v);
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Pajak {{ \App\Support\DateDisplay::period($report->taxPeriod) }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        h2 { font-size: 12px; margin: 16px 0 6px; }
        .meta { color: #555; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 6px; }
        th { background: #eee; text-align: left; }
        td.r, th.r { text-align: right; }
        .summary td:first-child { width: 40%; background: #f7f7f7; }
    </style>
</head>
<body>
    <h1>Rekap Pajak Masa {{ \App\Support\DateDisplay::period($report->taxPeriod) }}</h1>
    <div class="meta">PT Anaheim Nimbus Universal &middot; Modul E-1 Pencatatan e-Faktur dan pajak proyek</div>

    <h2>Ringkasan</h2>
    <table class="summary">
        @foreach ($report->summaryRows() as [$label, $value])
            <tr>
                <td>{{ $label }}</td>
                <td class="r">
                    @if (in_array($label, ['Total DPP', 'Total PPN', 'Total PPnBM', 'Jumlah setor', 'Selisih (jumlah setor - total PPN)'], true))
                        {{ $rp($value) }}
                    @else
                        {{ $value }}
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    <h2>Daftar Faktur</h2>
    <table>
        <thead>
            <tr>
                <th>NSFP</th><th>Tanggal</th><th>Invoice</th><th>Proyek</th><th>Klien</th><th>Status</th>
                <th class="r">DPP</th><th class="r">PPN</th><th class="r">PPnBM</th><th>Dihitung</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report->invoices as $i)
                <tr>
                    <td>{{ $i['taxInvoiceNumber'] }}</td>
                    <td>{{ $i['taxInvoiceDate'] }}</td>
                    <td>{{ $i['invoiceId'] }}</td>
                    <td>{{ $i['projectId'] }}</td>
                    <td>{{ $i['clientId'] }}</td>
                    <td>{{ $i['status'] }}</td>
                    <td class="r">{{ $rp($i['dpp']) }}</td>
                    <td class="r">{{ $rp($i['ppn']) }}</td>
                    <td class="r">{{ $rp($i['ppnbm']) }}</td>
                    <td>{{ $i['isCounted'] }}</td>
                </tr>
            @empty
                <tr><td colspan="10">Belum ada faktur pada masa pajak ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Pembayaran</h2>
    <table>
        <thead>
            <tr><th>ID Billing</th><th>NTPN</th><th class="r">Jumlah setor</th><th>Status</th><th>Dicatat</th></tr>
        </thead>
        <tbody>
            @forelse ($report->payments as $p)
                <tr>
                    <td>{{ $p['billingId'] }}</td>
                    <td>{{ $p['ntpn'] !== '' ? $p['ntpn'] : '-' }}</td>
                    <td class="r">{{ $rp($p['amountPaid']) }}</td>
                    <td>{{ $p['status'] }}</td>
                    <td>{{ $p['createdAt'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada pembayaran dicatat.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
