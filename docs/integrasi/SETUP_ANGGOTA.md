# Menjalankan E-1, F-1, dan D-2 di laptop anggota

Satu perintah **composer integration** menyalakan ketiga layanan. Instalasi awal tetap perlu dilakukan sekali pada setiap laptop. Panduan ini untuk Windows/PowerShell; versi PHP, Composer, Node/npm, dan PostgreSQL mengikuti [kebutuhan proyek](../../README.md#kebutuhan-sebelum-instalasi).

## Siapkan kode terbaru

Gunakan E-1 yang sudah memuat perbaikan ini. Periksa dari folder PPL-E-1:

```powershell
composer run-script --list
```

Daftarnya harus memuat **integration** dan **integration:stop**. Perbaikan pada sesi ini masih lokal dan belum di-push; clone GitHub saja belum membawa perubahan ini. Untuk mencoba sekarang, anggota dapat memakai paket **PPL-E-1-untuk-anggota.zip** yang dibagikan bersama panduan ini. Ekstrak sehingga terbentuk folder PPL-E-1. Paket tidak menyertakan .env, vendor, node_modules, atau runtime pribadi.

Setelah perbaikan tersedia di GitHub, E-1 juga dapat diambil dari branch dev. Jika sudah mempunyai folder berisi pekerjaan sendiri, simpan pekerjaan itu sebelum mengganti kode; jangan menimpa .env yang sudah dikonfigurasi.

## Clone repo pendukung

Dari folder yang menampung PPL-E-1, jalankan:

```powershell
git clone https://github.com/Izra29/PPL_F1.git _integrasi/F1
git clone https://github.com/eldyfg/PPL-D-2.git _integrasi/D2
```

Jika kedua folder sudah ada, tidak perlu clone ulang. Struktur akhirnya:

```text
folder-kerja/
  PPL-E-1/
  _integrasi/
    F1/
    D2/
```

Versi yang diuji: F-1 **0feb37a**, D-2 **b93fc4d**. Repo F-1 menyediakan server demo Node/JWT, sedangkan D-2 backend Laravel/database. Launcher memakai implementasi resmi tersebut; kode/kontrak/seed kedua kelompok tidak ditambal.

## Pasang dependensi ketiganya

Mulai dari folder-kerja di atas, jalankan berurutan:

```powershell
cd _integrasi/F1
npm ci --ignore-scripts

cd ../D2
composer install

cd ../../PPL-E-1
composer install
npm ci
npm run build
```

## Siapkan PostgreSQL dan .env E-1

Di pgAdmin, buat database **e1_pajak** dengan owner **postgres** pada server lokal. Akun PostgreSQL yang dipakai harus boleh membuat database karena launcher menyiapkan database D-2 dan pengujian terpisah. Akun postgres lokal sudah memiliki hak tersebut.

Dari folder PPL-E-1, buat .env jika belum tersedia:

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
```

Edit .env melalui VS Code, sesuaikan:

```dotenv
APP_ENV=local
APP_URL=http://127.0.0.1:8080
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=e1_pajak
DB_USERNAME=postgres
DB_PASSWORD="password_postgresql_di_laptop_anda"
```

DB_PORT harus mengikuti instalasi PostgreSQL masing-masing, bukan otomatis port Docker 15439 pada .env.example. Password di atas adalah password akun PostgreSQL, bukan master password pgAdmin. Jangan menyalin .env, .env.integration, atau storage/app/private/integration dari laptop anggota lain.

Pada instalasi baru, isi APP_KEY, lalu buat schema E-1:

```powershell
php artisan key:generate
php artisan config:clear
php artisan migrate
```

Key cukup dibuat sekali. Untuk mode integrasi ini tidak perlu menjalankan DemoSeeder; invoice diambil melalui HTTP D-2. Data awal D-2 dipasang oleh launcher saat runtime pertama kali disiapkan. Siapkan database baru/kosong jika ingin mencoba pencatatan pertama tanpa bentrok dengan faktur yang sudah aktif.

## Jalankan ketiganya sekaligus

Masih dari folder PPL-E-1:

```powershell
composer integration
```

| Layanan | Alamat |
|---|---|
| E-1 / login | http://127.0.0.1:8080/login |
| Swagger E-1 | http://127.0.0.1:8080/docs |
| F-1 | http://127.0.0.1:4100 |
| D-2 | http://127.0.0.1:8026 |

Akun seed fiktif F-1: **super.admin**, kata sandi **Password123!**. Launcher mengaktifkan F-1/D-2 pada .env E-1, mematikan login demo E-1, dan menjalankan server di background. Tidak perlu php artisan serve atau npm run dev tambahan. Jika composer integration dipanggil lagi, layanan yang sudah aktif dipertahankan; hanya layanan yang berhenti yang dinyalakan.

Pada laptop baru, invoice seed D-2 adalah **inv-demo-sample**, tanggal **11 Oktober 2026**, masa **2026-10**. Gunakan [PDF pasangannya](bukti/2026-10-31T07-43-25-100Z/input/efaktur.pdf). Jangan menganggap ID invoice hasil generate di laptop lain otomatis ada pada database sendiri. Lihat [langkah uji kelompok](UJI_KELOMPOK.md) untuk proses Periksa PDF, Simpan, rekap, dan pembayaran.

Untuk berhenti:

```powershell
composer integration:stop
```

Untuk restart penuh:

```powershell
composer integration:stop
composer integration
```

Data PostgreSQL tetap. F-1 mengembalikan data memorinya ke seed saat prosesnya benar-benar direstart, sesuai implementasi kelompok F-1.

## Jika ada pesan kesalahan

| Pesan | Tindakan |
|---|---|
| Integrasi sudah berjalan | Status berhasil. Langsung buka alamat E-1; tidak perlu start lagi. |
| Composer tidak mengenali integration | Kode E-1 belum memuat perbaikan terbaru; gunakan paket terbaru atau branch dev setelah perubahan tersedia. |
| Folder F-1/D-2 atau dependensi belum ada | Periksa struktur folder dan langkah clone/install di atas. |
| Port dipakai proses di luar runtime | Hentikan sendiri server lama pada port tersebut, misalnya composer serve atau php artisan serve. Launcher tidak mematikan proses lain. |
| Profil/port berbeda | Jalankan composer integration:stop, kemudian composer integration. |
| Tidak dapat membuat database | Periksa PostgreSQL, DB_HOST/PORT/USERNAME/PASSWORD, dan hak CREATE DATABASE akun lokal. |

Setiap laptop memakai server, database, key, dan PID sendiri. Menjalankan proyek secara bersamaan pada laptop berbeda tidak menimbulkan bentrok port antarlaptop. Panduan ini bukan konfigurasi satu database bersama atau akses melalui jaringan kampus.
