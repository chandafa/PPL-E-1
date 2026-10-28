# Data demo E-1

Diperbarui 24 Oktober 2026. Jalankan perintah dari akar repositori PPL-E-1 pada
setiap laptop setelah dependensi dan PostgreSQL siap sesuai [README](../../README.md).

Snapshot invoice D-2 berasal dari tabel dan seeder modul D-2. Seeder E-1 tidak
membuat invoice atau termin D-2 tambahan. Dokumen e-Faktur, pembayaran, NTPN, dan
PDF keluaran generator tetap merupakan data demo E-1. Seeder demo hanya berjalan
di luar production dengan `D2_DRIVER=mock`.

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

Pada database kosong, `php artisan db:seed` menghasilkan data referensi D-2 dasar:
3 kontrak, 4 termin, 1 parameter pajak, dan 1 invoice. `DatabaseSeeder` tidak
menjalankan `DemoSeeder` otomatis. Saat `DemoSeeder` dijalankan terpisah, seeder
tambahan `ModulD2DemoSeeder` lebih dulu mengisi 31 invoice D-2 yang diperlukan oleh
dataset E-1; invoice 006 dan 007 sengaja tetap tersedia untuk skenario UI. Setelah itu,
`DemoSeeder` mencatat 32 faktur pajak dan 5 pembayaran demo.

PDF contoh berada di `docs/postman/samples/`, bersama `manifest.json` dan
`validation.json`. Sampel e-Faktur dan lampiran dibuat berdasarkan invoice D-2
yang tersedia di database saat generator dijalankan.

`demo:samples` tidak menambah transaksi database. Perintah membaca kembali setiap
e-Faktur dengan parser tombol **Periksa PDF**, lalu mencocokkan tanggal invoice dan
e-Faktur, masa pajak, tahun NSFP, nominal, jumlah/kode/nama item, potongan, dan uang
muka. Lampiran diperiksa keterbacaan teksnya. Berkas dipublikasikan sesudah seluruh
validasi lulus; kegagalan validasi menghasilkan exit code gagal.

Manifest memuat invoiceId, invoiceNumber, invoiceDate, taxInvoiceDate, taxPeriod,
NSFP, nominal, jumlah item, alur, dan SHA-256 PDF. `validation.json` memuat hasil
pemeriksaan serta metadata lampiran. Seed dan generator membaca invoice dari tabel D-2 lokal; tanggal mengikuti tanggal
invoice D-2, bukan jam komputer.

## Skenario rekap awal

| Masa pajak | Faktur dihitung |     Total PPN |  Jumlah setor |       Selisih | Status                                                |
| ---------- | --------------: | ------------: | ------------: | ------------: | ----------------------------------------------------- |
| 2026-10    |              25 | Rp 16.940.000 |  Rp 5.940.000 | -Rp 11.000.000 | Terkonfirmasi, kurang setor                        |
| 2026-11    |               3 | Rp 46.750.000 | Rp 46.750.000 |          Rp 0 | Draft / Belum Konfirmasi                              |
| 2026-12    |               2 | Rp 19.800.000 |          Rp 0 | -Rp 19.800.000 | Belum dicatat                                       |
| 2027-01    |               1 |  Rp 1.100.000 |  Rp 1.000.000 |   -Rp 100.000 | Terkonfirmasi, kurang setor                           |
| 2027-02    |               1 |  Rp 1.100.000 |  Rp 1.100.000 |          Rp 0 | Terkonfirmasi, setor pas (lebih setor ditolak sistem) |

Oktober berisi 24 invoice dari katalog D-2 ditambah invoice sampel dasar agar pagination
bisa dicoba pada periode yang sama. Totalnya 25 faktur pajak. Faktur
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

Gunakan `demo:samples` untuk menyiapkan PDF berdasarkan invoice D-2 yang ada.

1. Masuk melalui `/login` dengan akun demo Admin, CEO, atau COO.
2. Buka Faktur Pajak, lalu Catat e-Faktur Keluaran.
3. Pilih invoice D-2 yang tersedia dan unggah PDF e-Faktur yang sesuai.
4. Klik Periksa PDF, periksa hasilnya, lalu Simpan Faktur.
   Untuk menguji penggantian, gunakan alur Catat Pengganti dari halaman detail faktur.
   Setiap PDF memakai NSFP unik. Seeder aditif tidak menghapus data yang sudah diubah
   melalui UI.

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
