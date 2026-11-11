# Struktur kode E-1

## Aplikasi

- `routes/api.php`: endpoint pajak, autentikasi F-1, dan katalog D-2.
- `app/Services/Tax`: pencatatan faktur, pembayaran, audit, dan rekap.
- `app/Services/Invoices`: provider referensi D-2 melalui HTTP atau mock.
- `D2InvoiceController`: daftar lengkap, daftar tanpa faktur aktif, dan detail referensi. URL API lama tetap tersedia.
- `resources/js/app.js`: autentikasi halaman dan pemuatan modul.
- `resources/js/pages`: logika login, daftar faktur, pencatatan, detail, katalog D-2, rekap, dan pembayaran.
- `resources/js/shared.js`: permintaan API, dialog, navigasi, dan pembentuk tampilan yang dipakai bersama.

## Data demo

`RestoredD2DemoSeeder` adalah satu implementasi untuk 31 referensi demo lama dari `database/data/d2-invoices.json`. Seeder mempertahankan sumber yang sudah ada. `inv-demo-004` tetap terhubung ke `prj-demo-003` dengan DPP Rp120.000.000.

`DemoSeeder` menangani demo mock legacy beserta pembayaran. Pada HTTP, perintah itu meneruskan ke `TaxInvoiceSeeder`, yang mengambil referensi provider aktif dan membuat faktur tanpa pembayaran otomatis. Keduanya memakai `App\Support\Demo\DemoFixtureWriter` untuk transaksi, PDF, validasi, dan pembersihan berkas jika gagal; keduanya tidak saling mewarisi.

Snapshot SQLite tetap ditangani `D2SourceDemoSeeder` dan `IntegrationSnapshotSeeder`. Snapshot dan katalog demo lama memiliki benturan ID, sehingga keduanya bukan dataset yang dapat saling menggantikan.

## Integrasi lokal

`Installer` mengoordinasikan checkout, dependensi, migrasi, dan seed. `RuntimeSetup` mengelola pembuatan runtime serta penulisan koneksi F-1/D-2 pada `.env`. Database, storage, dan APP_KEY utama tetap dipertahankan. Runtime baru hanya membuat database D-2 terpisah; setup tidak membuat database E-1/testing yang tidak dipakai.

`Runtime` menjadi tempat pembacaan, validasi, penyimpanan manifest, dan penyusunan environment proses anak. Skrip Bash/PowerShell tetap tersedia untuk kebutuhan proses masing-masing sistem operasi.

```bash
composer integration
composer integration run
composer integration:stop
```

## Pengujian

Jalankan Node/npm melalui nvm. Gunakan `.nvmrc` bila ada, atau versi default nvm.

```bash
composer test
npm run build
npm run test:unit
npm run test:ui
npx playwright test --config=playwright.d2-source.config.mjs
```

Browser utama memakai `Tests\Support\BrowserDemoSeeder` pada database testing khusus. Fixture mencakup pagination, pembatalan, pembayaran Draft, metadata sumber, dan PDF PPnBM. Fixture tersebut terpisah dari seeder aplikasi. Suite snapshot menggunakan database dan dataset SQLite tersendiri.

Artefak browser disimpan pada `test-results/browser` dan `test-results/d2-source`. Suite integrasi berbasis `.env.integration` berada di [arsip integrasi lama](../arsip/integrasi-lama/README.md), terpisah dari pengujian aktif.
