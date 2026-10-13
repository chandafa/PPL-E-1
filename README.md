<p align="center">
  <img src="docs/assets/thumbnail.svg" alt="E-1 Pajak Proyek REST API: pencatatan e-Faktur dan pajak proyek" width="100%">
</p>

<h1 align="center">🧾 E-1 Pajak Proyek API</h1>

<p align="center">
  <b>Pencatatan e-Faktur dan pajak proyek</b> untuk ERP PT Anaheim Nimbus Universal<br>
  Capstone PPL · Kelas E · Kelompok 1
</p>

<p align="center">
  <img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white">
  <img alt="PHP 8.4" src="https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white">
  <img alt="PostgreSQL" src="https://img.shields.io/badge/PostgreSQL-16%2B-4169E1?style=for-the-badge&logo=postgresql&logoColor=white">
  <img alt="OpenAPI 3.1" src="https://img.shields.io/badge/OpenAPI-3.1-6BA539?style=for-the-badge&logo=openapiinitiative&logoColor=white">
  <img alt="Postman" src="https://img.shields.io/badge/Postman-siap%20pakai-FF6C37?style=for-the-badge&logo=postman&logoColor=white">
  <img alt="100 uji lulus" src="https://img.shields.io/badge/uji-100%20lulus-2EA44F?style=for-the-badge">
</p>

---

<a id="daftar-isi"></a>
## 📑 Daftar isi

1. [🎯 Tentang proyek](#tentang-proyek)
2. [✨ Fitur utama](#fitur-utama)
3. [🗺️ Alur proses](#alur-proses)
4. [🧰 Teknologi dan prasyarat](#teknologi)
5. [🚀 Instalasi dan menjalankan](#instalasi)
6. [⚙️ Konfigurasi `.env`](#konfigurasi)
7. [🔐 Login dan hak akses](#login)
8. [📡 Daftar 11 API](#daftar-api)
9. [🧪 Tutorial menguji setiap API](#tutorial)
   - [Persiapan pengujian](#t-persiapan) · [Login](#t-login)
   - [API-01 Pratinjau ekstraksi](#t-api01) · [API-02 Simpan faktur / pengganti](#t-api02)
   - [API-03 Cari faktur](#t-api03) · [API-04 Detail faktur](#t-api04)
   - [API-05 Koreksi / batalkan](#t-api05) · [API-06 Riwayat](#t-api06)
   - [API-07 Rekap](#t-api07) · [API-08 Catat pembayaran](#t-api08) · [API-11 Lengkapi pembayaran](#t-api11)
   - [API-09 Ekspor](#t-api09) · [API-10 Ringkasan](#t-api10)
   - [Variasi data lain](#t-variasi) · [Menguji lewat Postman](#t-postman)
10. [❌ Kode kesalahan](#kode-kesalahan)
11. [📏 Aturan bisnis](#aturan-bisnis)
12. [✅ Pengujian otomatis](#pengujian-otomatis)
13. [🗂️ Struktur proyek](#struktur)
14. [🧩 Integrasi D-2 dan ekstraksi PDF](#integrasi)
15. [📝 Keputusan dan hal terbuka](#keputusan)
16. [🛠️ Pemecahan masalah](#troubleshooting)
17. [👥 Tim](#tim)

---

<a id="tentang-proyek"></a>
## 🎯 Tentang proyek

Modul **E-1** mencatat **Faktur Pajak Keluaran (e-Faktur)** dari berkas PDF yang dibuat di luar sistem, menautkannya ke invoice proyek
dari **D-2**, lalu merekap pajak per masa pajak sampai pembayarannya terdokumentasi.

| Peran modul lain | Hubungan dengan E-1 |
|---|---|
| 🔑 **F-1** (identitas) | menerbitkan token Bearer dan peran pengguna |
| 📄 **D-2** (kontrak dan invoice) | sumber data invoice yang dipilih saat mencatat faktur |
| 📊 **E-2** (arus kas dan dasbor) | konsumen API ringkasan pajak (API-KE01-10) |

> ℹ️ E-1 **tidak menerbitkan** e-Faktur dan **tidak terhubung langsung ke DJP**. E-1 hanya mencatat dari PDF.

<a id="fitur-utama"></a>
## ✨ Fitur utama

- 📥 **Unggah PDF e-Faktur** lalu data (NSFP, tanggal, DPP, PPN, PPnBM, item) dibaca otomatis.
- 🔗 **Satu invoice : satu faktur aktif**; NSFP tidak boleh ganda.
- ✏️ **Koreksi, pembatalan, dan faktur pengganti** dengan riwayat siapa, kapan, sebelum, dan sesudah (tidak ada hapus permanen).
- 🔎 **Cari dan filter**: nomor faktur, proyek, klien, masa pajak, status, plus paging.
- 📅 **Rekap bulanan**: hanya faktur aktif yang dijumlahkan; faktur dibatalkan/terganti tetap terlihat.
- 💳 **Pembayaran pajak**: jumlah setor, billing, NTPN, e-Billing, bukti; Draft lalu Terkonfirmasi; selisih selalu ditampilkan.
- 📤 **Ekspor** rekap ke Excel dan PDF dengan isi yang sama dengan rekap.
- 🔌 **API ringkasan** agregat untuk modul lain (E-2).

<a id="alur-proses"></a>
## 🗺️ Alur proses

<p align="center">
  <img src="docs/assets/alur-proses.svg" alt="Alur proses E-1 dari login sampai ringkasan pajak" width="100%">
</p>

<a id="teknologi"></a>
## 🧰 Teknologi dan prasyarat

| Komponen | Versi |
|---|---|
| PHP | 8.4 (ekstensi `pdo_pgsql`, `mbstring`, `bcmath`, `gd`, `zip`, `fileinfo`, `openssl`) |
| Composer | 2.x |
| PostgreSQL | 16 atau lebih baru (diuji pada 18) |
| Laravel | 13 |
| Alat uji (opsional) | Postman, atau `curl` (Git Bash) |

Pustaka utama: `firebase/php-jwt` (token F-1), `smalot/pdfparser` (baca PDF), `maatwebsite/excel` (Excel), `barryvdh/laravel-dompdf` (PDF).

<a id="instalasi"></a>
## 🚀 Instalasi dan menjalankan

**1️⃣ Ambil dependensi**

```bash
composer install
```

**2️⃣ Siapkan `.env`**

```bash
cp .env.example .env
php artisan key:generate
```

**3️⃣ Buat role dan database PostgreSQL** (jalankan sekali; akan meminta password superuser `postgres`)

```bash
psql -U postgres -c "CREATE ROLE e1_app LOGIN PASSWORD 'e1_dev_pass' CREATEDB;"
psql -U postgres -c "CREATE DATABASE e1_pajak OWNER e1_app;"
psql -U postgres -c "CREATE DATABASE e1_pajak_test OWNER e1_app;"
```

> 💡 Di Windows, `psql` biasanya ada di `C:\Program Files\PostgreSQL\<versi>\bin\psql.exe`.
> Nama role, database, dan password di atas sesuai bawaan `.env.example` (`DB_USERNAME`, `DB_DATABASE`, `DB_PASSWORD`).

**4️⃣ Buat tabel**

```bash
php artisan migrate:fresh          # database kosong (disarankan untuk mengikuti tutorial)
# atau, bila ingin data demo siap pakai:
php artisan migrate:fresh --seed
```

**5️⃣ Jalankan server**

```bash
composer serve                     # http://127.0.0.1:8080
```

**6️⃣ Periksa**

```bash
curl http://127.0.0.1:8080/up      # HTTP 200 berarti aplikasi hidup
```

> ⚠️ Gunakan `composer serve`, bukan `php artisan serve`: perintah ini menaikkan batas unggah PHP (`upload_max_filesize=6M`)
> sehingga berkas sampai 5 MB (batas aplikasi) dapat diterima. Batas bawaan PHP hanya 2 MB.

**7️⃣ Berkas PDF contoh** ada di [`docs/postman/samples/`](docs/postman/samples). Bila hilang, buat ulang: `php artisan demo:samples`.

<a id="konfigurasi"></a>
## ⚙️ Konfigurasi `.env`

| Variabel | Bawaan | Fungsi |
|---|---|---|
| `DB_*` | `127.0.0.1:5432`, `e1_pajak`, `e1_app` | koneksi PostgreSQL |
| `F1_JWT_ALGO` | `HS256` | algoritma token (`HS256` atau `RS256`) |
| `F1_JWT_SECRET` | rahasia contoh | rahasia bersama HS256 (ganti, minimal 32 karakter) |
| `F1_JWT_PUBLIC_KEY_PATH` | kosong | kunci publik F-1 untuk `RS256` |
| `F1_JWT_ISSUER`, `F1_JWT_AUDIENCE` | `f1-identity`, `erp-anaheim` | `iss` dan `aud` yang diterima |
| `F1_CLAIM_AKUN_ID`, `F1_CLAIM_ROLE` | `akunId`, `kodePeran` | nama klaim identitas dan peran |
| `E1_ALLOWED_ROLES` | `Admin,CEO,COO` | peran yang berhak mengakses modul |
| `E1_SUMMARY_EXTRA_ROLES` | kosong | peran layanan (mis. `LayananE2`) yang hanya boleh membaca API-KE01-10 |
| `E1_DEV_LOGIN` | `true` di contoh | menyalakan login tiruan F-1 (hanya lokal) |
| `D2_DRIVER` | `mock` | `mock` (data fiktif) atau `http` (layanan D-2 sebenarnya) |
| `D2_BASE_URL`, `D2_INVOICE_PATH` | kosong | alamat D-2 bila `D2_DRIVER=http` |
| `E1_UPLOAD_MAX_KB` | `5120` | batas ukuran PDF (5 MB) |

Setelah mengubah `.env`, bila perilaku tidak berubah jalankan `php artisan config:clear`.

<a id="login"></a>
## 🔐 Login dan hak akses

E-1 **tidak memiliki endpoint login**: login dimiliki **F-1**. Semua API E-1 meminta header:

```
Authorization: Bearer <token F-1>
```

Token diperiksa: tanda tangan, `exp` (wajib), `iss`, dan `aud`.

| Kondisi | Hasil |
|---|---|
| Token tidak ada, rusak, atau kedaluwarsa | **401** `UNAUTHORIZED` |
| Token sah, peran bukan Admin/CEO/COO | **403** `FORBIDDEN` |
| Peran Admin, CEO, atau COO | akses penuh (hak sama sementara; matriks per aksi menunggu Q-19) |

### Login tiruan untuk lokal (Postman/curl)

Karena F-1 belum tersedia, ada login tiruan (aktif bila `E1_DEV_LOGIN=true`, **tidak pernah aktif di production**):

```http
POST /api/dev/f1/login
Content-Type: application/json

{ "akunId": "AKN-0002", "kodePeran": "Admin" }
```

Alternatif lewat terminal: `php artisan f1:token AKN-0002 Admin` (mencetak token yang sama).

<a id="daftar-api"></a>
## 📡 Daftar 11 API

Semua di bawah awalan `/api/v1`. Sukses selalu **HTTP 200**. Kontrak lengkap: [`openapi.yaml`](openapi.yaml), contoh respons: [`examples/`](examples).

| ID | Metode dan path | Fungsi |
|---|---|---|
| **API-KE01-01** | `POST /tax-invoices/extractions` | 🔍 Pratinjau hasil baca PDF (tidak menyimpan) |
| **API-KE01-02** | `POST /tax-invoices/outgoing` | 💾 Simpan faktur keluaran atau faktur pengganti |
| **API-KE01-03** | `GET /tax-invoices` | 🔎 Cari dan filter faktur |
| **API-KE01-04** | `GET /tax-invoices/{taxInvoiceId}` | 📄 Detail faktur + item + tautan asal/pengganti |
| **API-KE01-05** | `PATCH /tax-invoices/{taxInvoiceId}` | ✏️ Koreksi atau batalkan faktur |
| **API-KE01-06** | `GET /tax-invoices/{taxInvoiceId}/history` | 🕘 Riwayat perubahan |
| **API-KE01-07** | `GET /tax-recaps/{taxPeriod}` | 📅 Rekap per masa pajak |
| **API-KE01-08** | `POST /tax-payments` | 💳 Catat pembayaran pajak (Draft/Terkonfirmasi) |
| **API-KE01-09** | `GET /tax-recaps/{taxPeriod}/export?format=xlsx\|pdf` | 📤 Ekspor rekap |
| **API-KE01-10** | `GET /tax/summary?taxPeriod=&projectId=` | 📊 Ringkasan pajak untuk modul lain (E-2) |
| **API-KE01-11** | `PATCH /tax-payments/{taxPaymentId}` | ✅ Lengkapi pembayaran jadi Terkonfirmasi |

Bentuk respons (aturan bersama):

```jsonc
// sukses
{ "code": "200", "message": "Faktur tersimpan", "data": { /* ... */ } }

// gagal
{ "code": "409", "message": "NSFP sudah terdaftar", "data": null, "errorCode": "DUPLICATE_NSFP" }
```

Nominal selalu **string** (`"11000000.00"`), tanggal `YYYY-MM-DD`, masa pajak `YYYY-MM`, waktu RFC 3339 UTC (`...Z`), ID UUID v7.

---

<a id="tutorial"></a>
## 🧪 Tutorial menguji setiap API

Ikuti berurutan pada **database kosong** (`php artisan migrate:fresh`) agar angka sama dengan contoh. ID pada contoh respons
akan berbeda di komputer Anda; gunakan ID dari respons Anda.

<a id="t-persiapan"></a>
### 🧱 Persiapan pengujian

Gunakan **Git Bash** (atau terminal Linux/macOS) dari folder proyek. Di Windows PowerShell tulis `curl.exe`, bukan `curl`.

```bash
BASE=http://127.0.0.1:8080
SAMPLES=docs/postman/samples

# fungsi kecil: ambil nilai teks "kunci":"nilai" pertama dari JSON yang masuk lewat pipa
idof() { grep -o "\"$1\":\"[^\"]*\"" | head -1 | cut -d'"' -f4; }
```

<a id="t-login"></a>
### 🔑 Login

```bash
TOKEN=$(curl -s -X POST $BASE/api/dev/f1/login \
  -H "Content-Type: application/json" \
  -d '{"akunId":"AKN-0002","kodePeran":"Admin"}' | idof accessToken)

AUTH="Authorization: Bearer $TOKEN"
echo "$TOKEN"          # harus berupa teks panjang berawalan eyJ...
```

| Field body | Contoh | Wajib |
|---|---|---|
| `akunId` | `AKN-0002` | ya |
| `kodePeran` | `Admin`, `CEO`, `COO` | ya |
| `ttl` | `3600` (detik, 60 sampai 86400) | tidak |

Kasus gagal: tanpa `kodePeran` → **400** `INVALID_LOGIN`. Uji peran tak berwenang:

```bash
TOKEN_CTO=$(curl -s -X POST $BASE/api/dev/f1/login -H "Content-Type: application/json" \
  -d '{"akunId":"AKN-0009","kodePeran":"CTO"}' | idof accessToken)
curl -s $BASE/api/v1/tax-invoices -H "Authorization: Bearer $TOKEN_CTO"     # 403 FORBIDDEN
curl -s $BASE/api/v1/tax-invoices                                           # 401 UNAUTHORIZED
```

---

<a id="t-api01"></a>
### 🔍 API-KE01-01 · Pratinjau ekstraksi PDF e-Faktur

**Tujuan:** memeriksa hasil baca PDF sebelum disimpan. **Tidak menyimpan apa pun.**
**Request:** `POST /api/v1/tax-invoices/extractions` · body **multipart/form-data**

| Field | Contoh | Wajib |
|---|---|---|
| `invoiceId` | `inv-demo-001` | ya |
| `eFakturFile` | berkas PDF (maks 5 MB) | ya |

```bash
curl -s -X POST $BASE/api/v1/tax-invoices/extractions -H "$AUTH" \
  -F invoiceId=inv-demo-001 \
  -F "eFakturFile=@$SAMPLES/efaktur-inv-demo-001.pdf"
```

<details>
<summary>✅ Contoh respons 200</summary>

```json
{
  "code": "200",
  "message": "Hasil ekstraksi (belum tersimpan)",
  "data": {
    "invoiceId": "inv-demo-001",
    "taxInvoiceNumber": "010.000-26.12345678",
    "taxInvoiceDate": "2026-11-01",
    "taxPeriod": "2026-11",
    "dpp": "100000000.00",
    "ppn": "11000000.00",
    "ppnbm": "0.00",
    "items": [
      {
        "itemCode": "620100",
        "itemName": "Jasa Pengembangan Portal ERP Termin 1",
        "saleReplacementAdvanceTermAmount": "100000000.00",
        "discountAmount": "0.00",
        "receivedAdvanceAmount": "0.00"
      }
    ]
  }
}
```
</details>

**Coba kasus gagal**

| Ubah menjadi | Hasil |
|---|---|
| `eFakturFile=@$SAMPLES/bukan-pdf.txt` | **400** `INVALID_FILE` |
| `invoiceId=inv-demo-004` + `eFakturFile=@$SAMPLES/e-billing.pdf` (PDF sah, bukan faktur) | **400** `EXTRACTION_FAILED` |
| tanpa `invoiceId` | **400** `INVALID_INVOICE_ID` |
| `invoiceId=inv-tidak-ada` | **404** `INVOICE_NOT_FOUND` |
| `invoiceId=inv-demo-001` setelah API-02 dijalankan | **409** `INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE` |
| `invoiceId=inv-demo-004` + PDF `efaktur-inv-demo-001.pdf` setelah API-02 | **409** `DUPLICATE_NSFP` |

<a id="t-api02"></a>
### 💾 API-KE01-02 · Simpan faktur keluaran atau faktur pengganti

**Tujuan:** menyimpan faktur dari PDF dan menautkannya ke invoice D-2.
**Request:** `POST /api/v1/tax-invoices/outgoing` · **multipart/form-data**

| Field | Contoh | Wajib |
|---|---|---|
| `invoiceId` | `inv-demo-001` | ya |
| `eFakturFile` | berkas PDF | ya |
| `replacesTaxInvoiceId` | ID faktur asal (hanya untuk faktur pengganti) | tidak |

**Langkah A: simpan faktur biasa.** `tee /dev/stderr` menampilkan respons sambil menyimpan ID-nya.

```bash
FAKTUR_A=$(curl -s -X POST $BASE/api/v1/tax-invoices/outgoing -H "$AUTH" \
  -F invoiceId=inv-demo-001 \
  -F "eFakturFile=@$SAMPLES/efaktur-inv-demo-001.pdf" | tee /dev/stderr | idof taxInvoiceId)
echo; echo "FAKTUR_A=$FAKTUR_A"
```

**Langkah B: lengkapi data uji** (satu perintah per invoice):

```bash
# B: dua item, masa pajak 2026-11
FAKTUR_B=$(curl -s -X POST $BASE/api/v1/tax-invoices/outgoing -H "$AUTH" \
  -F invoiceId=inv-demo-002 -F "eFakturFile=@$SAMPLES/efaktur-inv-demo-002.pdf" | idof taxInvoiceId)

# C: akan diganti
FAKTUR_C=$(curl -s -X POST $BASE/api/v1/tax-invoices/outgoing -H "$AUTH" \
  -F invoiceId=inv-demo-003 -F "eFakturFile=@$SAMPLES/efaktur-inv-demo-003.pdf" | idof taxInvoiceId)

# D dan E: masa pajak 2026-12
FAKTUR_D=$(curl -s -X POST $BASE/api/v1/tax-invoices/outgoing -H "$AUTH" \
  -F invoiceId=inv-demo-004 -F "eFakturFile=@$SAMPLES/efaktur-inv-demo-004.pdf" | idof taxInvoiceId)
curl -s -X POST $BASE/api/v1/tax-invoices/outgoing -H "$AUTH" \
  -F invoiceId=inv-demo-005 -F "eFakturFile=@$SAMPLES/efaktur-inv-demo-005.pdf" > /dev/null
```

**Langkah C: faktur pengganti** menggantikan faktur C. `invoiceId` HARUS sama dengan faktur asal.

```bash
FAKTUR_C2=$(curl -s -X POST $BASE/api/v1/tax-invoices/outgoing -H "$AUTH" \
  -F invoiceId=inv-demo-003 \
  -F "eFakturFile=@$SAMPLES/efaktur-inv-demo-003-pengganti.pdf" \
  -F "replacesTaxInvoiceId=$FAKTUR_C" | tee /dev/stderr | idof taxInvoiceId)
```

<details>
<summary>✅ Contoh respons 200 (langkah A)</summary>

```json
{
  "code": "200",
  "message": "Faktur tersimpan",
  "data": {
    "taxInvoiceId": "01a135e1-4265-704e-859b-50ef2d70a4b1",
    "type": "OUTGOING",
    "invoiceId": "inv-demo-001",
    "vendorName": null,
    "status": "NORMAL",
    "replacesTaxInvoiceId": null,
    "taxInvoiceNumber": "010.000-26.12345678",
    "taxInvoiceDate": "2026-11-01",
    "taxPeriod": "2026-11",
    "dpp": "100000000.00",
    "ppn": "11000000.00",
    "ppnbm": "0.00",
    "items": [ { "itemCode": "620100", "itemName": "Jasa Pengembangan Portal ERP Termin 1", "...": "..." } ],
    "createdByAkunId": "AKN-0002",
    "createdAt": "2026-10-13T15:28:37Z"
  }
}
```
</details>

Pada langkah C, `status` menjadi `REPLACEMENT`, `replacesTaxInvoiceId` terisi, dan faktur C berstatus terganti (tidak dihitung pada rekap).

**Coba kasus gagal**

| Ubah menjadi | Hasil |
|---|---|
| catat ulang `invoiceId=inv-demo-001` | **409** `INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE` |
| `invoiceId=inv-demo-004` + PDF `efaktur-inv-demo-001.pdf` | **409** `DUPLICATE_NSFP` |
| pengganti dengan `invoiceId=inv-demo-002` tetapi `replacesTaxInvoiceId=$FAKTUR_A` | **400** `REPLACEMENT_INVOICE_MISMATCH` |
| `replacesTaxInvoiceId=0191f3a0-7c1e-7a52-9d3b-4f6e2a1b8c99` | **404** `TAX_INVOICE_NOT_FOUND` |
| mengganti `$FAKTUR_C` yang sudah diganti | **409** `INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE` |
| `invoiceId=inv-tidak-ada` | **404** `INVOICE_NOT_FOUND` |

<a id="t-api03"></a>
### 🔎 API-KE01-03 · Cari dan filter faktur

**Request:** `GET /api/v1/tax-invoices` · semua parameter query opsional

| Parameter | Contoh | Keterangan |
|---|---|---|
| `taxInvoiceNumber` | `12345678` | sebagian nomor faktur/NSFP |
| `project` | `prj-demo-002` atau `gudang` | ID atau nama proyek |
| `client` | `Lintas` | ID atau nama klien |
| `taxPeriod` | `2026-11` | masa pajak |
| `status` | `NORMAL`, `REPLACEMENT`, `CANCELLED` | status faktur |
| `page`, `pageSize` | `1`, `20` | paging (maks `pageSize` 100) |

```bash
curl -s "$BASE/api/v1/tax-invoices" -H "$AUTH"                                    # semua (6 faktur)
curl -s "$BASE/api/v1/tax-invoices?taxPeriod=2026-11&status=NORMAL" -H "$AUTH"    # 3 faktur
curl -s "$BASE/api/v1/tax-invoices?project=gudang" -H "$AUTH"                     # cari nama proyek
curl -s "$BASE/api/v1/tax-invoices?client=Lintas&pageSize=2&page=1" -H "$AUTH"    # klien + paging
```

<details>
<summary>✅ Contoh respons 200</summary>

```json
{
  "code": "200",
  "message": "Daftar faktur",
  "data": {
    "items": [
      {
        "taxInvoiceId": "01a135e1-4668-7100-9380-31dd522f977b",
        "type": "OUTGOING",
        "taxInvoiceNumber": "010.000-26.12345680",
        "taxInvoiceDate": "2026-11-12",
        "invoiceId": "inv-demo-003",
        "projectId": "prj-demo-002",
        "clientId": "0191f3a0-7c1e-7a52-9d3b-4f6e2a1b8c01",
        "vendorName": null,
        "taxPeriod": "2026-11",
        "dpp": "75000000.00",
        "ppn": "8250000.00",
        "status": "NORMAL",
        "replacedByTaxInvoiceId": "01a135e1-490b-728a-8fa7-85ca00b80ad5"
      }
    ],
    "pagination": { "page": 1, "pageSize": 20, "totalItems": 3 }
  }
}
```
</details>

**Coba kasus gagal:** `?taxPeriod=2026-13`, `?status=APPROVED`, `?page=0`, `?pageSize=101` → semuanya **400** `INVALID_FILTER`.
Pencarian tanpa hasil tetap **200** dengan `items: []`.

<a id="t-api04"></a>
### 📄 API-KE01-04 · Detail faktur

**Request:** `GET /api/v1/tax-invoices/{taxInvoiceId}`

```bash
curl -s $BASE/api/v1/tax-invoices/$FAKTUR_A -H "$AUTH"
curl -s $BASE/api/v1/tax-invoices/$FAKTUR_C -H "$AUTH"      # lihat replacedByTaxInvoiceId terisi
```

Respons memuat `taxInvoice`, `items`, `replacesTaxInvoiceId`, dan `replacedByTaxInvoiceId`.

**Kasus gagal:** `curl -s $BASE/api/v1/tax-invoices/0191f3a0-7c1e-7a52-9d3b-4f6e2a1b8c99 -H "$AUTH"` → **404** `TAX_INVOICE_NOT_FOUND`
(ID bukan UUID, mis. `abc`, juga 404).

<a id="t-api05"></a>
### ✏️ API-KE01-05 · Koreksi atau batalkan faktur

**Request:** `PATCH /api/v1/tax-invoices/{taxInvoiceId}` · body **JSON**

| Field | Contoh | Keterangan |
|---|---|---|
| `changes` | `{ "taxInvoiceDate": "2026-11-10" }` | objek perubahan (wajib) |
| `note` | `"Koreksi tanggal faktur"` | alasan, maks 500 karakter |

Field `changes` yang boleh: `taxInvoiceNumber`, `taxInvoiceDate`, `taxPeriod`, `dpp`, `ppn`, `ppnbm` (nominal **string**), `items`, atau `status` = `CANCELLED`.

**Koreksi tanggal:**

```bash
curl -s -X PATCH $BASE/api/v1/tax-invoices/$FAKTUR_A -H "$AUTH" -H "Content-Type: application/json" \
  -d '{"changes":{"taxInvoiceDate":"2026-11-10"},"note":"Koreksi tanggal faktur"}'
```

**Batalkan faktur D** (hanya `status`; tidak boleh digabung dengan koreksi lain):

```bash
curl -s -X PATCH $BASE/api/v1/tax-invoices/$FAKTUR_D -H "$AUTH" -H "Content-Type: application/json" \
  -d '{"changes":{"status":"CANCELLED"},"note":"Salah klien"}'
```

<details>
<summary>✅ Contoh respons 200 (koreksi tanggal)</summary>

```json
{
  "code": "200",
  "message": "Faktur diperbarui",
  "data": {
    "taxInvoice": {
      "taxInvoiceId": "01a135e1-4265-704e-859b-50ef2d70a4b1",
      "type": "OUTGOING",
      "invoiceId": "inv-demo-001",
      "projectId": "prj-demo-001",
      "clientId": null,
      "vendorName": null,
      "taxInvoiceNumber": "010.000-26.12345678",
      "taxInvoiceDate": "2026-11-10",
      "taxPeriod": "2026-11",
      "dpp": "100000000.00",
      "ppn": "11000000.00",
      "ppnbm": "0.00",
      "status": "NORMAL",
      "pdfUri": "/files/tax/e-faktur/01a135e1-4265-704e-859b-50ef2d70a4b1.pdf",
      "createdByAkunId": "AKN-0002",
      "createdAt": "2026-10-13T15:28:37Z",
      "updatedAt": "2026-10-13T15:28:41Z"
    },
    "auditId": "01a135e1-52b5-70f8-97d6-e3d82503fcd5"
  }
}
```
</details>

> 💡 Ingin mencoba koreksi nominal, pindah masa pajak, atau ganti item? Lihat [Variasi data lain](#t-variasi); jalankan **setelah**
> tutorial selesai karena mengubah angka rekap.

**Coba kasus gagal**

| `changes` / kondisi | Hasil |
|---|---|
| `{"invoiceId":"inv-demo-005"}` (field tidak boleh diubah) | **400** `INVALID_CHANGE` |
| `{"dpp":90000000}` (angka, bukan string) | **400** `INVALID_CHANGE` |
| `{"status":"CANCELLED","dpp":"1.00"}` (digabung) | **400** `INVALID_CHANGE` |
| `{"dpp":"100000000.00"}` (sama dengan nilai sekarang) | **400** `INVALID_CHANGE` |
| ubah/batalkan faktur yang sudah `CANCELLED` | **409** `TAX_INVOICE_ALREADY_CANCELLED` |
| `{"taxInvoiceNumber":"010.000-26.12345678"}` pada faktur lain | **409** `DUPLICATE_NSFP` |
| ID faktur tidak dikenal | **404** `TAX_INVOICE_NOT_FOUND` |

> ℹ️ Membatalkan **faktur pengganti** melepas tautannya sehingga faktur asal berlaku kembali (riwayat keduanya dicatat).

<a id="t-api06"></a>
### 🕘 API-KE01-06 · Riwayat perubahan

**Request:** `GET /api/v1/tax-invoices/{taxInvoiceId}/history`

```bash
curl -s $BASE/api/v1/tax-invoices/$FAKTUR_A/history -H "$AUTH"
```

<details>
<summary>✅ Contoh respons 200</summary>

```json
{
  "code": "200",
  "message": "Riwayat faktur",
  "data": {
    "history": [
      {
        "auditId": "01a135e1-428d-7364-8896-b4a66ff8736d",
        "action": "CREATE",
        "actorAkunId": "AKN-0002",
        "changedAt": "2026-10-13T15:28:37Z",
        "beforeSnapshot": null,
        "afterSnapshot": { "taxInvoiceNumber": "010.000-26.12345678", "taxInvoiceDate": "2026-11-01", "...": "..." },
        "note": null
      },
      {
        "auditId": "01a135e1-52b5-70f8-97d6-e3d82503fcd5",
        "action": "UPDATE",
        "actorAkunId": "AKN-0002",
        "changedAt": "2026-10-13T15:28:41Z",
        "beforeSnapshot": { "taxInvoiceDate": "2026-11-01" },
        "afterSnapshot": { "taxInvoiceDate": "2026-11-10" },
        "note": "Koreksi tanggal faktur"
      }
    ]
  }
}
```
</details>

Aksi riwayat: `CREATE`, `UPDATE`, `STATUS_CHANGE` (batal, penggantian). Riwayat bersifat **append-only**. Faktur tidak dikenal → **404** `TAX_INVOICE_NOT_FOUND`.

<a id="t-api07"></a>
### 📅 API-KE01-07 · Rekap per masa pajak

**Request:** `GET /api/v1/tax-recaps/{taxPeriod}` (`taxPeriod` berformat `YYYY-MM`)

```bash
curl -s $BASE/api/v1/tax-recaps/2026-11 -H "$AUTH"
curl -s $BASE/api/v1/tax-recaps/2026-12 -H "$AUTH"      # faktur D dibatalkan: tampil tetapi tidak dihitung
curl -s $BASE/api/v1/tax-recaps/2026-01 -H "$AUTH"      # kosong: rekap nol (200), bukan error
```

<details>
<summary>✅ Contoh respons 200 (2026-11, dipangkas)</summary>

```json
{
  "code": "200",
  "message": "Rekap masa pajak",
  "data": {
    "recap": {
      "taxPeriod": "2026-11",
      "invoiceCount": 3,
      "totalDpp": "420000000.00",
      "totalPpn": "46200000.00",
      "totalPpnbm": "0.00",
      "amountPaid": "0.00",
      "paymentDifference": "-46200000.00",
      "paymentStatus": "NOT_RECORDED",
      "computedAt": "2026-10-13T15:28:43Z"
    },
    "invoices": [
      { "taxInvoiceNumber": "010.000-26.12345679", "status": "NORMAL", "dpp": "250000000.00", "ppn": "27500000.00", "isCounted": true }
    ],
    "payments": []
  }
}
```
</details>

Cara membaca: 3 faktur dihitung (A, B, C2); faktur asal C tampil dengan `isCounted: false`.
`paymentStatus`: `NOT_RECORDED` (belum ada pembayaran), `INCOMPLETE` (ada Draft), `CONFIRMED` (semua terkonfirmasi).
Masa pajak salah (mis. `2026-13`) → **400** `INVALID_TAX_PERIOD`.

<a id="t-api08"></a>
### 💳 API-KE01-08 · Catat pembayaran pajak

**Request:** `POST /api/v1/tax-payments` · **multipart/form-data**

| Field | Contoh | Wajib |
|---|---|---|
| `taxPeriod` | `2026-11` | ya |
| `taxYear` | `2026` (harus sama dengan tahun masa pajak) | ya |
| `amountPaid` | `46200000.00` | ya |
| `billingId` | `BILL-202611-001` | ya |
| `eBillingFile` | PDF e-Billing | ya |
| `ntpn` | `NTPN-ABC123456` | tidak |
| `paymentProofFile` | PDF bukti pembayaran | tidak |

Tanpa `ntpn` **dan** bukti, catatan tersimpan sebagai **Draft** (`INCOMPLETE`). Bila keduanya ada, langsung `CONFIRMED`.

```bash
# Draft
PAYMENT=$(curl -s -X POST $BASE/api/v1/tax-payments -H "$AUTH" \
  -F taxPeriod=2026-11 -F taxYear=2026 -F amountPaid=46200000.00 -F billingId=BILL-202611-001 \
  -F "eBillingFile=@$SAMPLES/e-billing.pdf" | tee /dev/stderr | idof taxPaymentId)
echo; echo "PAYMENT=$PAYMENT"
```

```bash
# Langsung Terkonfirmasi + kurang bayar (Desember: setor 6.000.000 < PPN 6.600.000)
curl -s -X POST $BASE/api/v1/tax-payments -H "$AUTH" \
  -F taxPeriod=2026-12 -F taxYear=2026 -F amountPaid=6000000.00 -F billingId=BILL-202612-001 \
  -F ntpn=NTPN-XYZ987654 \
  -F "eBillingFile=@$SAMPLES/e-billing.pdf" -F "paymentProofFile=@$SAMPLES/bukti-pembayaran.pdf"
```

<details>
<summary>✅ Contoh respons 200 (Draft)</summary>

```json
{
  "code": "200",
  "message": "Pembayaran tersimpan",
  "data": {
    "taxPaymentId": "01a135e1-59f1-7303-a3fb-48bc787dd906",
    "status": "INCOMPLETE",
    "taxPeriod": "2026-11",
    "paymentDifference": "0.00"
  }
}
```
</details>

`paymentDifference` = jumlah setor dikurangi total PPN (boleh negatif). Pembayaran **tetap tersimpan** walau berbeda.

**Coba kasus gagal**

| Ubah menjadi | Hasil |
|---|---|
| `taxPeriod=2027-01` `taxYear=2027` (belum ada faktur) | **404** `TAX_RECAP_NOT_FOUND` |
| `amountPaid=-1.00` | **400** `INVALID_PAYMENT` |
| `taxYear=2025` untuk `taxPeriod=2026-11` | **400** `INVALID_PAYMENT` |
| tanpa `eBillingFile`, atau berkas bukan PDF | **400** `INVALID_PAYMENT` |

<a id="t-api11"></a>
### ✅ API-KE01-11 · Lengkapi pembayaran menjadi Terkonfirmasi

**Request:** `PATCH /api/v1/tax-payments/{taxPaymentId}` · **multipart/form-data**

| Field | Contoh | Wajib |
|---|---|---|
| `ntpn` | `NTPN-ABC123456` | ya |
| `paymentProofFile` | PDF bukti pembayaran | ya |

```bash
curl -s -X PATCH $BASE/api/v1/tax-payments/$PAYMENT -H "$AUTH" \
  -F ntpn=NTPN-ABC123456 \
  -F "paymentProofFile=@$SAMPLES/bukti-pembayaran.pdf"
```

<details>
<summary>✅ Contoh respons 200</summary>

```json
{
  "code": "200",
  "message": "Pembayaran Terkonfirmasi",
  "data": {
    "taxPaymentId": "01a135e1-59f1-7303-a3fb-48bc787dd906",
    "status": "CONFIRMED",
    "paymentStatus": "CONFIRMED"
  }
}
```
</details>

Setelah ini `GET /api/v1/tax-recaps/2026-11` menunjukkan `paymentStatus: "CONFIRMED"`.

**Coba kasus gagal:** tanpa `paymentProofFile` atau tanpa `ntpn` → **400** `INVALID_PAYMENT`; ID pembayaran tidak dikenal → **404** `TAX_PAYMENT_NOT_FOUND`.

<a id="t-api09"></a>
### 📤 API-KE01-09 · Ekspor rekap (Excel / PDF)

**Request:** `GET /api/v1/tax-recaps/{taxPeriod}/export?format=xlsx|pdf` · respons berupa **berkas**, bukan JSON.

```bash
curl -s "$BASE/api/v1/tax-recaps/2026-11/export?format=xlsx" -H "$AUTH" -o rekap-pajak-2026-11.xlsx
curl -s "$BASE/api/v1/tax-recaps/2026-11/export?format=pdf"  -H "$AUTH" -o rekap-pajak-2026-11.pdf
```

Berkas Excel berisi tiga lembar: **Ringkasan**, **Faktur**, **Pembayaran**; isinya sama dengan rekap (API-KE01-07).
Format selain `xlsx`/`pdf` (mis. `csv`) → **400** `INVALID_FORMAT`; masa pajak salah → **400** `INVALID_TAX_PERIOD`.

<a id="t-api10"></a>
### 📊 API-KE01-10 · Ringkasan pajak untuk modul lain (E-2)

**Request:** `GET /api/v1/tax/summary?taxPeriod=&projectId=` (`projectId` opsional)

```bash
curl -s "$BASE/api/v1/tax/summary?taxPeriod=2026-11" -H "$AUTH"
curl -s "$BASE/api/v1/tax/summary?taxPeriod=2026-11&projectId=prj-demo-002" -H "$AUTH"
```

<details>
<summary>✅ Contoh respons 200</summary>

```json
{
  "code": "200",
  "message": "Ringkasan pajak",
  "data": {
    "taxPeriod": "2026-11",
    "invoiceCount": 3,
    "totalDpp": "420000000.00",
    "totalPpn": "46200000.00",
    "totalPpnbm": "0.00",
    "paymentStatus": "CONFIRMED"
  }
}
```
</details>

Hanya agregat: **tanpa** NTPN, kode billing, atau lampiran. Masa pajak tanpa data menghasilkan nilai nol (bukan angka karangan).

**Uji sebagai konsumen layanan (E-2):** isi `E1_SUMMARY_EXTRA_ROLES=LayananE2` pada `.env`, restart server, lalu:

```bash
TOKEN_E2=$(curl -s -X POST $BASE/api/dev/f1/login -H "Content-Type: application/json" \
  -d '{"akunId":"SVC-E2","kodePeran":"LayananE2"}' | idof accessToken)
curl -s "$BASE/api/v1/tax/summary?taxPeriod=2026-11" -H "Authorization: Bearer $TOKEN_E2"   # 200
curl -s "$BASE/api/v1/tax-invoices" -H "Authorization: Bearer $TOKEN_E2"                    # 403 (hanya ringkasan)
```

**Kasus gagal:** `taxPeriod=2026-13` atau tanpa `taxPeriod` → **400** `INVALID_TAX_PERIOD`; `projectId=prj-tidak-ada` → **400** `INVALID_PROJECT_ID`; peran tak berwenang → **403** `FORBIDDEN`.

<a id="t-variasi"></a>
### 🔁 Variasi data lain (jalankan paling akhir)

Mengubah angka rekap, sehingga contoh angka di atas tidak lagi berlaku bila dijalankan lebih awal.

```bash
# 1) koreksi nominal faktur A: DPP/PPN lebih kecil
curl -s -X PATCH $BASE/api/v1/tax-invoices/$FAKTUR_A -H "$AUTH" -H "Content-Type: application/json" \
  -d '{"changes":{"dpp":"90000000.00","ppn":"9900000.00"},"note":"Koreksi nilai sesuai e-Faktur final"}'

# 2) rekap 2026-11: total PPN menjadi 45.100.000,00; jumlah setor 46.200.000,00 melebihi PPN (selisih +1.100.000,00)
curl -s $BASE/api/v1/tax-recaps/2026-11 -H "$AUTH"

# 3) ganti seluruh daftar item faktur B (dengan potongan harga pada item kedua)
curl -s -X PATCH $BASE/api/v1/tax-invoices/$FAKTUR_B -H "$AUTH" -H "Content-Type: application/json" \
  -d '{"changes":{"items":[{"itemCode":"620200","itemName":"Lisensi Aplikasi Gudang","saleReplacementAdvanceTermAmount":"150000000.00","discountAmount":"0.00","receivedAdvanceAmount":"0.00"},{"itemCode":"620300","itemName":"Jasa Implementasi dan Pelatihan","saleReplacementAdvanceTermAmount":"100000000.00","discountAmount":"1000000.00","receivedAdvanceAmount":"0.00"}]},"note":"Tambah potongan harga"}'

# 4) pindah faktur A ke masa pajak 2026-12 (rekap lama dan baru dihitung ulang)
curl -s -X PATCH $BASE/api/v1/tax-invoices/$FAKTUR_A -H "$AUTH" -H "Content-Type: application/json" \
  -d '{"changes":{"taxPeriod":"2026-12"},"note":"Dilaporkan bulan berikutnya"}'
curl -s $BASE/api/v1/tax-recaps/2026-12 -H "$AUTH"      # kini memuat faktur A dan E (invoiceCount 2)
```

> ℹ️ Kompensasi lebih/kurang bayar **tidak dihitung** (Q-24 masih terbuka): sistem hanya menyimpan dan menampilkan selisihnya.

---

<a id="t-postman"></a>
### 📮 Menguji lewat Postman

Koleksi siap pakai berisi **62 request** (login, 11 API, variasi data, dan 24 kasus gagal) dengan pemeriksaan otomatis.

1. **Import** [`docs/postman/E1-API.postman_collection.json`](docs/postman/E1-API.postman_collection.json) (opsional: `E1-Local.postman_environment.json`).
2. **Settings > General > Working directory** = folder `docs/postman` (agar berkas PDF di form-data terbaca; bila tidak, pilih ulang berkasnya).
3. Jalankan folder **0. Login**, lalu folder **1** sampai **7** berurutan, atau klik **Run collection**.
4. Ulang dari awal: `php artisan migrate:fresh`.

Panduan lengkap dengan body dan nilai tiap request: [`docs/POSTMAN.md`](docs/POSTMAN.md).

<a id="kode-kesalahan"></a>
## ❌ Kode kesalahan

Pola: `{"code":"<status>","message":"...","data":null,"errorCode":"<KODE>"}`. `code` selalu sama dengan status HTTP.

| Status | `errorCode` | Penyebab |
|---|---|---|
| 400 | `INVALID_FILE` | berkas bukan PDF, hilang, atau lebih dari 5 MB |
| 400 | `EXTRACTION_FAILED` | PDF tidak dapat dibaca sebagai e-Faktur |
| 400 | `INVALID_INVOICE_ID` | `invoiceId` kosong |
| 400 | `REPLACEMENT_INVOICE_MISMATCH` | invoice pengganti berbeda dari faktur asal |
| 400 | `INVALID_FILTER` | filter daftar tidak valid |
| 400 | `INVALID_CHANGE` | field/nilai koreksi tidak valid |
| 400 | `INVALID_TAX_PERIOD` | masa pajak bukan `YYYY-MM` |
| 400 | `INVALID_FORMAT` | format ekspor selain `xlsx`/`pdf` |
| 400 | `INVALID_PROJECT_ID` | `projectId` tidak valid atau tidak dikenal |
| 400 | `INVALID_PAYMENT` | masukan pembayaran tidak valid |
| 401 | `UNAUTHORIZED` | token tidak ada atau tidak sah |
| 403 | `FORBIDDEN` | peran tidak berwenang |
| 404 | `INVOICE_NOT_FOUND` | invoice tidak ada di D-2 |
| 404 | `TAX_INVOICE_NOT_FOUND` | faktur tidak ditemukan |
| 404 | `TAX_RECAP_NOT_FOUND` | masa pajak belum memiliki rekap |
| 404 | `TAX_PAYMENT_NOT_FOUND` | catatan pembayaran tidak ditemukan |
| 409 | `INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE` | invoice sudah punya faktur aktif |
| 409 | `DUPLICATE_NSFP` | NSFP sudah terdaftar |
| 409 | `TAX_INVOICE_ALREADY_CANCELLED` | faktur sudah dibatalkan |
| 503 | `D2_UNAVAILABLE` | layanan D-2 tidak dapat dihubungi (`D2_DRIVER=http`) |

Kesalahan umum lain: `404 NOT_FOUND` (rute tidak ada), `405 METHOD_NOT_ALLOWED`, `413 PAYLOAD_TOO_LARGE`, `500 INTERNAL_ERROR`.

<a id="aturan-bisnis"></a>
## 📏 Aturan bisnis

| Aturan | Sumber | Penerapan |
|---|---|---|
| 1 invoice : 1 faktur aktif | Q-04 | 409 bila invoice sudah punya faktur aktif; pencatatan serentak diserialkan |
| NSFP unik | Q-02 | indeks unik + 409 `DUPLICATE_NSFP` |
| Status Normal / Pengganti / Dibatalkan | Q-07 | `NORMAL`, `REPLACEMENT`, `CANCELLED` |
| Koreksi dan batal tidak menghapus riwayat | Q-05, Q-06 | riwayat append-only (dijaga trigger basis data) |
| Rekap tidak menghitung Dibatalkan dan faktur asal yang diganti | Q-13 | `isCounted` pada daftar rekap |
| Terkonfirmasi = NTPN + bukti | Q-10 | selain itu `INCOMPLETE` (Draft) |
| Selisih jumlah setor vs PPN tetap disimpan | FTR-KE01-05 | `paymentDifference` (bisa negatif); tidak menolak |
| Periode kosong = rekap nol | FTR-KE01-04 | 200 dengan `0.00`, tanpa membuat data |
| PDF maksimum 5 MB | keputusan tim | divalidasi dari isi berkas (`%PDF-`) |

<a id="pengujian-otomatis"></a>
## ✅ Pengujian otomatis

```bash
composer test          # PHPUnit: 100 uji; memerlukan database e1_pajak_test
```

Cakupan: autentikasi dan amplop, setiap operasi (sukses dan seluruh kode kesalahan), ekstraksi PDF, ekspor Excel/PDF, login tiruan,
dan **uji kontrak** yang memvalidasi respons nyata serta seluruh [`examples/*.json`](examples) terhadap skema [`openapi.yaml`](openapi.yaml).

Variabel `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` dapat ditimpa lewat environment (mis. pada CI).
Alur Postman dapat dijalankan dari terminal dengan Newman:

```bash
npx newman run docs/postman/E1-API.postman_collection.json --working-dir docs/postman
```

<a id="struktur"></a>
## 🗂️ Struktur proyek

```
e1-api/
├── app/
│   ├── Http/Controllers/Api/V1/   4 controller tipis (faktur, rekap, pembayaran, ringkasan)
│   ├── Http/Middleware/           AuthenticateF1Token, ParseMultipartBody
│   ├── Services/Tax/              TaxInvoiceService, RecapService, TaxPaymentService, HistoryRecorder
│   ├── Services/EFaktur/          EFakturPdfParser (baca PDF e-Faktur)
│   ├── Services/Invoices/         InvoiceProvider: mock dan http (D-2)
│   ├── Services/Export/           RecapReport, RecapWorkbook (xlsx), RecapExporter (pdf)
│   └── Models/                    5 model E-1
├── database/
│   ├── migrations/                5 tabel + CHECK + trigger append-only
│   ├── seeders/                   DemoSeeder + generator PDF fiktif
│   └── data/d2-invoices.json      data invoice D-2 tiruan
├── docs/
│   ├── assets/                    thumbnail dan diagram alur
│   ├── postman/                   koleksi, environment, dan PDF contoh
│   └── POSTMAN.md                 panduan Postman lengkap
├── examples/                      contoh respons per API
├── openapi.yaml                   kontrak API (sumber kebenaran)
└── tests/                         uji fitur, unit, dan uji kontrak
```

<a id="integrasi"></a>
## 🧩 Integrasi D-2 dan ekstraksi PDF

**D-2 (invoice).** Kontrak D-2 belum final, sehingga `D2_DRIVER=mock` memakai [`database/data/d2-invoices.json`](database/data/d2-invoices.json)
(`inv-demo-001` sampai `inv-demo-005`, mengikuti `Skema_Data_Awal_D2`). Beralih ke D-2 sungguhan cukup dengan `D2_DRIVER=http`,
`D2_BASE_URL`, dan `D2_INVOICE_PATH`; antarmukanya `App\Services\Invoices\InvoiceProvider`.

**Ekstraksi PDF.** `EFakturPdfParser` membaca label Faktur Pajak: *Kode dan Nomor Seri*, *Harga Jual/Penggantian/Uang Muka/Termin*,
*Dikurangi Potongan Harga*, *Dikurangi Uang Muka*, *Dasar Pengenaan Pajak*, *Jumlah PPN*, *Jumlah PPnBM*, dan tanggal. Angka
berformat Indonesia (`100.000.000,00`) maupun internasional diterima.

> ⚠️ **Contoh dokumen asli dari mitra (Q-17) belum diterima.** Parser diuji dengan PDF fiktif; sesuaikan setelah contoh asli tersedia.

<a id="keputusan"></a>
## 📝 Keputusan dan hal terbuka

**Keputusan implementasi**

- Kolom basis data memakai **snake_case**; JSON tetap camelCase sesuai kontrak. ID **UUID v7**.
- `client_id` bertipe teks (skema awal: UUID) agar menerima ID klien berprefiks; `tax_invoice_number` unik.
- `type` (`OUTGOING`/`INCOMING`) dan `vendorName` ada pada kontrak mengikuti Skema Data Awal; API hanya membuat `OUTGOING`.
- Membatalkan faktur pengganti melepas tautannya dan faktur asal berlaku kembali.
- Satu masa pajak boleh memiliki lebih dari satu catatan pembayaran.
- Kompensasi kelebihan/kurang bayar **tidak dihitung** (Q-24 terbuka); hanya selisih yang disimpan dan ditampilkan.

**Menunggu mitra atau kelompok lain**

- **Q-19** hak akses per aksi · **Q-20** faktur masukan · **Q-21** log hapus vs batal · **Q-22** data yang boleh dibagikan ·
  **Q-23** integrasi DJP · **Q-24** kompensasi pembayaran.
- **F-1:** pemilik kunci/JWKS, masa token, nama klaim. **D-2:** path dan field API invoice, `clientId`.

<a id="troubleshooting"></a>
## 🛠️ Pemecahan masalah

| Gejala | Penyebab dan solusi |
|---|---|
| `password authentication failed for user "e1_app"` | role belum dibuat atau password beda; ulangi langkah 3 instalasi dan cek `DB_*` di `.env` |
| `could not find driver` | aktifkan ekstensi `pdo_pgsql` pada `php.ini` |
| Berkas lebih dari 2 MB ditolak `INVALID_FILE` | jalankan dengan `composer serve` (menaikkan batas unggah) |
| `401 UNAUTHORIZED` | token kedaluwarsa (bawaan 1 jam) atau salah rahasia; login ulang |
| `403 FORBIDDEN` | peran bukan Admin/CEO/COO |
| Login tiruan menjawab `404 NOT_FOUND` | set `E1_DEV_LOGIN=true`, lalu `php artisan config:clear` |
| `409` saat mencatat faktur | invoice sudah punya faktur aktif atau NSFP sudah ada; gunakan `php artisan migrate:fresh` untuk mengulang |
| Hasil berbeda dari contoh README | database tidak kosong; jalankan `php artisan migrate:fresh` |
| Port 8080 dipakai | ubah port pada script `serve` di `composer.json` dan `BASE` pada tutorial |
| `curl` di PowerShell aneh | gunakan `curl.exe`, atau pakai Git Bash/Postman |

<a id="tim"></a>
## 👥 Tim

**Kelas E · Kelompok 1** · Modul E-1 Pencatatan e-Faktur dan pajak proyek

| Nama |
|---|
| Raden Indra Prawirajaya |
| Aidil |
| Candra |
| Ismail Abdul Fathan |
| Raihan Azzani |

Dependensi: **F-1** (identitas dan hak akses) dan **D-2** (kontrak, termin, invoice). Konsumen hilir: **E-2**.

<p align="center"><sub>Dibuat untuk Capstone Proyek Perangkat Lunak · ERP PT Anaheim Nimbus Universal</sub></p>
