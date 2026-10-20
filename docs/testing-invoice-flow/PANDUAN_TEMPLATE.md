# Pengujian flow invoice — {{batch}}

Semua dokumen dan identitas pada paket ini adalah dummy. Data awal hanya referensi
invoice D-2. Faktur, pembayaran, dan riwayat E-1 belum dibuat oleh seed flow.

## Persiapan

1. Buka `http://localhost:8000/login`, masuk akun demo Admin, CEO, atau COO.
2. Buka **Catat e-Faktur Keluaran**. Cari `{{batch}}` di dropdown Invoice D-2.
3. Pastikan keenam invoice tersedia. Semua memakai proyek `{{projectId}}`, dengan
   Masa Pajak Oktober–Desember 2026. Nilai lengkap serta NSFP ada dalam `manifest.json`.
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
| `inv-flow-{{batch}}-005` | `11-faktur-005.pdf` | 7.000.000 | 770.000 | 0 | Periode November |
| `inv-flow-{{batch}}-006` | `12-faktur-006.pdf` | 8.000.000 | 880.000 | 0 | Lintas tahun |

### Variasi tanggal yang wajib diperiksa

| Invoice | Tanggal invoice D-2 | Tanggal e-Faktur | Masa Pajak |
| --- | --- | --- | --- |
| 001 | 15 Oktober 2026 | 15 Oktober 2026 | 2026-10 |
| 002 | 16 November 2026 | 16 Oktober 2026 | 2026-10 |
| 003 | 17 Desember 2026 | 17 Oktober 2026 | 2026-10 |
| 004 (cadangan) | 18 Oktober 2026 | 18 Oktober 2026 | 2026-10 |
| 005 | 19 Desember 2026 | 19 November 2026 | 2026-11 |
| 006 | 20 Januari 2027 | 20 Desember 2026 | 2026-12 |

Masa Pajak mengikuti tanggal e-Faktur, bukan tanggal invoice D-2. Periksa pada
pratinjau, detail, filter daftar, rekap, dan ekspor. Pengganti 001 bertanggal
20 Oktober dan pencatatan ulang 002 bertanggal 21 Oktober, tetap Masa Pajak Oktober.
Perbedaan tanggal ini adalah skenario pengujian, bukan bukti kepatuhan pajak;
kesesuaiannya perlu diperiksa berdasarkan waktu penyerahan/penerimaan uang muka.

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
4. Cari `{{batch}}` pada dropdown pencatatan baru. Invoice 004 cadangan, 005, dan 006
   masih tersedia. Faktur asal 001 dan faktur batal 002 tetap terlihat di daftar
   serta detail, tetapi tidak dihitung pada rekap.
5. Ringkasan Oktober proyek `{{projectId}}` berisi **3 faktur aktif**, DPP **Rp 37.000.000**,
   PPN **Rp 4.070.000**, dan PPnBM **Rp 100.000**. Selisih pembayaran aplikasi
   dihitung terhadap total PPN; PPnBM ditampilkan terpisah.
   API untuk mengecek batch saja:
   `GET /api/v1/tax/summary?taxPeriod=2026-10&projectId={{projectId}}`.

6. Catat invoice 005 dengan `11-faktur-005.pdf`, lalu invoice 006 dengan
   `12-faktur-006.pdf`. Hanya invoice 004 cadangan masih tersedia.
7. Periksa ringkasan proyek per periode (ubah `taxPeriod` pada API di atas):

   | Masa Pajak | Faktur aktif | DPP | PPN | PPnBM |
   | --- | ---: | ---: | ---: | ---: |
   | 2026-10 | 3 | 37.000.000 | 4.070.000 | 100.000 |
   | 2026-11 | 1 | 7.000.000 | 770.000 | 0 |
   | 2026-12 | 1 | 8.000.000 | 880.000 | 0 |
   | 2027-01 | 0 | 0 | 0 | 0 |

   Invoice 002/003 tidak berpindah ke November/Desember dan invoice 006 tidak
   berpindah ke Januari hanya karena tanggal invoice lebih akhir.

Rekap bulanan mencakup semua proyek. Jika sudah ada batch/transaksi pada periode yang sama,
nilai rekap bulanan merupakan gabungan. Jangan menganggap angka satu batch sebagai
total seluruh periode. Invoice cadangan 004 juga menambah rekap jika disimpan.

## 5. Pembayaran, pelengkapan, dan ekspor

1. Buka rekap **2026-10**, klik **Catat Pembayaran**.
2. Isi jumlah setor **4070000.00**, billing **{{billingId}}**, unggah
   `07-e-billing.pdf`. Biarkan NTPN dan bukti kosong untuk menguji **Draft**.
3. Klik **Lengkapi**, isi NTPN **{{ntpn}}**, unggah `08-bukti-pembayaran.pdf`.
   Hasilnya **Terkonfirmasi**. Jika periode hanya berisi batch ini dan belum ada
   pembayaran lain, selisih menjadi nol.
4. Buka **Ekspor** dan periksa pratinjau serta hasil Excel/PDF. Faktur asal yang
    terganti dan faktur batal tetap bisa ditelusuri, tetapi tidak dijumlahkan.
5. Ekspor juga November dan Desember. Pembayaran contoh hanya untuk Oktober;
   November dan Desember belum dibayar (PPN masing-masing Rp 770.000 dan Rp 880.000).

## Pengujian ulang

Jangan mengunggah NSFP sama untuk pencatatan kedua. Gunakan `php artisan demo:flow`
untuk mendapatkan enam invoice serta nomor seri baru. Data yang sudah dicatat
tetap dipertahankan; tidak perlu mengosongkan database.
