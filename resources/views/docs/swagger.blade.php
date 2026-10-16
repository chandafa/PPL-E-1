<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dokumentasi API E-1 | Swagger UI</title>
    <link rel="icon" href="{{ asset('vendor/swagger-ui/favicon-32x32.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/swagger-ui/swagger-ui.css') }}">
    <style>
        body { margin: 0; background: #fafafa; color: #263238; font-family: Arial, sans-serif; }
        .docs-help { max-width: 1400px; margin: 0 auto; padding: 20px; line-height: 1.6; }
        .docs-help h1 { margin: 0 0 8px; font-size: 24px; }
        .docs-help p { margin: 4px 0; }
        .docs-help a { color: #185b8a; }
        .docs-help code { overflow-wrap: anywhere; }
        .docs-status { margin: 24px 20px; }
    </style>
</head>
<body>
    <header class="docs-help">
        <h1>API E-1: e-Faktur dan pajak proyek</h1>
        <p>11 operasi API untuk faktur, rekap, pembayaran, ekspor, dan ringkasan pajak.</p>
        <p>Gunakan <strong>Authorize</strong> untuk memasukkan token F-1 tanpa awalan Bearer, lalu pilih <strong>Try it out</strong>. Permintaan tulis mengubah data pada server yang dipilih.</p>
        <p><a href="{{ route('docs.openapi', absolute: false) }}">Unduh kontrak OpenAPI</a>. Petunjuk login lokal tersedia di <code>docs/PENGUJIAN_API.md</code>.</p>
    </header>
    <main id="swagger-ui" aria-label="Dokumentasi dan pengujian API">
        <p class="docs-status" role="status">Memuat Swagger UI...</p>
    </main>
    <noscript><p class="docs-help">Aktifkan JavaScript untuk menggunakan Swagger UI. Kontrak tetap dapat diunduh melalui tautan di atas.</p></noscript>
    <script src="{{ asset('vendor/swagger-ui/swagger-ui-bundle.js') }}"></script>
    <script>
        if (typeof SwaggerUIBundle === 'undefined') {
            document.querySelector('.docs-status').textContent = 'Aset Swagger UI belum tersedia. Jalankan npm ci dari folder e1-api, lalu muat ulang halaman.';
        } else {
            window.ui = SwaggerUIBundle({
                url: @json(route('docs.openapi', absolute: false)),
                dom_id: '#swagger-ui',
                deepLinking: true,
                filter: true,
                docExpansion: 'list',
                displayRequestDuration: true,
                persistAuthorization: false,
                validatorUrl: null,
                presets: [SwaggerUIBundle.presets.apis],
                layout: 'BaseLayout'
            });
        }
    </script>
</body>
</html>
