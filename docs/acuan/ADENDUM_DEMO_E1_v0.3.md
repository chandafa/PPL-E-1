# Adendum spesifikasi dan kontrak API E-1

Versi: v0.3 | Tanggal: 17 Oktober 2026

Modul: Pencatatan e-Faktur dan pajak proyek, Kelas E Kelompok 1.

## Kedudukan dan target penerimaan

Adendum ini mencatat keputusan pengguna untuk implementasi demo lokal. Baca bersama
`01_Spesifikasi_Fitur_Minimum_E-1_v0.2.docx.pdf` dan
`Kontrak_API_Final_Kelas_E_Kelompok_1.docx.pdf`. Untuk butir yang disebutkan di bawah,
adendum menjelaskan aturan demo yang dipilih. PDF asli dipertahankan. Dokumen ini
bukan bukti persetujuan mitra atau kesepakatan integrasi lintas kelompok.

Target penerimaan adalah demo lokal. Mock F-1 dan D-2 diperbolehkan. Integrasi
langsung F-1, D-2, dan E-2 serta validasi PDF asli mitra tetap merupakan pekerjaan
lanjutan; hasil uji mock tidak dinyatakan sebagai bukti integrasi tersebut.

## Rekap masa pajak: FTR-KE01-04 dan API-KE01-07

- Dropdown hanya menampilkan masa pajak yang memiliki data faktur, dengan label bulan dan tahun.
- URL masa pajak valid yang belum memiliki data tetap menampilkan rekap kosong, total nol, serta daftar faktur dan pembayaran kosong. Tidak ada pengalihan ke periode lain dan tidak dibuat data fiktif.
- Periode kosong tidak ditambahkan sebagai pilihan dropdown. Jika ada periode lain, pengguna dapat memilihnya. Jika seluruh daftar periode kosong, dropdown dan tombol tampilkan dinonaktifkan.
- API tetap `GET /api/v1/tax-recaps/{taxPeriod}`. Periode kosong menghasilkan HTTP 200; format periode tidak valid menghasilkan HTTP 400. Bentuk JSON tidak berubah.

Aturan ini memenuhi kriteria periode kosong pada halaman 6 spesifikasi dan halaman
10 kontrak API, sekaligus mempertahankan dropdown yang hanya memuat data tersedia.

## Pencatatan pembayaran: API-KE01-08

Endpoint tetap `POST /api/v1/tax-payments`, memakai `multipart/form-data`.
Autentikasi menggunakan Bearer token; peran yang diizinkan adalah Admin, CEO, dan COO.

| Field | Wajib saat pencatatan awal | Ketentuan |
|---|---|---|
| taxPeriod | Ya | Masa pajak YYYY-MM yang sudah memiliki rekap. |
| taxYear | Ya | Tahun pajak, sama dengan tahun taxPeriod. |
| amountPaid | Ya | Nominal desimal tidak negatif. |
| billingId | Ya | ID atau kode billing. |
| eBillingFile | Ya | PDF e-Billing. |
| ntpn | Tidak | Wajib untuk status CONFIRMED. |
| paymentProofFile | Tidak | PDF bukti pembayaran; wajib untuk status CONFIRMED. |

Jika NTPN atau bukti belum tersedia, pembayaran tersimpan sebagai
`INCOMPLETE` dengan label Draft / Belum Konfirmasi. Jika keduanya tersedia dan
valid, statusnya `CONFIRMED`. Selisih jumlah setor dengan total PPN tetap diterima
dan dikembalikan sebagai `paymentDifference`.

Tabel halaman 11 kontrak PDF diperjelas: kewajiban NTPN dan bukti berlaku pada
konfirmasi, bukan pada penyimpanan Draft. Ini selaras dengan fitur Draft pada
halaman 7 spesifikasi dan request schema API-KE01-08 di `openapi.yaml`.

## Tambahan kontrak: API-KE01-11

Tujuan: melengkapi pembayaran dengan NTPN dan PDF bukti pembayaran.

**Metode dan path:** `PATCH /api/v1/tax-payments/{taxPaymentId}`.

**Content-Type:** `multipart/form-data`. Gunakan boundary multipart sesuai klien.

**Akses:** Bearer token yang valid; Admin, CEO, dan COO.

| Field dan lokasi | Tipe | Wajib | Ketentuan |
|---|---|---|---|
| taxPaymentId; path | string UUID v7 | Ya | ID catatan pembayaran yang sudah tersimpan. |
| ntpn; body | string | Ya | NTPN tidak kosong. |
| paymentProofFile; body | file biner | Ya | PDF bukti pembayaran. |

HTTP 200 memakai amplop JSON berikut:

```json
{
  "code": "200",
  "message": "Pembayaran Terkonfirmasi",
  "data": {
    "taxPaymentId": "01a25dad-c900-79c2-a6e1-b45d08af7362",
    "status": "CONFIRMED",
    "paymentStatus": "CONFIRMED"
  }
}
```

`status` adalah status catatan yang dilengkapi. `paymentStatus` adalah status
seluruh masa pajak: tetap `INCOMPLETE` bila masih ada pembayaran lain yang Draft;
menjadi `CONFIRMED` jika seluruh catatan sudah terkonfirmasi.

Kesalahan: HTTP 400 untuk NTPN/bukti yang tidak lengkap atau PDF tidak valid;
401 untuk token tidak sah; 403 untuk peran tidak berwenang; 404 jika catatan tidak
ditemukan. Galat menggunakan `{code, message, data: null, errorCode}`. Detail
schema, batas ukuran berkas, dan contoh galat mengikuti `openapi.yaml`.

## Koreksi nomor API dan konsistensi ekspor

- Pada halaman 2 kontrak PDF, rujukan API-KE01-12 untuk koordinasi dengan E-2 dibaca sebagai API-KE01-10, yaitu endpoint ringkasan pajak.
- Pada halaman 12 kontrak PDF, konsistensi angka ekspor diperiksa terhadap API-KE01-07 (rekap) dan API-KE01-10 (ringkasan). Catatan pembayaran berasal dari API-KE01-08 dan dapat dilengkapi melalui API-KE01-11.
- Tidak ada endpoint baru bernomor API-KE01-12 yang diwajibkan oleh rujukan tersebut. Daftar implementasi mencakup API-KE01-01 sampai API-KE01-11.
- Excel menggunakan satu sheet Rekap Pajak: Ringkasan, Daftar Faktur, lalu Pembayaran, berurutan seperti PDF. Nominal dan ID Excel tetap teks untuk menjaga seluruh digit.

## Batas integrasi yang masih terbuka

Pencarian nama proyek/klien dan pemilih invoice berjalan pada katalog mock D-2.
Provider D-2 HTTP masih membutuhkan kesepakatan daftar invoice dan pencarian nama.
Login UI memakai akun demo. Verifikasi token/peran API sudah tersedia, tetapi
pengujian bersama penerbit token F-1 dan konsumen E-2 asli belum dinyatakan selesai.

Kontrak mesin yang dapat diuji adalah [openapi.yaml](../../openapi.yaml). Respons dan panduan tersedia
di [RESPON_API.md](../api/RESPON_API.md) dan [POSTMAN.md](../api/POSTMAN.md). Adendum ini tidak mengubah bentuk respons atau
menambahkan endpoint di luar implementasi yang sudah ada.
