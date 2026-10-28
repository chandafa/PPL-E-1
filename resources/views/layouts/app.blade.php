<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('title', 'Pajak Proyek') · E-1</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-page="{{ $page }}" @isset($taxInvoiceId) data-invoice-id="{{ $taxInvoiceId }}" @endisset @isset($taxPeriod) data-period="{{ $taxPeriod }}" @endisset>
    <div hidden aria-hidden="true">{!! file_get_contents(resource_path('icons/solar.svg')) !!}</div>
    <script type="application/json" id="e1-config">{!! json_encode(['uploadMaxKb' => config('e1.upload.max_kb'), 'month' => now('Asia/Jakarta')->format('Y-m'), 'claimAccount' => config('e1.f1.claim_akun_id'), 'claimRole' => config('e1.f1.claim_role'), 'd2Driver' => config('e1.d2.driver')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:bg-white focus:p-4">Lewati ke konten</a>
    @if($page !== 'login')
    <div id="sidebar-backdrop" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden" hidden></div>
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-border bg-white transition-transform lg:translate-x-0" aria-label="Navigasi utama">
        <a href="{{ route('ui.invoices') }}" class="flex min-h-24 items-center gap-3 border-b border-border px-6"><span class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-sm font-bold text-white">E1</span><span><span class="block text-sm font-semibold">Pajak Proyek</span><span class="mt-1 block text-xs text-muted">ERP Anaheim Nimbus</span></span></a>
        <div class="flex items-center justify-between px-6 pb-2 pt-7"><p class="text-xs font-medium text-muted">Pencatatan pajak</p><button class="btn btn-ghost lg:hidden" id="close-menu" aria-label="Tutup menu"><x-icon name="close" /></button></div>
        <nav class="space-y-1 px-3">
            <a class="nav-link" href="{{ route('ui.invoices') }}" @if(in_array($page, ['invoices','create','detail'])) aria-current="page" @endif><x-icon name="document" />Faktur Pajak</a>
            <a class="nav-link" href="{{ route('ui.d2-invoices') }}" @if($page === 'd2-invoices') aria-current="page" @endif><x-icon name="document" />Invoice D-2</a>
            <a class="nav-link" href="{{ route('ui.recaps') }}" @if($page === 'recap') aria-current="page" @endif><x-icon name="calendar" />Rekap Masa Pajak</a>
            <a class="nav-link" href="{{ route('ui.payments', ['taxPeriod' => $taxPeriod ?? now('Asia/Jakarta')->format('Y-m')]) }}" @if($page === 'payment') aria-current="page" @endif><x-icon name="wallet" />Pembayaran Pajak</a>
        </nav>
        <div class="mt-auto border-t border-border p-5"><p class="truncate text-sm font-semibold" data-account>Memeriksa akses</p><p class="mt-1 text-xs text-muted" data-role></p><button class="btn btn-ghost mt-3 w-full justify-start px-0" id="logout"><x-icon name="logout" />Keluar</button></div>
    </aside>
    <div class="lg:ml-64" id="app-main">
        <header id="topbar" class="flex min-h-16 items-center justify-between gap-3 border-b border-border bg-white px-4 sm:px-8"><div class="flex items-center gap-3"><button id="open-menu" class="btn btn-ghost lg:hidden" aria-controls="sidebar" aria-expanded="false"><x-icon name="menu" />Menu</button><nav class="flex items-center gap-2 text-xs text-muted" aria-label="Breadcrumb"><a href="{{ route('ui.invoices') }}" class="inline-flex min-h-11 items-center">Pajak proyek</a><x-icon name="right" class="h-3 w-3" /><span aria-current="page">@yield('breadcrumb', 'Faktur Pajak')</span></nav></div><span class="hidden text-xs text-muted sm:inline">Modul E-1</span></header>
        <main id="main-content" class="mx-auto max-w-[1600px] px-4 py-7 sm:px-8 sm:py-9">
            <div id="app-alert" class="notice mb-5" role="alert" tabindex="-1" hidden></div>
            <div id="auth-pending" class="panel"><x-ui.skeleton /></div>
            <div id="access-denied" class="panel empty-state" hidden><x-icon name="lock" class="mx-auto mb-4 h-8 w-8" /><h1>Akses ditolak</h1><p>Modul pajak hanya tersedia untuk Admin, CEO, dan COO. Gunakan akun yang memiliki akses.</p><a href="/login" class="btn btn-primary mt-5">Ganti akun</a></div>
            <div id="page-content" hidden>@yield('content')</div>
        </main>
    </div>
    @else
    <main id="main-content" class="mx-auto flex min-h-dvh max-w-6xl flex-col justify-center gap-10 px-5 py-12 md:flex-row md:items-center md:gap-20"><section class="md:w-1/2"><p class="mb-5 text-sm font-semibold text-primary">ERP Anaheim Nimbus / E-1</p><h1 class="text-4xl font-semibold leading-tight tracking-tight sm:text-5xl">Pencatatan pajak,<br>terhubung ke proyek.</h1><p class="mt-5 max-w-md text-base leading-relaxed text-muted">Catat e-Faktur, periksa rekap masa pajak, dan dokumentasikan pembayaran dalam satu alur.</p><div class="mt-8 flex items-center gap-3 text-sm text-muted"><x-icon name="document" /><span>Faktur keluaran</span><span aria-hidden="true">·</span><span>Rekap bulanan</span></div></section><section class="w-full max-w-lg md:w-1/2"><div id="app-alert" class="notice mb-5" role="alert" tabindex="-1" hidden></div>@yield('content')</section></main>
    @endif
    <noscript><p class="notice notice-error m-5">Aktifkan JavaScript untuk mengambil dan menyimpan data melalui API E-1.</p></noscript>
</body>
</html>
