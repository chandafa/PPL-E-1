# Setup integrasi E-1, F-1, dan D-2

Jalankan semua perintah dari folder `PPL-E-1`. Setup dan menjalankan layanan menggunakan dua perintah terpisah.

## Prasyarat

Pasang PHP dan ekstensi sesuai `composer.json`, Composer, Git, PostgreSQL, serta Node.js/npm melalui nvm. Linux memerlukan Bash, `setsid`/`nohup` dan PHP `posix`. Windows memakai PowerShell dan nvm-windows; aktifkan versi Node melalui nvm sebelum setup. Ikuti `.nvmrc` bila tersedia.

Pasang dependensi E-1 dengan `composer install`. Untuk build antarmuka, gunakan Node melalui nvm lalu `npm ci` dan `npm run build`. Persiapkan database utama E-1 dan `.env`:

```dotenv
APP_ENV=local
APP_URL=http://127.0.0.1:8087
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=e1_pajak
DB_USERNAME=postgres
DB_PASSWORD="password_postgresql_lokal"
```

Salin `.env.example` hanya jika `.env` belum ada, sesuaikan koneksi, lalu jalankan `php artisan key:generate` sekali. Akun PostgreSQL harus memiliki izin CREATE DATABASE. Jangan menyalin `.env`, `.env.integration`, atau direktori runtime dari laptop lain.

## Setup

```bash
composer integration
```

Perintah ini:

1. Clone branch `main` F-1 ke `../_integrasi/F1` dan branch `main` D-2 ke `../_integrasi/D2`, jika belum ada. Clone baru mengikuti `main` saat itu tanpa patokan commit.
2. Pasang dependensi F-1/D-2 sesuai lockfile.
3. Siapkan profil lokal dan database D-2 terpisah; database utama E-1 tetap dari `.env`.
4. Migrasi E-1/D-2 tanpa reset, seed referensi contoh E-1 dan seed resmi D-2. F-1 menggunakan seed JSON saat server dimulai.
5. Simpan status setup dan selesai tanpa menyalakan layanan.

Checkout terkelola harus berada pada branch `main`; SHA hanya dicatat sebagai informasi versi yang digunakan. Checkout lama dengan detached HEAD atau branch lain ditolak tanpa diubah. Pindahkan checkout lama secara manual atau tentukan `F1_PATH` dan `D2_PATH` secara eksplisit untuk memakai checkout sendiri. Setup tidak melakukan pull/reset pada checkout yang sudah ada. Lokasi override disimpan, sehingga tidak perlu ditentukan lagi saat `run`.

Setup dan `run` memeriksa data seed setiap kali. Baris yang hilang dipulihkan meskipun penanda setup masih ada, sedangkan nilai dan status baris lama dipertahankan dalam transaksi. Konflik seed E-1 menampilkan tabel/ID terkait dan membatalkan transaksi. Periksa data konflik; jangan menghapus database untuk melewati kesalahan tersebut.

## Jalankan dan hentikan layanan

```bash
composer integration run
```

| Layanan | Alamat |
|---|---|
| E-1 / login | http://127.0.0.1:8087/login |
| Swagger E-1 | http://127.0.0.1:8087/docs |
| F-1 | http://127.0.0.1:4100 |
| D-2 | http://127.0.0.1:8026 |

Login fiktif F-1: **super.admin** / **Password123!**. Invoice contoh HTTP D-2: **inv-demo-sample**. Runtime lokal membaca ID invoice langsung dari database D-2 secara read-only, lalu memverifikasi detail dan nilai komersial melalui HTTP. Invoice tampil pada halaman Invoice D-2 dan pilihan pada form Catat e-Faktur.

`run` memeriksa migrasi dan memulihkan baris seed yang hilang sebelum menyalakan layanan dari setup yang lengkap. Setelah layanan siap, `TaxInvoiceSeeder` membuat faktur demo untuk invoice sumber yang belum mempunyai faktur. PDF, item, audit, dan rekap dibuat melalui layanan E-1; pembayaran tetap dicatat manual. Perintah ini tidak melakukan clone, instalasi, atau reset database. Pemanggilan ulang mempertahankan proses sehat dan menyalakan layanan yang berhenti. Untuk berhenti atau restart:

```bash
composer integration:stop
composer integration run
```

Database dan faktur yang sudah ada dipertahankan; faktur yang dibatalkan tidak dibuat ulang oleh seeder. Faktur demo tampil pada halaman Faktur Pajak. Form Catat e-Faktur hanya menawarkan invoice yang belum memiliki faktur aktif, sehingga invoice yang baru di-seed tidak lagi muncul di pilihan tersebut. F-1 memuat ulang seed JSON setiap prosesnya dimulai ulang. Sebelum mengulang setup atau mengganti mode, hentikan layanan terlebih dahulu.

## Mode dummy opsional

```bash
composer integration -- --d2=mock
composer integration run -- --d2=mock
```

Mode ini memakai F-1 serta dua invoice snapshot SQLite di database E-1, tanpa server D-2. PDF pasangan tersedia di `storage/app/private/d2-source-samples`. Ikuti [panduan dummy](../panduan/D2_SOURCE_DEMO.md) dan [langkah uji kelompok](UJI_KELOMPOK.md).

## Pemulihan

| Pesan/kondisi | Tindakan |
|---|---|
| Setup belum lengkap / konfigurasi berubah | Hentikan layanan, lalu ulang `composer integration`. |
| Port terpakai | Gunakan `composer integration:stop` untuk proses runtime ini; tangani sendiri proses lain. |
| Clone/dependensi gagal | Periksa koneksi dan prasyarat, lalu ulang setup. |
| Tahap database gagal | Periksa log privat `storage/logs/integration-setup.log` pada E-1, konfigurasi PostgreSQL, dan izin CREATE DATABASE. |
| Checkout bukan branch main | Pindahkan checkout lama atau gunakan override path secara eksplisit. |
| Konflik seed | Periksa tabel/ID yang disebutkan; data lama tidak ditimpa. |

Alias Artisan tersedia: `php artisan integration`, `php artisan integration run`, dan `php artisan integration:stop`. Konfigurasi privat tersimpan di `storage/app/private/integration`; jangan ikut memasukkannya ke Git atau bukti pengujian.
