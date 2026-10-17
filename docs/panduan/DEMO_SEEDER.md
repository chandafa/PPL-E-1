# Data demo lengkap E-1

Semua invoice, klien, akun, NTPN, dan PDF di paket ini adalah contoh fiktif.
Seeder hanya berjalan di luar production dengan `D2_DRIVER=mock`.

## Mengisi data

```bash
php artisan migrate
php artisan db:seed
php artisan demo:samples
```

Seeder menambahkan data yang belum ada. Menjalankannya ulang mempertahankan faktur,
koreksi, pembatalan, riwayat, dan pembayaran yang sudah diubah melalui UI. Tidak ada
TRUNCATE atau penghapusan data. Jika satu tahap gagal, penambahan dibatalkan dan
berkas baru milik proses tersebut dibersihkan.

Pada database kosong, hasilnya 32 faktur, 34 item, 35 riwayat, 5 pembayaran, dan
5 rekap masa pajak. Pada database yang sudah berisi data, jumlah akhir dapat berbeda
karena seeder mempertahankan kondisi yang ada.

PDF berada di `docs/postman/samples/`: 39 PDF, satu berkas bukan PDF untuk validasi,
dan `manifest.json` yang memetakan berkas ke invoice, NSFP, serta alur penggunaan.

## Skenario rekap awal

| Masa pajak | Faktur dihitung | Total PPN | Jumlah setor | Selisih | Status |
|---|---:|---:|---:|---:|---|
| 2026-10 | 24 | Rp 5.940.000 | Rp 5.940.000 | Rp 0 | Terkonfirmasi, gabungan dua pembayaran |
| 2026-11 | 3 | Rp 46.200.000 | Rp 46.200.000 | Rp 0 | Draft / Belum Konfirmasi |
| 2026-12 | 1 | Rp 6.600.000 | Rp 0 | -Rp 6.600.000 | Belum dicatat |
| 2027-01 | 1 | Rp 1.100.000 | Rp 1.000.000 | -Rp 100.000 | Terkonfirmasi, kurang setor |
| 2027-02 | 1 | Rp 1.100.000 | Rp 1.200.000 | Rp 100.000 | Terkonfirmasi, lebih setor |

Oktober berisi 24 faktur agar pagination bisa dicoba pada periode yang sama. Faktur
`inv-demo-010` juga memiliki dua item, potongan, uang muka, dan PPnBM Rp 100.000.
November mempertahankan contoh faktur asal, Pengganti, koreksi, serta pembayaran Draft.
Desember mempertahankan faktur Dibatalkan. Januari 2026 tetap kosong untuk empty state.
Periode bersifat tetap, bukan mengikuti bulan komputer; pilih periode di atas jika bulan
berjalan sudah berubah.

## Mencoba pencatatan baru dari UI

Untuk paket flow baru yang belum terpakai, gunakan `php artisan demo:flow`.
Paket berisi empat invoice D-2, PDF, panduan, dan checklist; faktur/pembayaran belum
disimpan. Lihat [Paket Pengujian Invoice](../testing-invoice-flow/README.md).

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
| Mengganti faktur 006 sesudah dicatat | `inv-demo-006` | `efaktur-inv-demo-006-pengganti.pdf` |
| Mengganti faktur 001 yang sudah ada | `inv-demo-001` | `efaktur-inv-demo-001-pengganti-baru.pdf` |
| Mencatat ulang invoice 004 setelah pembatalan | `inv-demo-004` | `efaktur-inv-demo-004-baru.pdf` |

Setiap PDF memakai NSFP unik. Setelah suatu PDF berhasil disimpan, penggunaan ulang
NSFP yang sama memang ditolak. Seeder aditif tidak menghapus hasil percobaan tersebut.
Pada manifest, `seededByDefault=false` berarti berkas tidak digunakan oleh seeder,
bukan jaminan bahwa pengguna belum pernah memakainya.

## Mencoba pembayaran dan ekspor

- Pada rekap November, klik Lengkapi untuk menambahkan NTPN dan `bukti-pembayaran.pdf`.
- Pada rekap Desember, klik Catat Pembayaran, isi jumlah dan billing, lalu unggah `e-billing.pdf`.
- Untuk Draft, simpan tanpa NTPN/bukti. Untuk Terkonfirmasi, lengkapi keduanya.
- Pada rekap Oktober, Januari 2027, dan Februari 2027, bandingkan status serta selisihnya.
- Dialog Ekspor menyediakan pratinjau dan unduhan Excel/PDF untuk setiap periode.

UI memilih invoice melalui dropdown dengan pencarian. Hanya invoice tanpa faktur
keluaran aktif yang ditampilkan; pengganti memakai invoice asal yang terkunci.

## Pemisahan dari tutorial Postman

Tutorial Postman lama dimulai dari database kosong dan membuat enam faktur lewat request.
Gunakan database demo terpisah untuk tutorial itu. Menjalankan request pembuatan PDF
001 sampai 005 di database yang sudah di-seed akan menemui konflik NSFP/invoice aktif,
sesuai aturan bisnis. `migrate:fresh` menghapus data; tidak diperlukan untuk menambah
dataset baru dengan `db:seed`.

## Hasil verifikasi lokal

Pada 17 Oktober 2026, 118 tes PHP (1.130 assertions) dan 7 skenario browser lulus.
Pengujian seeder mencakup jumlah dan nominal data, pagination API, PDF pencatatan
serta pengganti, pelestarian perubahan pengguna, dan rollback data beserta berkas.

Database lokal juga sudah diisi secara aditif: 25 baris lama tetap identik, total
menjadi 32 faktur dan 5 pembayaran, serta seluruh 41 berkas yang dirujuk tersedia.
Eksekusi kedua menambah 0 faktur dan 0 pembayaran tanpa mengubah data.
Browser pada port 8080 menampilkan pagination 20 + 12 baris dan rekap Oktober
terkonfirmasi. Ekstraksi PDF invoice 006 dan 007 memberi HTTP 200 tanpa penyimpanan.
