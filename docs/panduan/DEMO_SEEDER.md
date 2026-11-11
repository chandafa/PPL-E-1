# Data demo E-1

## Pemulihan invoice demo dari riwayat commit

Dataset `database/data/d2-invoices.json` dari commit `fa49be3` memuat `inv-demo-004` → `prj-demo-003`, nomor `INV-2026-0004`, tanggal 2 Desember 2026, DPP Rp120.000.000, PPN Rp13.200.000, PPh Rp2.400.000, dan total Rp130.800.000.

`RestoredD2DemoSeeder` memulihkan 31 referensi demo (`001`–`005` dan `010`–`035`). Setup dan perbaikan integrasi HTTP menjalankannya di database D-2 melalui `scripts/seed-integration-d2.php`. Setelah layanan siap, `TaxInvoiceSeeder` membuat faktur E-1 beserta PDF dari sumber tersebut. Jalankan `composer integration run` pada instalasi yang sudah disiapkan untuk memperbaiki data dan menanam faktur yang belum ada.

Untuk mengulang pencatatan faktur E-1 ketika layanan berjalan, perintah lama juga tersedia:

```bash
php artisan db:seed --class=DemoSeeder
```

Pada HTTP, perintah tersebut meneruskan ke `TaxInvoiceSeeder`. Pemulihan menambahkan invoice yang hilang, mempertahankan invoice serta faktur yang sudah ada, dan melewati nomor invoice yang sudah digunakan ID lain. Kontrak, termin, dan parameter pajak pemulihan memakai ID tersendiri agar tidak mengubah sumber lama. Benturan `inv-demo-001` dengan snapshot SQLite tetap dipertahankan sesuai sumber yang sudah tersimpan.

Untuk database mock legacy terpisah, jalankan `D2_DRIVER=mock D2_MOCK_DATASET=legacy php artisan db:seed --class=RestoredD2DemoSeeder`, kemudian `D2_DRIVER=mock D2_MOCK_DATASET=legacy php artisan db:seed --class=TaxInvoiceSeeder`. Snapshot SQLite tetap menyediakan katalog aslinya; gunakan HTTP atau database legacy untuk seluruh katalog demo lama.

## Faktur pajak dari sumber D-2 saat ini

Seeder faktur `DemoSeeder` tersedia untuk fixture legacy dan meneruskan mode HTTP ke `TaxInvoiceSeeder`. Kedua seeder memakai `App\Support\Demo\DemoFixtureWriter` untuk pembuat PDF, validasi parser, pencatatan faktur, audit, dan rekap untuk sumber D-2 yang sekarang.

Pada integrasi lokal, jalankan:

```bash
composer integration
composer integration run
```

Setup memasang referensi D-2. Setelah F-1, D-2, dan E-1 siap, `run` menjalankan `TaxInvoiceSeeder` secara otomatis. Seeder login ke F-1 menggunakan akun demo lokal, membaca invoice dari D-2, lalu membuat faktur demo yang tanggal, DPP, PPN, dan proyeknya mengikuti sumber. Nomor faktur serta PDF adalah data uji E-1; rincian item uji tidak diklaim sebagai rincian komersial D-2. Pembayaran tidak dibuat otomatis.

Untuk menjalankan ulang seeder saja saat layanan sudah aktif:

```bash
php artisan db:seed --class=TaxInvoiceSeeder
```

Akun demo HTTP bawaan adalah `super.admin` / `Password123!`. Jika akun demo F-1 diubah, sesuaikan `E1_DEMO_F1_USERNAME` dan `E1_DEMO_F1_PASSWORD` pada `.env`. Login hanya dikirim ke runtime lokal F-1 pada 4100 dan D-2 pada 8026. Seeder ini ditolak di luar local/testing.

Pada mode mock, seeder mengambil invoice dari dataset mock yang sedang aktif, tanpa menambahkan fixture legacy. Faktur yang sudah ada dilewati, termasuk faktur yang dibatalkan pengguna. Menjalankan seeder ulang tidak mengubah nominal, status, audit, pembayaran, atau berkas faktur lama. Faktur baru terlihat pada halaman Faktur Pajak; invoice yang sudah memiliki faktur aktif tidak ditawarkan lagi pada form pencatatan.

## Referensi SQLite dan dataset legacy

Dataset utama D-2 mengikuti `database.sqlite`: tiga kontrak, empat termin, satu parameter pajak, dua invoice dan dua riwayat penerbitan. Gunakan `D2_DRIVER=mock`, `D2_MOCK_DATASET=sqlite`, lalu jalankan `php artisan demo:d2`. Panduan [snapshot SQLite D-2](D2_SOURCE_DEMO.md) memuat isi sumber, API E-1, PDF pasangan, dan langkah pencatatan serta pembayaran.

`php artisan db:seed` pada mode snapshot SQLite hanya menanam data invoice referensi D-2, termasuk `inv-demo-sample` untuk `prj-demo-001` bertanggal 1 November 2026, masa pajak 2026-11, DPP Rp100.000.000, PPN Rp11.000.000, PPh Rp2.000.000. Tidak ada e-Faktur atau pembayaran yang dibuat; proses pencatatan dilakukan terpisah melalui aplikasi.

Invoice `inv-demo-001` tetap berada pada 28 Oktober 2026 dengan PPN Rp16.500.000. `e-billing-2026-11-001.pdf` adalah nama untuk dokumen contoh yang dapat dibuat manual, bukan pembayaran yang otomatis disimpan oleh seeder.

Dataset demo lama dengan 32 faktur dan 5 pembayaran tetap merupakan fixture legacy untuk database terpisah. Untuk menggunakannya, pilih `D2_MOCK_DATASET=legacy` pada database kosong yang khusus untuk legacy, jalankan migrasi dan seed dasar, kemudian `php artisan db:seed --class=DemoSeeder` serta `php artisan demo:samples`. Jangan menjalankan fixture legacy pada database snapshot SQLite; ID `inv-demo-001` berbenturan dan seeder menolak penimpaan data canonical.

Data buatan `inv-d2-local-*` dari versi sebelumnya tidak dihapus, tetapi dipisahkan dari katalog snapshot aktif. Dokumen pengujian tetap fiktif dan bukan bukti transaksi/DJP nyata.
