# Data demo lengkap E-1

Diperbarui 20 Oktober 2026. Jalankan perintah dari akar repositori PPL-E-1 pada
setiap laptop setelah dependensi dan PostgreSQL siap sesuai [README](../../README.md).

Semua invoice, klien, akun, NTPN, dan PDF di paket ini adalah contoh fiktif.
Seeder hanya berjalan di luar production dengan `D2_DRIVER=mock`.

## Mengisi data

Untuk laptop anggota tim, gunakan kode dan lockfile terbaru, jalankan
`composer install`, `npm ci`, serta `npm run build`. Siapkan `.env`, APP_KEY, dan
database masing-masing sesuai README. Gunakan `APP_ENV=local`, `D2_DRIVER=mock`,
dan `E1_DEV_LOGIN=true`, lalu jalankan:

```bash
php artisan migrate
php artisan db:seed
php artisan demo:samples
```

Jalankan `composer serve` dan buka `http://127.0.0.1:8080/login`. PDF yang
diabaikan Git dibangkitkan ulang di setiap laptop oleh perintah generator ini.

Seeder menambahkan data yang belum ada. Menjalankannya ulang mempertahankan faktur,
koreksi, pembatalan, riwayat, dan pembayaran yang sudah diubah melalui UI. Tidak ada
TRUNCATE atau penghapusan data. Jika satu tahap gagal, penambahan dibatalkan dan
berkas baru milik proses tersebut dibersihkan.

Pada database kosong, hasilnya 32 faktur, 34 item, 34 riwayat, 5 pembayaran, dan
5 rekap masa pajak. Pada database yang sudah berisi data, jumlah akhir dapat berbeda
karena seeder mempertahankan kondisi yang ada.

PDF berada di `docs/postman/samples/`: 48 e-Faktur dan 14 lampiran pembayaran,
satu berkas bukan PDF, `manifest.json`, serta `validation.json`. Jumlah mengikuti
katalog mock yang disertakan; menambah invoice manual akan menambah sampelnya.

`demo:samples` tidak menambah transaksi database. Perintah membaca kembali setiap
e-Faktur dengan parser tombol **Periksa PDF**, lalu mencocokkan tanggal invoice dan
e-Faktur, masa pajak, tahun NSFP, nominal, jumlah/kode/nama item, potongan, dan uang
muka. Lampiran diperiksa keterbacaan teksnya. Berkas dipublikasikan sesudah seluruh
validasi lulus; kegagalan validasi menghasilkan exit code gagal.

Manifest memuat invoiceId, invoiceNumber, invoiceDate, taxInvoiceDate, taxPeriod,
NSFP, nominal, jumlah item, alur, dan SHA-256 PDF. `validation.json` memuat hasil
pemeriksaan serta metadata lampiran. Seed dan generator memakai katalog
`database/data/d2-invoices.json` melalui konfigurasi mock yang sama. Tanggal tetap
mengikuti katalog, bukan jam komputer. Sampel 2027 memakai tahun NSFP 27.

## Skenario rekap awal

| Masa pajak | Faktur dihitung | Total PPN | Jumlah setor | Selisih | Status |
|---|---:|---:|---:|---:|---|
| 2026-10 | 24 | Rp 5.940.000 | Rp 5.940.000 | Rp 0 | Terkonfirmasi, gabungan dua pembayaran |
| 2026-11 | 3 | Rp 46.200.000 | Rp 46.200.000 | Rp 0 | Draft / Belum Konfirmasi |
| 2026-12 | 1 | Rp 6.600.000 | Rp 0 | -Rp 6.600.000 | Belum dicatat |
| 2027-01 | 1 | Rp 1.100.000 | Rp 1.000.000 | -Rp 100.000 | Terkonfirmasi, kurang setor |
| 2027-02 | 1 | Rp 1.100.000 | Rp 1.100.000 | Rp 0 | Terkonfirmasi, setor pas (lebih setor ditolak sistem) |

Oktober berisi 24 faktur agar pagination bisa dicoba pada periode yang sama. Faktur
`inv-demo-010` juga memiliki dua item, potongan, dan uang muka. PPnBM untuk contoh
jasa ini diperbaiki menjadi nol. Dua invoice manual tambahan menyediakan skenario
barang mewah dan jasa pada proyek simulasi yang sama; lihat
[panduan PPN dan PPnBM](PPN_DAN_PPNBM.md). Keduanya tidak ditanam seeder standar.
November memuat faktur asal, Pengganti, dan pembayaran Draft. Koreksi dapat dicoba
melalui detail faktur. Seeder tidak lagi menanam tanggal salah untuk simulasi
koreksi: PDF tersimpan, tanggal e-Faktur, dan invoice pada instalasi baru sesuai.
Desember mempertahankan faktur Dibatalkan. Januari 2026 tetap kosong untuk empty state.
Periode bersifat tetap, bukan mengikuti bulan komputer; pilih periode di atas jika bulan
berjalan sudah berubah.

## Mencoba pencatatan baru dari UI

Gunakan `demo:samples` untuk menyiapkan PDF. Pembuat batch lama telah dihapus.
Referensi invoice manual yang sudah ada tetap tersedia dan tidak dicatat otomatis
oleh seeder.

1. Masuk melalui `/login` dengan akun demo Admin, CEO, atau COO.
2. Buka Faktur Pajak, lalu Catat e-Faktur Keluaran.
3. Masukkan `inv-demo-006` dan unggah `efaktur-inv-demo-006.pdf`.
4. Klik Periksa PDF, periksa hasilnya, lalu Simpan Faktur.
5. Dari detail, klik Catat Pengganti dan unggah `efaktur-inv-demo-006-pengganti.pdf`.

Invoice 006 dan 007 ada dalam mock D-2 tetapi sengaja tidak dicatat oleh seeder.

| Tujuan | ID invoice | PDF |
|---|---|---|
| Pencatatan baru utama | `inv-demo-006` | `efaktur-inv-demo-006.pdf` |
| Pencatatan baru tambahan | `inv-demo-007` | `efaktur-inv-demo-007.pdf` |
| Pencatatan manual | `inv-manual-001` | `efaktur-inv-manual-001.pdf` |
| Barang mewah, PPnBM nonnol | `inv-manual-ppnbm-001` | `efaktur-inv-manual-ppnbm-001.pdf` |
| Jasa tanpa PPnBM, proyek simulasi yang sama | `inv-manual-jasa-001` | `efaktur-inv-manual-jasa-001.pdf` |
| Mengganti faktur 006 sesudah dicatat | `inv-demo-006` | `efaktur-inv-demo-006-pengganti.pdf` |
| Mengganti faktur 001 yang sudah ada | `inv-demo-001` | `efaktur-inv-demo-001-pengganti-baru.pdf` |
| Mencatat ulang invoice 004 setelah pembatalan | `inv-demo-004` | `efaktur-inv-demo-004-baru.pdf` |

Setiap PDF memakai NSFP unik. Setelah suatu PDF berhasil disimpan, penggunaan ulang
NSFP yang sama memang ditolak. Seeder aditif tidak menghapus hasil percobaan tersebut.
Pada manifest, `seededByDefault=false` berarti berkas tidak digunakan oleh seeder,
bukan jaminan bahwa pengguna belum pernah memakainya.

## Mencoba pembayaran dan ekspor

- Pada rekap November, klik Lengkapi dengan NTPN dan `bukti-pembayaran-2026-11-001.pdf`.
- Lampiran Oktober, Januari 2027, dan Februari 2027 tersedia dengan masa pajak pada nama berkas; rinciannya ada di `validation.json`.
- Pembayaran Desember belum dicatat seeder. Gunakan `e-billing-2026-12-001.pdf` dan `bukti-pembayaran-2026-12-001.pdf` untuk mencobanya dari UI.
- Untuk Draft, simpan tanpa NTPN/bukti. Untuk Terkonfirmasi, lengkapi keduanya.
- Pada rekap Oktober, Januari 2027, dan Februari 2027, bandingkan status serta selisihnya.
- Dialog Ekspor menyediakan pratinjau dan unduhan Excel/PDF untuk setiap periode.

Nama lampiran adalah `e-billing-YYYY-MM-NNN.pdf` dan
`bukti-pembayaran-YYYY-MM-NNN.pdf`. Tanggal pembayaran contoh adalah 10 bulan
berikutnya (Q-09), dengan e-Billing sehari sebelumnya; masa pajak tetap bulan
e-Faktur (Q-12). Hanya bukti pembayaran yang memuat NTPN. Nama pendek
`e-billing.pdf` dan `bukti-pembayaran.pdf` merujuk November 2026 untuk koleksi
Postman, sehingga tidak dipakai sebagai contoh lintas periode.

UI memilih invoice melalui dropdown dengan pencarian. Hanya invoice tanpa faktur
keluaran aktif yang ditampilkan; pengganti memakai invoice asal yang terkunci.

## Pemisahan dari tutorial Postman

Tutorial Postman lama dimulai dari database kosong dan membuat enam faktur lewat request.
Gunakan database demo terpisah untuk tutorial itu. Menjalankan request pembuatan PDF
001 sampai 005 di database yang sudah di-seed akan menemui konflik NSFP/invoice aktif,
sesuai aturan bisnis. `migrate:fresh` menghapus data; tidak diperlukan untuk menambah
dataset baru dengan `db:seed`.

## Hasil verifikasi lokal

Pada pemeriksaan terakhir 20 Oktober 2026, 148 tes PHP lulus (1.917 assertions),
mencakup seeding dari database kosong, tanggal PDF tersimpan, ekstraksi semua
48 sampel lewat API, pelestarian edit ketika seeding ulang, rollback data/berkas,
dan penolakan nominal pajak rusak. Skenario PPnBM nonnol diperiksa sampai koreksi,
pengganti lintas bulan, pembatalan, ringkasan proyek, dan ekspor Excel/PDF.
Generator menghasilkan 62 PDF tervalidasi (48 e-Faktur dan 14 lampiran), dengan
seluruh hash manifest sesuai. Tiga tes unit JavaScript dan build aset juga lulus.

Seluruh 16 tes browser lulus pada Chrome/Windows: login, dropdown invoice,
pencatatan/pengganti/pembatalan, koreksi, pembayaran, ekspor, aksesibilitas, dan
pemeriksaan PDF pada desktop/mobile, termasuk dua invoice simulasi pajak.
Pengujian memakai database terpisah.
Hasil browser dan screenshot tersedia pada `test-results/` setelah pengujian.

Website diperbaiki agar kontrol detail siap ketika riwayat lambat dimuat dan form
PDF tidak melebar pada ponsel ketika invoice/nama berkas panjang. Pengujian browser
memakai build tetap agar tidak ikut reload dari Vite pengembangan yang sedang aktif.

Catatan berikut adalah hasil historis 17 Oktober. Perubahan 20 Oktober membuat
riwayat awal menjadi 34 karena koreksi tanggal buatan dihapus; gunakan hasil
pengujian terbaru untuk versi saat ini.

Pada 17 Oktober 2026, 118 tes PHP (1.130 assertions) dan 7 skenario browser lulus.
Pengujian seeder mencakup jumlah dan nominal data, pagination API, PDF pencatatan
serta pengganti, pelestarian perubahan pengguna, dan rollback data beserta berkas.

Database lokal juga sudah diisi secara aditif: 25 baris lama tetap identik, total
menjadi 32 faktur dan 5 pembayaran, serta seluruh 41 berkas yang dirujuk tersedia.
Eksekusi kedua menambah 0 faktur dan 0 pembayaran tanpa mengubah data.
Browser pada port 8080 menampilkan pagination 20 + 12 baris dan rekap Oktober
terkonfirmasi. Ekstraksi PDF invoice 006 dan 007 memberi HTTP 200 tanpa penyimpanan.
