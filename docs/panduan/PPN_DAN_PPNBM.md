# Pemeriksaan PPN, PPnBM, produk, dan proyek

## Acuan dan batas tanggung jawab

E-1 mencatat nominal dari PDF e-Faktur sesuai Q-02, Q-05, dan skema data
Pertemuan 2. Perhitungan komersial invoice dan parameter pajak milik D-2.
E-1 tidak menghitung ulang semua PPN dengan persentase tetap.

PPnBM berlaku pada barang tertentu yang tergolong mewah; nama proyek, harga
mahal, atau nama klien tidak menentukan pengenaannya. Penyerahan oleh produsen,
impor, serta pengecualian penggunaan barang juga perlu diperhatikan. Lihat
[UU PPN, penjelasan Pasal 5](https://www.pajak.go.id/undang-undang-nomor-42-tahun-2009)
dan [PMK 96/2021 beserta perubahannya](https://jdih.kemenkeu.go.id/dok/96-pmk-03-2021).

Kontrak D-2 yang tersedia belum menyertakan master klasifikasi pajak produk,
kode HS, tahap penyerahan, atau fasilitas pembebasan. Karena itu, E-1 belum dapat
menyatakan klasifikasi hukum setiap produk secara otomatis. Nominal PDF dan
koreksi petugas tetap menjadi sumber pencatatan. Kode barang pada contoh adalah
identitas item demo, bukan bukti klasifikasi HS atau penetapan objek pajak.

## Dataset yang diperbaiki

| Contoh | Produk/jasa | Proyek | PPN pada PDF | PPnBM pada PDF |
|---|---|---|---:|---:|
| `inv-demo-010` | Jasa konfigurasi dan pendampingan | Administrasi demo | Rp 121.000 | Rp 0 |
| `inv-manual-ppnbm-001` | Penyerahan yacht privat produksi sendiri, bukan untuk negara, angkutan umum, atau usaha pariwisata | Simulasi Produksi Yacht Privat | Rp 120.000.000 | Rp 750.000.000 |
| `inv-manual-jasa-001` | Jasa konfigurasi sistem inventaris yacht | Simulasi Produksi Yacht Privat | Rp 1.100.000 | Rp 0 |

Contoh yacht memakai harga/DPP simulasi Rp 1.000.000.000, PPN 12%, dan PPnBM
75%. Penggunaan yacht untuk usaha pariwisata memiliki perlakuan berbeda;
contoh ini sengaja menggunakan rekreasi privat. Acuan:
[penjelasan DJP tentang pengecualian yacht](https://www.pajak.go.id/en/node/69120)
dan [siaran DJP mengenai PPN 2025](https://pajak.go.id/sites/default/files/2025-01/SP%201%202025.pdf).

Dua invoice manual tersebut tidak ditanam oleh seeder standar agar data jasa TI
mitra tidak berubah menjadi usaha produsen yacht. Keduanya adalah skenario uji
fiktif terpisah, tersedia di dropdown invoice setelah kode diperbarui. Produk
barang dan jasa sengaja memakai proyek yang sama untuk memastikan PPnBM tidak
diterapkan ke seluruh invoice proyek secara otomatis.

Angka contoh jasa yang berasal dari tugas/D-2 dipertahankan sebagai data uji
pencatatan; pengujian E-1 bukan validasi tarif legal semua transaksi tahun 2026.
Tarif dan dasar pengenaan transaksi nyata harus mengikuti dokumen penerbit dan
parameter D-2 yang telah ditetapkan.

## Mencoba dari website

1. Jalankan `php artisan db:seed` dan `php artisan demo:samples`.
2. Buka halaman Catat e-Faktur Keluaran.
3. Pilih `inv-manual-ppnbm-001`, unggah
   `docs/postman/samples/efaktur-inv-manual-ppnbm-001.pdf`, lalu Periksa PDF.
4. Pastikan item `YACHT-01`, PPN Rp 120.000.000, dan PPnBM Rp 750.000.000.
5. Simpan; ulangi dengan `inv-manual-jasa-001` dan PDF bernama sesuai ID tersebut.
   Item `JASA-01` mempunyai PPnBM nol meskipun proyeknya sama.
6. Rekap Oktober bertambah PPN Rp 121.100.000 dan PPnBM Rp 750.000.000.
   Ringkasan proyek `prj-simulasi-yacht-001` harus menampilkan dua faktur tersebut.

Seeder bersifat aditif dan mempertahankan koreksi pengguna. Jika database lama
masih mempunyai contoh jasa 010 dengan PPnBM Rp 100.000, koreksi melalui detail
faktur menjadi `0.00` dengan catatan perbaikan data demo. Riwayat dan PDF lama
tetap menjadi bukti kondisi sebelumnya. PDF baru dari generator sudah bernilai nol.

## Aturan yang diperiksa

- PPN dan PPnBM disimpan terpisah, memakai string decimal(18,2), tanpa float.
- PPnBM yang benar-benar tidak tercantum di PDF menjadi `0.00`. Label PPnBM
  yang ada tetapi nominalnya rusak ditolak, bukan dianggap nol.
- Nilai negatif, kapasitas berlebih, pemisah ribuan rusak, akhiran teks, dan
  pecahan melebihi dua digit ditolak dengan `400 EXTRACTION_FAILED`.
- Format PDF Indonesia dan internasional didukung; respons JSON tetap seperti
  `"750000000.00"`. Tampilan web/PDF memakai format Indonesia.
- Rekap menghitung faktur NORMAL dan REPLACEMENT aktif, mengecualikan CANCELLED
  dan faktur asal yang sudah diganti. Koreksi/perpindahan bulan memperbarui rekap.
- Ringkasan dengan projectId hanya menjumlahkan faktur proyek tersebut.
- Excel dan PDF menampilkan total PPN dan total PPnBM secara terpisah.
- Sesuai kontrak tugas, `paymentDifference = amountPaid - totalPpn`, termasuk
  catatan pembayaran Draft. Angka ini bukan perhitungan seluruh kewajiban pajak
  termasuk PPnBM; E-1 belum memiliki pemisahan pembayaran per jenis pajak.

Tes regresi berada pada `tests/Feature/TaxAmountsTest.php`,
`tests/Unit/EFakturPdfParserTest.php`, `tests/Feature/DemoSeederTest.php`, dan
`tests/browser/samples.spec.mjs`. Tes API/database memakai PostgreSQL terpisah;
pengujian ini belum membuktikan integrasi langsung dengan D-2 atau DJP.

Pada 20 Oktober 2026: 148 tes PHP lulus (1.917 assertions), 16 tes browser
lulus, 3 tes unit JavaScript lulus, serta build dan pemeriksaan format PHP lulus.
Generator memverifikasi 62 PDF dan seluruh hash manifest. Audit 44 referensi
invoice pada 13 proyek tidak menemukan ID ganda, total invoice yang berbeda dari
DPP + PPN + PPnBM - PPh, atau identitas proyek/klien yang bertentangan.
