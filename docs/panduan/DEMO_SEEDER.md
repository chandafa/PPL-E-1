# Data demo E-1

Diperbarui 31 Oktober 2026. Lihat [README proyek](../../README.md) untuk instalasi dan [integrasi F-1/D-2](../integrasi/README.md) untuk sumber invoice HTTP resmi.

## Snapshot lokal dan seeder

Gunakan D2_DRIVER=mock dan E1_DEV_LOGIN=true pada lingkungan lokal. Jalankan:

```bash
php artisan migrate
php artisan db:seed
php artisan demo:samples
```

DatabaseSeeder hanya mengisi snapshot referensi: 3 kontrak, 4 termin, 1 parameter pajak, 1 invoice inv-demo-sample (11 Oktober 2026). Belum ada faktur/pembayaran E-1. Pilih invoice tersebut untuk mencoba Periksa PDF dan Simpan. PDF yang cocok adalah efaktur-inv-demo-sample.pdf di docs/postman/samples; manifest.json/validation.json memuat tanggal, masa pajak, nominal, hash, dan validasi keterbacaan.

Untuk dataset E-1 lengkap, jalankan terpisah:

```bash
php artisan db:seed --class=DemoSeeder
php artisan demo:samples
```

DemoSeeder memanggil ModulD2DemoSeeder untuk menambah 31 invoice sumber **simulasi E-1** pada tabel lokal, lalu membuat 32 faktur dan 5 pembayaran. Ini bukan penambahan data ke layanan D-2 HTTP. Invoice 006/007 tidak ditanam oleh versi seeder ini; jangan memakai ID atau PDF yang tidak ada di manifest. Seluruh 32 invoice yang ditanam sudah mempunyai faktur aktif. Gunakan koreksi/pengganti, atau database baru dengan seed dasar tanpa DemoSeeder untuk mencoba pencatatan baru.

Seeder aditif mempertahankan faktur yang sudah dicatat/diubah dan billing yang sudah ada; tidak mereset transaksi. Dataset dasar baru berbeda dari database lama yang telah dikoreksi pengguna. DemoSeeder hanya berjalan di luar production dan D2_DRIVER=mock.

## Rekap dataset baru

| Masa | Faktur | Total PPN | Jumlah setor | Selisih | Status |
|---|---:|---:|---:|---:|---|
| 2026-10 | 25 | 16940000.00 | 16940000.00 | 0.00 | CONFIRMED |
| 2026-11 | 3 | 46750000.00 | 46750000.00 | 0.00 | CONFIRMED |
| 2026-12 | 2 | 19800000.00 | 19800000.00 | 0.00 | CONFIRMED |
| 2027-01 | 1 | 1100000.00 | 1100000.00 | 0.00 | CONFIRMED |
| 2027-02 | 1 | 1100000.00 | 1100000.00 | 0.00 | CONFIRMED |

Saat ini semua contoh dari invoice jasa memakai PPnBM 0.00 dan satu item jasa per invoice. Tidak ada pembatalan, pengganti, potongan/uang muka, atau pembayaran Draft yang ditanam otomatis pada dataset ini. Variasi tersebut diuji oleh tes/API, bukan diklaim tersedia pada seed awal. Skenario barang mewah merupakan fixture pengujian terpisah; lihat [panduan PPN/PPnBM](PPN_DAN_PPNBM.md).

## PDF dan pembayaran

demo:samples membaca tabel invoice snapshot saat itu. Tanggal e-Faktur, masa pajak, dan tahun NSFP mengikuti invoice, bukan jam komputer. PDF pengganti memakai NSFP berbeda. Lampiran e-Billing/bukti mengikuti masa pajak dan tanggal contoh bulan berikutnya. Setiap PDF dibaca ulang sebelum dipublikasikan. Pada seed dasar satu invoice terdapat 4 PDF (dua e-Faktur dan dua lampiran); setelah DemoSeeder jumlahnya mengikuti 32 invoice dan lima masa pajak.

Data PDF tidak membuktikan keaslian DJP atau pembayaran bank. Server memeriksa struktur dokumen, menyimpan nama privat buatan server, dan tetap mencatat riwayat perubahan. Untuk data HTTP resmi gunakan panduan integrasi; demo:samples tidak mengunduh katalog D-2 yang belum diterbitkan.
