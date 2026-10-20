# Antarmuka E-1 dengan Blade

## Menjalankan

Siapkan PostgreSQL dan konfigurasi `.env` sesuai README. Build UI memerlukan Node.js
22.12 atau lebih baru dan npm. Jalankan:

```bash
composer install
npm ci
npm run build
php artisan migrate
composer serve
```

Buka `http://127.0.0.1:8080/login`. Login demo lokal hanya muncul ketika
`E1_DEV_LOGIN=true` dan lingkungan bukan production. Halaman masuk hanya menampilkan
form akun demo dengan ID akun dan peran. Token hasil login disimpan di `sessionStorage`, dikirim melalui Bearer, dan dihapus
ketika keluar atau API menjawab 401. Otorisasi data tetap dilakukan oleh API.

`npm run dev` menyediakan aset Vite selama pengembangan. `composer serve` menggunakan
`server.php` agar aset statis dilayani langsung dengan batas unggah PDF yang sesuai.

Untuk data demo lengkap, jalankan `php artisan db:seed` dan `php artisan demo:samples`.
Seeder menambahkan data tanpa mereset perubahan pengguna. Dataset awal berisi 32 faktur,
5 pembayaran, dan 5 masa pajak. Petunjuk PDF siap pakai dan skenario tersedia di
[Panduan Seeder Demo](DEMO_SEEDER.md).

## Layar dan route

| Layar | Route GET | Operasi API |
|---|---|---|
| Masuk | `/login` | Login demo dan pemeriksaan akses API |
| Daftar faktur | `/tax-invoices` | API-KE01-03 |
| Pencatatan | `/tax-invoices/create` | API-KE01-01, API-KE01-02 |
| Detail, koreksi, pembatalan, riwayat | `/tax-invoices/{taxInvoiceId}` | API-KE01-04, API-KE01-05, API-KE01-06 |
| Rekap | `/tax-recaps/{taxPeriod}` | API-KE01-07 dan API-KE01-03 |
| Pembayaran | `/tax-recaps/{taxPeriod}/payments/create` | API-KE01-07, API-KE01-08, API-KE01-11 |
| Ekspor | Dialog pada rekap | API-KE01-09 |

`/` menuju daftar faktur; `/tax-recaps` menuju bulan berjalan di Asia/Jakarta.
Pengganti memakai query `replacesTaxInvoiceId` pada pencatatan. Pelengkapan pembayaran
memakai query `paymentId` pada pembayaran. Koreksi dan pembatalan memakai dialog detail.
Filter daftar dipertahankan dalam query string.

Dropdown masa pajak rekap hanya memuat periode yang memiliki faktur. URL periode
valid yang belum memiliki data tetap menampilkan rekap kosong dan total nol tanpa
pengalihan atau pembuatan data. Jika ada periode lain, dropdown menyediakan pilihan
periode tersebut; jika belum ada periode sama sekali, dropdown dan tombol tampilkan
dinonaktifkan. Lihat [adendum spesifikasi dan kontrak demo](../acuan/ADENDUM_DEMO_E1_v0.3.md).

## Aturan angka dan kompatibilitas

Sesuai pilihan implementasi berdasarkan PDF v0.2, Normal dan Pengganti keluaran
yang belum terganti dihitung. Dibatalkan dan faktur asal terganti tidak dihitung.
Aturan ini menggantikan pengecualian Pengganti pada kontrak audit 2026-10-16.
Rekap, ekspor, selisih pembayaran, dan ringkasan E-2 memakai aturan yang sama.
Nominal API tetap string desimal; format rupiah hanya untuk tampilan.

Tampilan masa pajak memakai nama bulan lengkap, misalnya `Januari 2026`.
Tanggal kalender memakai format `10 Januari 2026`. Waktu kejadian dikonversi ke
Asia/Jakarta dan ditulis `10 Januari 2026, 10.15 WIB`. Format ini digunakan pada
daftar, detail, pratinjau, rekap, pembayaran, riwayat, serta ekspor PDF dan Excel.
Nilai form tanggal/bulan yang dapat diedit, URL, JSON API, dan snapshot audit tetap
memakai format kontrak (`YYYY-MM`, `YYYY-MM-DD`, atau timestamp UTC). Nama berkas
ekspor tetap memakai periode `YYYY-MM` agar konsisten dengan endpoint API.

Sesudah memperbarui aplikasi dengan data lama, jalankan `php artisan e1:recaps:rebuild`.
Perintah mengambil gabungan periode faktur, pembayaran, dan rekap, kemudian menghitung
ulang dalam transaksi. Perintah dapat dijalankan kembali tanpa menggandakan rekap.

Tidak ada endpoint tambahan atau perubahan bentuk respons. Komponen shadcn/ui diadaptasi
menjadi Blade dengan Tailwind, tanpa React/Inertia. Template ekspor PDF tetap menggunakan
styling renderer PDF yang sudah ada.

## Batasan integrasi

- Saat `D2_DRIVER=mock`, form pencatatan memuat dropdown dari data dummy D-2 melalui
  `GET /api/ui/d2/invoices` (memerlukan token F-1). Ringkasan nomor, tanggal, proyek,
  klien, status, DPP, dan PPN mengikuti pilihan invoice. Pencarian nomor/ID invoice,
  proyek, dan klien menyatu dengan dropdown dan mendukung tombol panah, Enter,
  serta Escape. Teks pencarian harus dipilih dari hasil sebelum PDF dapat diperiksa.
  Katalog hanya menampilkan invoice tanpa faktur keluaran aktif menggunakan scope
  `FakturPajak::active()`, sama dengan aturan penyimpanan. Faktur pengganti tetap
  memakai invoice asal yang terkunci. Pembatalan pengganti mengaktifkan kembali
  faktur asal; invoice baru muncul kembali bila tidak ada faktur aktif tersisa.
  Endpoint pendukung UI ini
  berada di luar 11 API kontrak E-1; driver HTTP tetap memakai ID invoice manual.
- Proyek dan klien tampil sebagai ID; nilai kosong diberi label Belum tersedia.
- Ringkasan berasal dari `database/data/d2-invoices.json` dan ditandai sebagai data
  dummy. Data yang kosong ditampilkan sebagai belum tersedia. Nilai faktur tetap
  dibaca dari PDF, bukan disalin dari nilai invoice D-2.
- Pengganti langsung memakai penyimpanan karena ekstraksi menolak invoice dengan faktur aktif.
- Referensi PDF tersimpan ditampilkan sebagai teks; API akses berkas belum tersedia.
- Periode tanpa faktur menampilkan total nol dan menonaktifkan pencatatan pembayaran.

## Antislop dan keputusan desain

Antislop diterapkan dalam mode During, bersama UI, Copywriting, Human, dan Layoutmobile.
Acuan visual adalah wireframe PDF: aplikasi administrasi pajak untuk Admin, CEO, dan COO.
Dial: ENERGY 1 / RHYTHM 1 / MOTION 1.

| Keputusan | Alasan |
|---|---|
| Tema terang, aksen navy | Mempertahankan arah PDF dan memudahkan pencocokan nominal dengan dokumen cetak. |
| Tabel dominan pada daftar | Pencarian dan perbandingan NSFP, invoice, periode, serta nominal merupakan pekerjaan utama. |
| Ringkasan hanya pada rekap | Total dipakai untuk memeriksa pembayaran dan selisih periode. |
| Font sistem sans-serif, angka tabular | Teks tetap terbaca dan digit nominal sejajar tanpa mengunduh font. |
| Card mengelompokkan input | Invoice, berkas, dan hasil ekstraksi mempunyai tahap kerja yang berbeda. |
| Badge status dengan teks | Normal, Pengganti, Dibatalkan, dan kelengkapan pembayaran terbaca tanpa bergantung pada warna. |
| Solar Linear | Ikon yang diminta pengguna membantu mengenali aksi dokumen, pencarian, pembayaran, dan ekspor. |
| Bayangan pada dialog | Membedakan lapisan konfirmasi dari data halaman. |
| Hover dan fokus singkat | Menjelaskan interaksi tanpa animasi dekoratif yang mengganggu pemeriksaan data. |

## Pengujian

```bash
php artisan test
npm run test:unit
npm run build
npx playwright install chromium
npm run test:ui
```

PHPUnit menggunakan database PostgreSQL `e1_pajak_test`. Browser menggunakan database
terpisah `e1_pajak_ui_test`, yang harus dibuat dahulu dengan pemilik sesuai `.env`:

```sql
CREATE DATABASE e1_pajak_ui_test OWNER e1_app;
```

Database browser dapat diganti lewat `E1_UI_TEST_DATABASE`; buat database itu dahulu.
Di Windows, contoh memakai Chrome yang sudah terpasang:

```powershell
$env:PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH='C:\Program Files\Google\Chrome\Application\chrome.exe'
npm.cmd run test:ui
```

Screenshot pengujian memakai folder keluaran Playwright sehingga dapat dijalankan
di Windows maupun Linux. Pengujian PDF memeriksa tanggal invoice dan hasil tombol
Periksa PDF menggunakan server nyata, termasuk tampilan mobile.
Lingkungan testing memakai aset hasil `npm run build`; server Vite pengembangan
yang sedang berjalan tidak memicu reload pada halaman uji.

Browser tests menjalankan server sendiri pada port 8081 dan **mengosongkan serta mengisi
ulang hanya database `e1_pajak_ui_test`**. Port 8080 dan database operasional tidak digunakan
oleh konfigurasi browser default. `E1_UI_BASE_URL` dapat menunjuk server uji yang sudah
disiapkan; dalam mode itu reset database dilewati dan penguji harus menyediakan data demo
yang dapat diubah. Jangan arahkan browser tests ke data operasional.

Hasil browser, trace kegagalan, dan screenshot tersedia di `test-results/` (diabaikan Git).
Gate Antislop memeriksa perilaku kontrol, status data, kontras, keyboard, reflow, dan sumber data.
Hasil eksekusi lokal dan bukti setiap gate tersedia di [Verifikasi Antislop](../arsip/UI_ANTISLOP_REVIEW.md).

## Atribusi ikon

Solar Icons oleh 480 Design, lisensi [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/),
bersumber dari [Solar Icon Set](https://github.com/480-Design/Solar-Icon-Set) melalui
`@iconify-json/solar`. SVG lokal hanya mengambil ikon yang digunakan; regenerasi dengan
`npm run icons:assets`.
