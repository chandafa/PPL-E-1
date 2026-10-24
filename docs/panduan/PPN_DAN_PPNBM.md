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

## Invoice D-2 yang tersedia

E-1 menanam tabel referensi D-2 melalui seeder modul D-2. Database sumber saat ini
memiliki satu invoice, `inv-demo-sample`, dengan DPP Rp 100.000.000 dan PPN
Rp 11.000.000. Skema D-2 belum menyediakan kolom PPnBM atau klien; PPnBM yang
tidak tercantum pada PDF e-Faktur dicatat sebagai nol. Katalog D-2 tidak berisi
invoice yacht sintetis atau invoice unggah manual.

Tarif dan dasar pengenaan transaksi nyata harus mengikuti dokumen penerbit dan
parameter pajak yang ditetapkan D-2. Tes E-1 memakai data fixture terpisah hanya
untuk menguji perhitungan pajak dan pelaporan E-1.

## Mencoba dari website

1. Jalankan `php artisan db:seed` dan `php artisan demo:samples`.
2. Buka halaman Catat e-Faktur Keluaran.
3. Pilih invoice D-2 yang tersedia dan unggah PDF e-Faktur sesuai dengan invoice.
4. Periksa PPN dan PPnBM yang diekstrak dari PDF, lalu simpan.

Seeder E-1 bersifat aditif dan mempertahankan koreksi pengguna. Katalog sumber
D-2 tidak ditambah dengan invoice sintetis.

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
