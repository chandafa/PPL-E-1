# Pengujian flow invoice — {{batch}}

Semua dokumen dan identitas pada paket ini adalah dummy. Data awal hanya referensi
invoice D-2. Faktur, pembayaran, dan riwayat E-1 belum dibuat oleh seed flow.

## Persiapan

1. Buka `http://localhost:8000/login`, masuk akun demo Admin, CEO, atau COO.
2. Buka **Catat e-Faktur Keluaran**. Cari `{{batch}}` di dropdown Invoice D-2.
3. Pastikan keempat invoice tersedia. Semua memakai proyek `{{projectId}}` dan masa
   pajak **2026-09**. Nilai lengkap serta NSFP ada dalam `manifest.json`.
4. Catat hasil aktual dan status pada `CHECKLIST.csv` selama mengikuti langkah berikut.

## Pasangan invoice dan dokumen

| Invoice | Dokumen | DPP | PPN | PPnBM | Tujuan |
| --- | --- | ---: | ---: | ---: | --- |
| `inv-flow-{{batch}}-001` | `01-faktur-001.pdf` | 10.000.000 | 1.100.000 | 0 | Pencatatan pertama |
| Invoice 001 yang sama | `05-pengganti-001.pdf` | 9.000.000 | 990.000 | 0 | Pengganti melalui detail |
| `inv-flow-{{batch}}-002` | `02-faktur-002.pdf` | 20.000.000 | 2.200.000 | 0 | Pembatalan |
| Invoice 002 yang sama | `06-catat-ulang-002.pdf` | 18.000.000 | 1.980.000 | 0 | Dicatat setelah pembatalan |
| `inv-flow-{{batch}}-003` | `03-faktur-003.pdf` | 10.000.000 | 1.100.000 | 100.000 | Dua item, diskon, dan uang muka |
| `inv-flow-{{batch}}-004` | `04-faktur-004.pdf` | 5.000.000 | 550.000 | 0 | Cadangan; jangan simpan pada flow utama |

## 1. Penolakan file dan pratinjau

- Pilih invoice 001, unggah `10-bukan-pdf.txt`. **Periksa PDF** menolak format dan
  tombol simpan tetap nonaktif.
- Unggah `09-bukan-efaktur.pdf`. Format PDF sah, tetapi ekstraksi gagal karena
  tidak berisi identitas dan tabel e-Faktur. Faktur belum tersimpan.
- Unggah `01-faktur-001.pdf`, klik **Periksa PDF**. Nomor seri, tanggal, item, DPP,
  dan PPN sesuai manifest. Tombol simpan menjadi aktif.
- Ganti pilihan ke invoice 002. Pratinjau lama tidak berlaku dan tombol simpan
  kembali nonaktif. Pilih invoice 001 dan periksa kembali PDF 001.

## 2. Catat dan ganti invoice 001

1. Simpan PDF 001. Detail menunjukkan **Normal**. Simpan ID faktur asal.
2. Buka pencatatan baru: invoice 001 tidak muncul lagi karena memiliki faktur aktif.
   Pemaksaan lewat API tetap ditolak `409 INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE`.
3. Kembali ke detail faktur asal, klik **Catat Pengganti**. Invoice 001 terpilih dan
   terkunci. Unggah `05-pengganti-001.pdf`, lalu simpan langsung.
4. Faktur baru berstatus **Pengganti**. Faktur asal tampil **Terganti**, riwayat
   penautan tersimpan, dan keduanya saling menautkan. Invoice 001 tetap tidak muncul
   pada dropdown pencatatan baru.

## 3. Batalkan dan catat ulang invoice 002

1. Pilih invoice 002 dan unggah `02-faktur-002.pdf`; periksa lalu simpan.
2. Pada detail, klik **Batalkan**, isi alasan `Pengujian pembatalan {{batch}}`,
   lalu konfirmasi. Status berubah **Dibatalkan** dan riwayat bertambah.
3. Invoice 002 kembali muncul di dropdown karena tidak memiliki faktur aktif.
4. Pilih invoice 002, unggah `06-catat-ulang-002.pdf`, periksa lalu simpan.
   Status **Normal**, nomor seri baru, sedangkan faktur yang dibatalkan tetap ada.

## 4. Dua item dan rekap

1. Catat invoice 003 memakai `03-faktur-003.pdf`.
2. Pratinjau menunjukkan dua item: Rp 6.000.000 dan Rp 4.600.000. Diskon
   Rp 100.000 dan uang muka Rp 500.000 menghasilkan DPP Rp 10.000.000.
3. PPN Rp 1.100.000 dan PPnBM Rp 100.000 terbaca; periksa dan simpan.
4. Cari `{{batch}}` pada dropdown pencatatan baru. Hanya invoice 004 cadangan yang
   masih tersedia. Faktur asal 001 dan faktur batal 002 tetap terlihat di daftar
   serta detail, tetapi tidak dihitung pada rekap.
5. Ringkasan proyek `{{projectId}}` berisi **3 faktur aktif**, DPP **Rp 37.000.000**,
   PPN **Rp 4.070.000**, dan PPnBM **Rp 100.000**. Selisih pembayaran aplikasi
   dihitung terhadap total PPN; PPnBM ditampilkan terpisah.
   API untuk mengecek batch saja:
   `GET /api/v1/tax/summary?taxPeriod=2026-09&projectId={{projectId}}`.

Rekap bulanan mencakup semua proyek. Jika sudah ada batch/transaksi September lain,
nilai rekap bulanan merupakan gabungan. Jangan menganggap angka satu batch sebagai
total seluruh periode. Invoice cadangan 004 juga menambah rekap jika disimpan.

## 5. Pembayaran, pelengkapan, dan ekspor

1. Buka rekap **2026-09**, klik **Catat Pembayaran**.
2. Isi jumlah setor **4070000.00**, billing **{{billingId}}**, unggah
   `07-e-billing.pdf`. Biarkan NTPN dan bukti kosong untuk menguji **Draft**.
3. Klik **Lengkapi**, isi NTPN **{{ntpn}}**, unggah `08-bukti-pembayaran.pdf`.
   Hasilnya **Terkonfirmasi**. Jika periode hanya berisi batch ini dan belum ada
   pembayaran lain, selisih menjadi nol.
4. Buka **Ekspor** dan periksa pratinjau serta hasil Excel/PDF. Faktur asal yang
   terganti dan faktur batal tetap bisa ditelusuri, tetapi tidak dijumlahkan.

## Pengujian ulang

Jangan mengunggah NSFP sama untuk pencatatan kedua. Gunakan `php artisan demo:flow`
untuk mendapatkan empat invoice serta nomor seri baru. Data yang sudah dicatat
tetap dipertahankan; tidak perlu mengosongkan database.
