# E-1 — Pencatatan e-Faktur dan Pajak Proyek

<p align="center">
  <img src="docs/assets/thumbnail.svg" alt="E-1 Pajak Proyek" width="100%">
</p>

Aplikasi web dan REST API untuk mencatat PDF e-Faktur keluaran, menghubungkannya dengan invoice proyek, serta mendokumentasikan rekap dan pembayaran pajak.

**Capstone Proyek Perangkat Lunak · Kelas E · Kelompok 1**

ERP PT Anaheim Nimbus Universal · Laravel 13 · Blade · Tailwind CSS · PostgreSQL

## Kebutuhan sebelum instalasi

Pasang perangkat berikut terlebih dahulu. Untuk Composer, pasang PHP lebih dulu agar installer dapat menemukan `php.exe`.

| Perangkat | Versi yang digunakan | Kegunaan | Unduhan resmi |
|---|---|---|---|
| **PHP** | 8.4 atau 8.5 | Menjalankan Laravel dan perintah `php artisan` | [PHP](https://www.php.net/downloads.php) · [PHP Windows](https://windows.php.net/download/) |
| **Composer** | 2.x | Memasang dependensi PHP proyek | [Download Composer](https://getcomposer.org/download/) |
| **Node.js dan npm** | Disarankan Node.js **24 LTS**; Node 22 minimal 22.12 juga didukung | Memasang dependensi frontend dan membangun aset. npm sudah disertakan pada installer Node.js | [Download Node.js + npm](https://nodejs.org/en/download) |
| **PostgreSQL** | 16 atau lebih baru; demo diuji pada 18 | Menyimpan data aplikasi | [Download PostgreSQL](https://www.postgresql.org/download/) |
| **pgAdmin 4** | Versi yang mendukung PostgreSQL terpasang | Membuat dan melihat database melalui antarmuka grafis | [Download pgAdmin](https://www.pgadmin.org/download/) |
| **Git** | Versi stabil | Mengambil kode dari repositori | [Download Git](https://git-scm.com/downloads/) |

Gunakan Node.js/npm melalui **nvm** pada Linux atau **nvm-windows** pada Windows. Aktifkan versi Node sebelum menjalankan perintah npm dan ikuti `.nvmrc` bila tersedia. Pada Linux, integrasi juga memerlukan Bash, `setsid`, `nohup`, dan ekstensi PHP `posix`; pada Windows, integrasi memakai PowerShell.

Saat memasang PostgreSQL di Windows, pilih **PostgreSQL Server** dan **pgAdmin 4**. Catat password akun `postgres` serta port yang dipilih; keduanya dipakai saat mengisi `.env`.

PHP memerlukan ekstensi `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `gd`, `mbstring`, `openssl`, `pdo`, `pdo_pgsql`, `session`, `tokenizer`, `xml`, `xmlreader`, `xmlwriter`, dan `zip`. Jika Composer menyebut ekstensi belum tersedia, aktifkan ekstensi tersebut pada `php.ini` yang digunakan PHP CLI. Lokasinya dapat dilihat dengan `php --ini`.

Setelah instalasi perangkat selesai, buka ulang terminal dan pastikan perintah `php`, `composer`, `node`, dan `npm` sudah dikenali.

## Instalasi proyek

Semua perintah berikut dijalankan dari folder **PPL-E-1**. Di VS Code, buka folder proyek lalu pilih **Terminal → New Terminal**.

### 1. Ambil kode proyek

```bash
git clone https://github.com/chandafa/PPL-E-1.git
cd PPL-E-1
```

Jika folder proyek sudah tersedia, cukup buka folder tersebut.

### 2. Pasang dependensi dan build tampilan

```bash
composer install
npm ci
npm run build
```

`composer install` memasang paket Laravel/PHP E-1. Jalankan npm melalui nvm: `npm ci` memasang paket frontend sesuai lockfile, lalu `npm run build` menyiapkan CSS serta JavaScript. Dependensi repo F-1 dan D-2 akan dipasang otomatis oleh `composer integration`.

### 3. Buat database melalui pgAdmin

Langkah ini dilakukan melalui tampilan pgAdmin, tanpa perintah SQL di terminal:

1. Buka **pgAdmin 4** dan hubungkan ke server PostgreSQL lokal. Gunakan akun `postgres` serta password yang ditentukan saat instalasi PostgreSQL.
2. Pada panel kiri, buka **Servers → server PostgreSQL Anda**.
3. Klik kanan **Databases → Create → Database…**.
4. Isi **Database** dengan `e1_pajak` dan **Owner** dengan `postgres`.
5. Klik **Save**. Database `e1_pajak` sekarang tersedia; tabelnya akan dibuat otomatis saat menjalankan `composer integration`.

Jika database tersebut sudah ada, gunakan database yang tersedia. Panduan menu tersedia pada [dokumentasi pgAdmin](https://www.pgadmin.org/docs/pgadmin4/latest/database_dialog.html).

### 4. Siapkan konfigurasi .env

Di Explorer VS Code, salin `.env.example`, lalu ubah nama salinannya menjadi `.env`. Jika `.env` sudah ada, edit file tersebut.

Isi atau sesuaikan baris berikut:

```dotenv
# Aplikasi lokal
APP_NAME="E-1 Pajak Proyek"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8087

# Database PostgreSQL yang dibuat melalui pgAdmin
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=e1_pajak
DB_USERNAME=postgres
DB_PASSWORD="password_postgresql_anda"

```

**Ganti `password_postgresql_anda` dengan password akun PostgreSQL Anda.** Contoh ini menggunakan akun `postgres` untuk menjalankan demo lokal di laptop masing-masing. Jika memakai akun lain, sesuaikan `DB_USERNAME` dan pemilik database.

| Pengaturan | Isi untuk contoh ini | Penjelasan |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | PostgreSQL berjalan di laptop sendiri |
| `DB_PORT` | `5432` | Sesuaikan dengan port yang dipilih saat instalasi PostgreSQL |
| `DB_DATABASE` | `e1_pajak` | Harus sama dengan nama database di pgAdmin |
| `DB_USERNAME` | `postgres` | Akun yang mempunyai akses ke database dan izin CREATE DATABASE untuk D-2 |
| `DB_PASSWORD` | Password PostgreSQL Anda | Password akun database, bukan master password pgAdmin |

**Perhatikan port:** `.env.example` berisi port `15439` untuk konfigurasi Docker. Pada panduan PostgreSQL lokal melalui pgAdmin ini, ubah menjadi `5432` atau port instalasi Anda.

Biarkan konfigurasi lainnya mengikuti `.env.example`. `composer integration` akan mengatur koneksi F-1/D-2 dan mematikan login tiruan E-1. Nilai `APP_KEY` disiapkan pada langkah berikutnya.

### 5. Siapkan key aplikasi

Untuk instalasi baru dengan `APP_KEY` yang belum terisi:

```bash
php artisan key:generate
```

Jika `APP_KEY` sudah tersedia, lanjutkan ke setup integrasi.

### 6. Setup dan jalankan integrasi

```bash
# Siapkan repo F-1/D-2, dependensi, konfigurasi, migrasi, dan seed
composer integration

# Jalankan F-1, D-2, dan E-1; buat faktur demo dari invoice sumber
composer integration run
```

Setup memerlukan port **4100, 8026, dan 8087** yang kosong. E-1 memakai database utama pada `.env`; D-2 memakai database terpisah yang dibuat otomatis. Migrasi dan seed dijalankan oleh integrasi, sehingga tidak perlu dijalankan manual.

Setelah layanan siap, buka:

| Halaman | Alamat |
|---|---|
| **Login aplikasi** | http://127.0.0.1:8087/login |
| **Swagger UI** | http://127.0.0.1:8087/docs |
| **Kontrak OpenAPI** | http://127.0.0.1:8087/docs/openapi.yaml |

Login menggunakan akun demo F-1: **`super.admin` / `Password123!`**. Faktur demo beserta PDF dibuat setelah layanan berjalan; pembayaran dicatat melalui aplikasi.

### 7. Pemakaian berikutnya dan menghentikan layanan

```bash
# Jalankan kembali integrasi yang sudah disiapkan
composer integration run

# Hentikan layanan integrasi
composer integration:stop
```

Data yang sudah tersimpan dipertahankan. Jika konfigurasi koneksi berubah, hentikan layanan lalu ulangi `composer integration` sebelum menjalankan `composer integration run`.

### Alternatif: integrasi dengan D-2 mock

```bash
composer integration:stop
composer integration -- --d2=mock
composer integration run -- --d2=mock
```

Mode ini menjalankan F-1 pada **4100** dan E-1 pada **8087**, dengan referensi snapshot D-2 lokal tanpa server D-2. Gunakan mode yang sama pada setup dan `run`.

Panduan tambahan: [setup anggota](docs/integrasi/SETUP_ANGGOTA.md), [data demo](docs/panduan/DEMO_SEEDER.md), dan [snapshot D-2](docs/panduan/D2_SOURCE_DEMO.md).

## Alur proses

```mermaid
flowchart TD
    A["Masuk sebagai Admin, CEO, atau COO"] --> B["Pilih invoice proyek D-2"]
    B --> C["Unggah PDF e-Faktur dan klik Periksa PDF"]
    C --> D{"PDF dan invoice valid?"}
    D -- "Belum" --> E["Perbaiki pilihan invoice atau PDF"]
    E --> B
    D -- "Ya" --> F["Periksa hasil ekstraksi lalu simpan faktur"]
    F --> G["Lihat rekap berdasarkan masa pajak"]
    G --> H["Catat pembayaran dan unggah e-Billing"]
    H --> I{"NTPN dan bukti pembayaran lengkap?"}
    I -- "Belum" --> J["Simpan sebagai Draft / Belum Konfirmasi"]
    J --> K["Lengkapi NTPN dan PDF bukti pembayaran"]
    K --> L["Catatan pembayaran Terkonfirmasi"]
    I -- "Ya" --> L
    L --> M["Rekap diperbarui"]
    G --> N["Ekspor rekap ke Excel atau PDF"]
    G --> O["Ringkasan pajak untuk E-2"]
```

Pratinjau PDF belum menyimpan faktur. Koreksi, pembatalan, dan pencatatan pengganti dilakukan melalui detail faktur; setiap perubahan mempunyai riwayat. Masa pajak pada sampel mengikuti bulan e-Faktur, sedangkan tanggal pembayaran contoh berada pada bulan berikutnya.

## Daftar 11 API

| ID | Metode | Endpoint | Fungsi |
|---|---|---|---|
| API-KE01-01 | `POST` | `/api/v1/tax-invoices/extractions` | Periksa PDF dan tampilkan pratinjau tanpa menyimpan |
| API-KE01-02 | `POST` | `/api/v1/tax-invoices/outgoing` | Simpan faktur keluaran atau pengganti |
| API-KE01-03 | `GET` | `/api/v1/tax-invoices` | Cari, filter, dan tampilkan daftar faktur |
| API-KE01-04 | `GET` | `/api/v1/tax-invoices/{taxInvoiceId}` | Tampilkan detail faktur |
| API-KE01-05 | `PATCH` | `/api/v1/tax-invoices/{taxInvoiceId}` | Koreksi atau batalkan faktur |
| API-KE01-06 | `GET` | `/api/v1/tax-invoices/{taxInvoiceId}/history` | Tampilkan riwayat perubahan |
| API-KE01-07 | `GET` | `/api/v1/tax-recaps/{taxPeriod}` | Tampilkan rekap masa pajak |
| API-KE01-08 | `POST` | `/api/v1/tax-payments` | Catat pembayaran Draft atau Terkonfirmasi |
| API-KE01-09 | `GET` | `/api/v1/tax-recaps/{taxPeriod}/export` | Unduh rekap Excel atau PDF |
| API-KE01-10 | `GET` | `/api/v1/tax/summary` | Baca ringkasan pajak untuk modul lain |
| API-KE01-11 | `PATCH` | `/api/v1/tax-payments/{taxPaymentId}` | Lengkapi NTPN dan bukti pembayaran |

API-09 memerlukan query `format=xlsx` atau `format=pdf`. API-10 memerlukan `taxPeriod=YYYY-MM`; `projectId` bersifat opsional.

Buka **Swagger UI** untuk melihat parameter, mengunggah PDF, dan mencoba API. Bentuk respons, kode kesalahan, serta langkah pengujian lengkap tersedia dalam dokumentasi berikut.

## Dokumentasi lanjutan

| Kebutuhan | Dokumen |
|---|---|
| Memahami halaman dan penggunaan website | [Panduan UI](docs/panduan/UI_BLADE.md) |
| Menggunakan seeder dan pasangan invoice/PDF | [Panduan data demo](docs/panduan/DEMO_SEEDER.md) |
| Menguji API dengan koleksi Postman | [Panduan Postman](docs/api/POSTMAN.md) |
| Melihat respons berhasil dan gagal setiap API | [Referensi respons API](docs/api/RESPON_API.md) |
| Membaca kontrak API | [OpenAPI](openapi.yaml) |
| Melihat seluruh dokumen dan acuan tugas | [Folder dokumentasi](docs/README.md) |

## Tim

**Kelas E · Kelompok 1**

| Anggota |
|---|
| Raden Indra Prawirajaya |
| Aidil |
| Candra |
| Ismail Abdul Fathan |
| Raihan Azzani |

Dependensi: **F-1** untuk identitas dan hak akses, **D-2** untuk invoice proyek. Konsumen ringkasan pajak: **E-2**. `composer integration` menyiapkan kedua repo, migrasi, dan seed; `composer integration run` menjalankan F-1 dan D-2 melalui HTTP bersama E-1. Mode dummy tersedia dengan `--d2=mock`.
