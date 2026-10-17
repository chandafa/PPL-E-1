# Panduan mencoba API E-1 di Postman

Koleksi berisi 62 request dan 196 pemeriksaan Postman untuk alur sukses serta kasus gagal.

## 1. Persiapan (sekali)

```bash
# database kosong agar alur di bawah menghasilkan angka yang sama
php artisan migrate:fresh
composer serve                  # http://127.0.0.1:8080
```

Opsional, hanya untuk request 5.4 (konsumen E-2): tambahkan `E1_SUMMARY_EXTRA_ROLES=LayananE2` pada `.env`, lalu restart server.

Pada Postman:

1. **Import** berkas `docs/postman/E1-API.postman_collection.json` (dan `E1-Local.postman_environment.json`, lalu pilih environment **E-1 Lokal**).
2. Berkas PDF ada di `docs/postman/samples/`. Agar terbaca otomatis: **Settings > General > Working directory** = folder `docs/postman`.
   Bila tidak, pilih ulang berkas pada tiap field bertipe File (form-data).
3. Jalankan folder **0** lebih dulu, lalu folder 1 sampai 7 berurutan (atau **Run collection**). Pengujian ulang dari awal: `php artisan migrate:fresh`.

Pembuat berkas PDF contoh: `php artisan demo:samples`.

Panduan ini dimulai dari database kosong dan membuat enam faktur melalui API. Paket
seeder UI berisi 32 faktur dan tidak boleh digabungkan ke urutan request ini jika ingin
angka yang sama. Untuk demo UI tanpa reset data, gunakan [Panduan Seeder Demo](../panduan/DEMO_SEEDER.md)
dan PDF invoice 006/007 yang sengaja belum dicatat.

## 2. Alur login

E-1 tidak memiliki endpoint login: login dimiliki **F-1**. Untuk lokal tersedia login tiruan (hanya aktif bila `E1_DEV_LOGIN=true`).

- **POST** `{{baseUrl}}/api/dev/f1/login`
- Body (raw, JSON):
  ```json
  { "akunId": "AKN-0002", "kodePeran": "Admin" }
  ```
- Respons berisi `data.accessToken`. Koleksi menyimpannya ke variabel `token`; seluruh request lain memakai
  **Authorization: Bearer {{token}}** (diset pada level koleksi).
- Peran yang berhak: **Admin, CEO, COO**. Peran lain (mis. `CTO`) mendapat 403.
- Pilihan lain tanpa Postman: `php artisan f1:token AKN-0002 Admin` mencetak token yang sama.

## 3. Alur lengkap, berurutan

Semua URL di bawah berawalan `{{baseUrl}}` = `http://127.0.0.1:8080`. Jenis body **form-data** berarti tab Body > form-data;
field bertipe **File** dipilih dari `docs/postman/samples/`.

### Faktur

**1.1 Pratinjau ekstraksi (API-KE01-01)** `POST /api/v1/tax-invoices/extractions`
- form-data: `invoiceId` = `inv-demo-001`, `eFakturFile` (File) = `efaktur-inv-demo-001.pdf`
- Hasil 200: `taxInvoiceNumber` `010.000-26.12345678`, `taxInvoiceDate` `2026-11-01`, `taxPeriod` `2026-11`, `dpp` `100.000.000,00`, `ppn` `11.000.000,00`. Tidak menyimpan apa pun.

**1.2 Simpan faktur A (API-KE01-02)** `POST /api/v1/tax-invoices/outgoing`
- form-data: `invoiceId` = `inv-demo-001`, `eFakturFile` = `efaktur-inv-demo-001.pdf`
- Hasil 200: `status` `NORMAL`. `data.taxInvoiceId` disimpan ke variabel `taxInvoiceId`.

**1.3 Simpan faktur B** `POST /api/v1/tax-invoices/outgoing`
- `invoiceId` = `inv-demo-002`, `eFakturFile` = `efaktur-inv-demo-002.pdf` (2 item; DPP `250.000.000,00`, PPN `27.500.000,00`)

**1.4 Simpan faktur C (asal)** `POST /api/v1/tax-invoices/outgoing`
- `invoiceId` = `inv-demo-003`, `eFakturFile` = `efaktur-inv-demo-003.pdf` (DPP `75.000.000,00`, PPN `8.250.000,00`)

**1.5 Faktur pengganti C2** `POST /api/v1/tax-invoices/outgoing`
- `invoiceId` = `inv-demo-003` (HARUS sama dengan faktur asal), `eFakturFile` = `efaktur-inv-demo-003-pengganti.pdf`,
  `replacesTaxInvoiceId` = ID faktur C (variabel `{{originalTaxInvoiceId}}`)
- Hasil 200: `status` `REPLACEMENT`. Faktur C menjadi non-aktif (tidak dihitung pada rekap).

**1.6 dan 1.7 Faktur Desember**
- D: `invoiceId` = `inv-demo-004`, `eFakturFile` = `efaktur-inv-demo-004.pdf` (DPP `120.000.000,00`, PPN `13.200.000,00`, masa pajak `2026-12`)
- E: `invoiceId` = `inv-demo-005`, `eFakturFile` = `efaktur-inv-demo-005.pdf` (DPP `60.000.000,00`, PPN `6.600.000,00`, masa pajak `2026-12`)

**1.8 Daftar (API-KE01-03)** `GET /api/v1/tax-invoices?page=1&pageSize=20`
- Hasil: `pagination.totalItems` = 6, terurut tanggal faktur terbaru.
- Filter opsional (query): `taxInvoiceNumber` (sebagian nomor), `project` (ID atau nama), `client` (ID atau nama),
  `taxPeriod` (`2026-11`), `status` (`NORMAL`, `REPLACEMENT`, `CANCELLED`), `page`, `pageSize` (maks 100).
- Contoh yang diuji: `?taxPeriod=2026-11&status=NORMAL` → 3 faktur; `?project=gudang` → 3; `?client=Lintas` → 3.

**1.12 Detail (API-KE01-04)** `GET /api/v1/tax-invoices/{{taxInvoiceId}}`
- Memuat `taxInvoice`, `items`, `replacesTaxInvoiceId`, `replacedByTaxInvoiceId`.

**1.14 Koreksi (API-KE01-05)** `PATCH /api/v1/tax-invoices/{{taxInvoiceId}}`
- Body (raw, JSON):
  ```json
  { "changes": { "taxInvoiceDate": "2026-11-10" }, "note": "Koreksi tanggal faktur" }
  ```
- Field `changes` yang boleh: `taxInvoiceNumber`, `taxInvoiceDate` (`YYYY-MM-DD`), `taxPeriod` (`YYYY-MM`), `dpp`, `ppn`, `ppnbm`
  (**string** desimal, mis. `"90000000.00"`), `items`.

**1.15 Riwayat (API-KE01-06)** `GET /api/v1/tax-invoices/{{taxInvoiceId}}/history`
- Hasil: urutan `CREATE`, `UPDATE` dengan `beforeSnapshot` `{ "taxInvoiceDate": "2026-11-01" }` dan `afterSnapshot` `{ "taxInvoiceDate": "2026-11-10" }`.

**1.16 Batalkan faktur D** `PATCH /api/v1/tax-invoices/{{cancelTaxInvoiceId}}`
- Body (raw, JSON):
  ```json
  { "changes": { "status": "CANCELLED" }, "note": "Salah klien" }
  ```
- Pembatalan tidak boleh digabung dengan koreksi field lain. Faktur tidak dihapus dan tidak lagi dihitung.

### Rekap

**2.1 Rekap November (API-KE01-07)** `GET /api/v1/tax-recaps/2026-11`
- `invoiceCount` 3 (A, B, C2), `totalDpp` `420.000.000,00`, `totalPpn` `46.200.000,00`, `paymentStatus` `NOT_RECORDED`.
- `invoices` memuat 4 faktur; C tampil dengan `isCounted: false`.

**2.2** `GET /api/v1/tax-recaps/2026-12` → `invoiceCount` 1, `totalPpn` `6.600.000,00` (D dibatalkan, tampil tetapi tidak dihitung).

**2.3** `GET /api/v1/tax-recaps/2026-01` → rekap nol (200), bukan error.

### Pembayaran

**3.1 Catat pembayaran Draft (API-KE01-08)** `POST /api/v1/tax-payments`
- form-data: `taxPeriod` = `2026-11`, `taxYear` = `2026`, `amountPaid` = `46200000.00`, `billingId` = `BILL-202611-001`,
  `eBillingFile` (File) = `e-billing.pdf`
- Opsional: `ntpn` = `NTPN-ABC123456` dan `paymentProofFile` (File) = `bukti-pembayaran.pdf`. Bila **keduanya** diisi, langsung `CONFIRMED`.
- Hasil 200: `status` `INCOMPLETE`, `paymentDifference` `0,00`. `taxPaymentId` disimpan otomatis.

**3.3 Lengkapi pembayaran (API-KE01-11)** `PATCH /api/v1/tax-payments/{{taxPaymentId}}`
- form-data: `ntpn` = `NTPN-ABC123456`, `paymentProofFile` (File) = `bukti-pembayaran.pdf` (keduanya wajib)
- Hasil 200: `status` `CONFIRMED`, `paymentStatus` `CONFIRMED`.

**3.5 Kurang bayar** `POST /api/v1/tax-payments`
- `taxPeriod` = `2026-12`, `taxYear` = `2026`, `amountPaid` = `6000000.00`, `billingId` = `BILL-202612-001`, `ntpn` = `NTPN-XYZ987654`,
  `eBillingFile` = `e-billing.pdf`, `paymentProofFile` = `bukti-pembayaran.pdf`
- Hasil 200: `CONFIRMED`, `paymentDifference` `-600.000,00` (disimpan, tidak ditolak).

### Ekspor

**4.1** `GET /api/v1/tax-recaps/2026-11/export?format=xlsx` dan **4.2** `...?format=pdf`
- Berkas biner (tanpa amplop). Di Postman klik **Send and Download**. Nama: `rekap-pajak-2026-11.xlsx` / `.pdf`.

### Ringkasan untuk modul lain (E-2)

**5.1** `GET /api/v1/tax/summary?taxPeriod=2026-11` → `invoiceCount` 3, `totalDpp` `420.000.000,00`, `totalPpn` `46.200.000,00`, `paymentStatus` `CONFIRMED`.

**5.2** `GET /api/v1/tax/summary?taxPeriod=2026-11&projectId=prj-demo-002` → `invoiceCount` 2, `totalDpp` `320.000.000,00`, `totalPpn` `35.200.000,00`.

### Variasi data lain

**6.1 Koreksi nominal** `PATCH /api/v1/tax-invoices/{{taxInvoiceId}}`
```json
{ "changes": { "dpp": "90000000.00", "ppn": "9900000.00" }, "note": "Koreksi nilai sesuai e-Faktur final" }
```
- Lalu **6.2** `GET /api/v1/tax-recaps/2026-11`: `totalPpn` `45.100.000,00`, `paymentDifference` `1.100.000,00` (lebih bayar; kompensasi tidak dihitung).

**6.3 Koreksi item faktur B** `PATCH /api/v1/tax-invoices/{{taxInvoiceIdB}}`
```json
{
  "changes": {
    "items": [
      { "itemCode": "620200", "itemName": "Lisensi Aplikasi Gudang", "saleReplacementAdvanceTermAmount": "150000000.00", "discountAmount": "0.00", "receivedAdvanceAmount": "0.00" },
      { "itemCode": "620300", "itemName": "Jasa Implementasi dan Pelatihan", "saleReplacementAdvanceTermAmount": "100000000.00", "discountAmount": "1000000.00", "receivedAdvanceAmount": "0.00" }
    ]
  },
  "note": "Tambah potongan harga pada item kedua"
}
```

**6.4 Pindah masa pajak** `PATCH /api/v1/tax-invoices/{{taxInvoiceId}}`
```json
{ "changes": { "taxPeriod": "2026-12" }, "note": "Faktur dilaporkan pada masa pajak berikutnya" }
```
- Lalu **6.5** `GET /api/v1/tax-recaps/2026-12`: `invoiceCount` 2 (rekap lama dan baru dihitung ulang).

## 4. Kasus gagal (folder 7)

Setiap request sengaja salah. Pola respons: `{"code":"4xx","message":"...","data":null,"errorCode":"..."}`.

- **401 `UNAUTHORIZED`**: tanpa token. Request 7.1.
- **403 `FORBIDDEN`**: token peran `CTO` (login 0.2). Request 7.2.
- **400 `INVALID_FILE`**: unggah `bukan-pdf.txt`. 7.3.
- **400 `EXTRACTION_FAILED`**: unggah `e-billing.pdf` sebagai e-Faktur (PDF sah, bukan faktur). 7.4.
- **400 `INVALID_INVOICE_ID`**: `invoiceId` tidak dikirim. 7.5.
- **404 `INVOICE_NOT_FOUND`**: `invoiceId` = `inv-tidak-ada`. 7.6.
- **409 `INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE`**: catat ulang `inv-demo-001`. 7.7.
- **409 `DUPLICATE_NSFP`**: `inv-demo-004` dengan PDF bernomor sama dengan faktur A. 7.8.
- **400 `REPLACEMENT_INVOICE_MISMATCH`**: pengganti dengan `invoiceId` berbeda dari faktur asal. 7.9.
- **404 `TAX_INVOICE_NOT_FOUND`**: ID faktur `0191f3a0-7c1e-7a52-9d3b-4f6e2a1b8c99`. 7.11.
- **400 `INVALID_CHANGE`**: mengubah `invoiceId`, atau nominal bukan string (`"dpp": 90000000`). 7.12, 7.13.
- **409 `TAX_INVOICE_ALREADY_CANCELLED`**: mengubah faktur yang sudah dibatalkan. 7.14.
- **400 `INVALID_FILTER`**: `taxPeriod=2026-13` atau `status=APPROVED`. 7.16, 7.17.
- **400 `INVALID_TAX_PERIOD`**: `/tax-recaps/2026-13`. 7.18.
- **400 `INVALID_FORMAT`**: `format=csv`. 7.19.
- **400 `INVALID_PROJECT_ID`**: `projectId=prj-tidak-ada`. 7.20.
- **404 `TAX_RECAP_NOT_FOUND`**: bayar pada masa pajak `2027-01` (belum ada faktur). 7.21.
- **400 `INVALID_PAYMENT`**: `amountPaid` = `-1.00`, atau melengkapi tanpa bukti. 7.22, 7.23.
- **404 `TAX_PAYMENT_NOT_FOUND`**: ID pembayaran tidak dikenal. 7.24.

## 5. Aturan nilai yang perlu diingat

- Nominal pada input berupa **string** desimal, misalnya `"11000000.00"` (juga menerima `"11000000"`); respons menggunakan format Indonesia `"11.000.000,00"`.
- `taxPeriod` berformat `YYYY-MM`; `taxYear` harus sama dengan tahun pada `taxPeriod`.
- PDF maksimum **5 MB**. Server bawaan PHP menolak lebih awal bila `upload_max_filesize` kecil; gunakan `composer serve`.
- Satu invoice hanya boleh punya **satu faktur aktif**; NSFP tidak boleh ganda (termasuk faktur yang sudah dibatalkan).
- Sukses selalu HTTP **200** dengan `{"code":"200","message":"...","data":{...}}`.
