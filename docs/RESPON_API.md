# Respons berhasil dan gagal setiap API

[Kembali ke README](../README.md) · [Cara menguji](PENGUJIAN_API.md) · [OpenAPI](../openapi.yaml)

Dihasilkan dari OpenAPI **0.2.0** dengan `php scripts/generate-api-reference.php`. Sumber field, tipe, enum, dan contoh tersedia pada kontrak yang sama di Swagger `/docs`.

## Ketentuan umum

- Seluruh sukses JSON memakai HTTP 200 dan amplop `{code,message,data}`; `code` string. API-KE01-09 mengirim berkas biner.
- Gagal memakai `{code,message,data:null,errorCode}`; `code` sama dengan status HTTP.
- Nominal JSON string decimal tanpa pemisah ribuan, misalnya `11000000.00`. Nilai uang tidak dikirim sebagai float.
- Tanggal `YYYY-MM-DD`, masa pajak `YYYY-MM`, waktu ISO 8601 UTC. PDF maksimum 5 MiB per berkas.
- ID UUID pada contoh adalah fiktif; gunakan ID dari hasil penyimpanan saat menguji.
- Token F-1 wajib: tanpa token/tidak sah = 401, peran ditolak = 403.
- `type` dan `vendorName` merupakan field tambahan yang sudah didokumentasikan. API pembuatan hanya menghasilkan `OUTGOING`. URI `/files/tax/...` adalah referensi penyimpanan privat, belum endpoint unduh publik.
- Q-13 tertutup: hanya NORMAL keluaran belum terganti dihitung. CANCELLED, REPLACEMENT, dan asal terganti tetap terlihat dengan `isCounted:false`.
- `status` pembayaran adalah satu catatan; `paymentStatus` adalah seluruh masa pajak. Satu Draft saja membuat rekap INCOMPLETE. Filter proyek tidak mengubah lingkup status pembayaran.
- `paymentDifference` = jumlah setor seluruh catatan periode, termasuk Draft, dikurangi total PPN. Kompensasi otomatis belum dibuat (Q-24).

Kesalahan umum di seluruh API: 404 `NOT_FOUND` (rute), 405 `METHOD_NOT_ALLOWED`, 413 `PAYLOAD_TOO_LARGE`, 500 `INTERNAL_ERROR` (termasuk gagal menyimpan PDF). Handler juga menggunakan 400 `VALIDATION_ERROR`, 429 `TOO_MANY_REQUESTS`, atau `HTTP_ERROR` untuk kesalahan framework yang relevan. Respons gagal tidak memuat rincian exception atau kredensial.

## API-KE01-01: Pratinjau hasil ekstraksi PDF e-Faktur

`POST /api/v1/tax-invoices/extractions`

Fitur FTR-KE01-01; dipakai UI-KE01-02 (internal). Hasil ekstraksi PDF e-Faktur untuk invoice D-2 yang dipilih ditampilkan sebagai pratinjau sebelum faktur disimpan; tidak menyimpan data. Sumber: Backlog BL-01; Peta Aktor dan Proses E-1 (Langkah 2–3); jawaban mitra Q-02 dan Q-04.

### HTTP 200

Hasil ekstraksi (belum tersimpan)

**sukses**

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
                "itemCode": "JASA-001",
                "itemName": "Jasa pengembangan perangkat lunak termin 1",
                "saleReplacementAdvanceTermAmount": "100000000.00",
                "discountAmount": "0.00",
                "receivedAdvanceAmount": "0.00"
            }
        ]
    }
}
```

### HTTP 400

Permintaan tidak valid: berkas bukan PDF; struktur PDF tidak sesuai standar sehingga ekstraksi gagal (tidak dibuat catatan faktur parsial); invoiceId kosong

**INVALID_FILE**: berkas bukan PDF

```json
{
    "code": "400",
    "message": "Berkas harus berformat PDF",
    "data": null,
    "errorCode": "INVALID_FILE"
}
```

**EXTRACTION_FAILED**: struktur PDF tidak sesuai standar sehingga ekstraksi gagal (tidak dibuat catatan faktur parsial)

```json
{
    "code": "400",
    "message": "PDF e-Faktur tidak dapat diproses",
    "data": null,
    "errorCode": "EXTRACTION_FAILED"
}
```

**INVALID_INVOICE_ID**: invoiceId tidak dikirim atau kosong

```json
{
    "code": "400",
    "message": "invoiceId wajib diisi",
    "data": null,
    "errorCode": "INVALID_INVOICE_ID"
}
```

### HTTP 401

Token tidak ada atau tidak sah

**UNAUTHORIZED**: token tidak ada atau tidak sah

```json
{
    "code": "401",
    "message": "Token tidak ada atau tidak sah",
    "data": null,
    "errorCode": "UNAUTHORIZED"
}
```

### HTTP 403

Pengguna sah, akses tidak cukup (peran bukan Admin, CEO, atau COO)

**FORBIDDEN**: peran bukan Admin, CEO, atau COO

```json
{
    "code": "403",
    "message": "Pengguna sah, akses tidak cukup",
    "data": null,
    "errorCode": "FORBIDDEN"
}
```

### HTTP 404

ID tidak ditemukan: invoiceId tidak dikenal D-2

**INVOICE_NOT_FOUND**: invoiceId tidak dikenal D-2

```json
{
    "code": "404",
    "message": "Invoice tidak ditemukan",
    "data": null,
    "errorCode": "INVOICE_NOT_FOUND"
}
```

### HTTP 409

Konflik status atau data: invoice sudah memiliki faktur aktif (1 invoice : 1 faktur, Q-04); NSFP pada PDF sudah terdaftar

**INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE**: invoice sudah memiliki faktur aktif (1 invoice : 1 faktur, Q-04)

```json
{
    "code": "409",
    "message": "Invoice ini sudah memiliki faktur aktif",
    "data": null,
    "errorCode": "INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE"
}
```

**DUPLICATE_NSFP**: NSFP pada PDF sudah terdaftar

```json
{
    "code": "409",
    "message": "NSFP sudah terdaftar",
    "data": null,
    "errorCode": "DUPLICATE_NSFP"
}
```

### HTTP 503

Layanan D-2 tidak dapat dihubungi sehingga invoiceId tidak dapat divalidasi

**D2_UNAVAILABLE**: API D-2 tidak tersedia (hanya bila D2_DRIVER=http)

```json
{
    "code": "503",
    "message": "Layanan D-2 tidak dapat dihubungi",
    "data": null,
    "errorCode": "D2_UNAVAILABLE"
}
```

## API-KE01-02: Simpan faktur keluaran atau faktur pengganti

`POST /api/v1/tax-invoices/outgoing`

Fitur FTR-KE01-01, FTR-KE01-03; dipakai UI-KE01-02, UI-KE01-03 (internal). Faktur Pajak Keluaran tersimpan dan tertaut ke invoice D-2 dari PDF e-Faktur, atau faktur pengganti yang wajib menunjuk faktur asalnya. Sumber: Backlog BL-01, BL-02; Peta Aktor dan Proses E-1 (Langkah 2–5); jawaban mitra Q-02, Q-04, Q-07.

### HTTP 200

Faktur tersimpan

**sukses**

```json
{
    "code": "200",
    "message": "Faktur tersimpan",
    "data": {
        "taxInvoiceId": "01a1950c-b380-73a9-91c4-7e20d5b86f14",
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
        "items": [
            {
                "itemCode": "JASA-001",
                "itemName": "Jasa pengembangan perangkat lunak termin 1",
                "saleReplacementAdvanceTermAmount": "100000000.00",
                "discountAmount": "0.00",
                "receivedAdvanceAmount": "0.00"
            }
        ],
        "createdByAkunId": "AKN-0002",
        "createdAt": "2026-11-01T03:00:00Z"
    }
}
```

### HTTP 400

Permintaan tidak valid: berkas bukan PDF; struktur PDF tidak sesuai standar sehingga ekstraksi gagal (tidak dibuat catatan faktur parsial); invoiceId faktur pengganti berbeda dari faktur asal; invoiceId kosong

**INVALID_FILE**: berkas bukan PDF

```json
{
    "code": "400",
    "message": "Berkas harus berformat PDF",
    "data": null,
    "errorCode": "INVALID_FILE"
}
```

**EXTRACTION_FAILED**: struktur PDF tidak sesuai standar sehingga ekstraksi gagal (tidak dibuat catatan faktur parsial)

```json
{
    "code": "400",
    "message": "PDF e-Faktur tidak dapat diproses",
    "data": null,
    "errorCode": "EXTRACTION_FAILED"
}
```

**REPLACEMENT_INVOICE_MISMATCH**: invoiceId faktur pengganti berbeda dari faktur asal

```json
{
    "code": "400",
    "message": "invoiceId faktur pengganti harus sama dengan faktur asal",
    "data": null,
    "errorCode": "REPLACEMENT_INVOICE_MISMATCH"
}
```

**INVALID_INVOICE_ID**: invoiceId tidak dikirim atau kosong

```json
{
    "code": "400",
    "message": "invoiceId wajib diisi",
    "data": null,
    "errorCode": "INVALID_INVOICE_ID"
}
```

### HTTP 401

Token tidak ada atau tidak sah

**UNAUTHORIZED**: token tidak ada atau tidak sah

```json
{
    "code": "401",
    "message": "Token tidak ada atau tidak sah",
    "data": null,
    "errorCode": "UNAUTHORIZED"
}
```

### HTTP 403

Pengguna sah, akses tidak cukup (peran bukan Admin, CEO, atau COO)

**FORBIDDEN**: peran bukan Admin, CEO, atau COO

```json
{
    "code": "403",
    "message": "Pengguna sah, akses tidak cukup",
    "data": null,
    "errorCode": "FORBIDDEN"
}
```

### HTTP 404

ID tidak ditemukan: invoiceId tidak dikenal D-2; replacesTaxInvoiceId tidak dikenal

**INVOICE_NOT_FOUND**: invoiceId tidak dikenal D-2

```json
{
    "code": "404",
    "message": "Invoice tidak ditemukan",
    "data": null,
    "errorCode": "INVOICE_NOT_FOUND"
}
```

**TAX_INVOICE_NOT_FOUND**: replacesTaxInvoiceId tidak dikenal

```json
{
    "code": "404",
    "message": "Faktur asal tidak ditemukan",
    "data": null,
    "errorCode": "TAX_INVOICE_NOT_FOUND"
}
```

### HTTP 409

Konflik status atau data: invoice sudah memiliki faktur aktif (bukan faktur pengganti); NSFP pada PDF sudah terdaftar; faktur asal yang akan diganti sudah Dibatalkan atau sudah diganti faktur lain

**INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE**: invoice sudah memiliki faktur aktif (bukan faktur pengganti)

```json
{
    "code": "409",
    "message": "Invoice ini sudah memiliki faktur aktif",
    "data": null,
    "errorCode": "INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE"
}
```

**DUPLICATE_NSFP**: NSFP pada PDF sudah terdaftar

```json
{
    "code": "409",
    "message": "NSFP sudah terdaftar",
    "data": null,
    "errorCode": "DUPLICATE_NSFP"
}
```

**TAX_INVOICE_ALREADY_CANCELLED**: faktur asal (replacesTaxInvoiceId) sudah Dibatalkan

```json
{
    "code": "409",
    "message": "Faktur asal sudah berstatus Dibatalkan",
    "data": null,
    "errorCode": "TAX_INVOICE_ALREADY_CANCELLED"
}
```

### HTTP 503

Layanan D-2 tidak dapat dihubungi sehingga invoiceId tidak dapat divalidasi

**D2_UNAVAILABLE**: API D-2 tidak tersedia (hanya bila D2_DRIVER=http)

```json
{
    "code": "503",
    "message": "Layanan D-2 tidak dapat dihubungi",
    "data": null,
    "errorCode": "D2_UNAVAILABLE"
}
```

## API-KE01-03: Cari dan filter faktur

`GET /api/v1/tax-invoices`

Fitur FTR-KE01-02; dipakai UI-KE01-01 (internal). Daftar faktur dicari dan difilter berdasarkan nomor faktur/NSFP, proyek, klien, masa pajak, dan status. Sumber: Backlog BL-04; Peta Aktor dan Proses E-1 (Langkah 6); jawaban mitra Q-16.

### HTTP 200

Daftar faktur

**sukses**

```json
{
    "code": "200",
    "message": "Daftar faktur",
    "data": {
        "items": [
            {
                "taxInvoiceId": "01a1950c-b380-73a9-91c4-7e20d5b86f14",
                "type": "OUTGOING",
                "taxInvoiceNumber": "010.000-26.12345678",
                "taxInvoiceDate": "2026-11-01",
                "invoiceId": "inv-demo-001",
                "vendorName": null,
                "projectId": "prj-demo-001",
                "clientId": null,
                "taxPeriod": "2026-11",
                "dpp": "100000000.00",
                "ppn": "11000000.00",
                "status": "NORMAL",
                "replacedByTaxInvoiceId": null
            }
        ],
        "pagination": {
            "page": 1,
            "pageSize": 20,
            "totalItems": 1
        }
    }
}
```

### HTTP 400

Permintaan tidak valid: taxPeriod bukan YYYY-MM atau status di luar enum

**INVALID_FILTER**: taxPeriod bukan YYYY-MM atau status di luar enum

```json
{
    "code": "400",
    "message": "Filter tidak valid: taxPeriod harus berformat YYYY-MM",
    "data": null,
    "errorCode": "INVALID_FILTER"
}
```

### HTTP 401

Token tidak ada atau tidak sah

**UNAUTHORIZED**: token tidak ada atau tidak sah

```json
{
    "code": "401",
    "message": "Token tidak ada atau tidak sah",
    "data": null,
    "errorCode": "UNAUTHORIZED"
}
```

### HTTP 403

Pengguna sah, akses tidak cukup (peran bukan Admin, CEO, atau COO)

**FORBIDDEN**: peran bukan Admin, CEO, atau COO

```json
{
    "code": "403",
    "message": "Pengguna sah, akses tidak cukup",
    "data": null,
    "errorCode": "FORBIDDEN"
}
```

## API-KE01-04: Detail faktur beserta item dan tautan asal/pengganti

`GET /api/v1/tax-invoices/{taxInvoiceId}`

Fitur FTR-KE01-02; dipakai UI-KE01-03 (internal). Detail satu faktur beserta item, status, dan tautan faktur asal/pengganti ditampilkan, termasuk detail yang dibuka dari rekap. Sumber: Backlog BL-04; Peta Aktor dan Proses E-1 (Langkah 6); jawaban mitra Q-03, Q-07, Q-16.

### HTTP 200

Detail faktur

**sukses**

```json
{
    "code": "200",
    "message": "Detail faktur",
    "data": {
        "taxInvoice": {
            "taxInvoiceId": "01a1950c-b380-73a9-91c4-7e20d5b86f14",
            "type": "OUTGOING",
            "invoiceId": "inv-demo-001",
            "vendorName": null,
            "projectId": "prj-demo-001",
            "clientId": null,
            "taxInvoiceNumber": "010.000-26.12345678",
            "taxInvoiceDate": "2026-11-01",
            "taxPeriod": "2026-11",
            "dpp": "100000000.00",
            "ppn": "11000000.00",
            "ppnbm": "0.00",
            "status": "NORMAL",
            "pdfUri": "/files/tax/efaktur-001.pdf",
            "createdByAkunId": "AKN-0002",
            "createdAt": "2026-11-01T03:00:00Z",
            "updatedAt": "2026-11-01T03:00:00Z"
        },
        "items": [
            {
                "itemCode": "JASA-001",
                "itemName": "Jasa pengembangan perangkat lunak termin 1",
                "saleReplacementAdvanceTermAmount": "100000000.00",
                "discountAmount": "0.00",
                "receivedAdvanceAmount": "0.00"
            }
        ],
        "replacesTaxInvoiceId": null,
        "replacedByTaxInvoiceId": null
    }
}
```

### HTTP 401

Token tidak ada atau tidak sah

**UNAUTHORIZED**: token tidak ada atau tidak sah

```json
{
    "code": "401",
    "message": "Token tidak ada atau tidak sah",
    "data": null,
    "errorCode": "UNAUTHORIZED"
}
```

### HTTP 403

Pengguna sah, akses tidak cukup (peran bukan Admin, CEO, atau COO)

**FORBIDDEN**: peran bukan Admin, CEO, atau COO

```json
{
    "code": "403",
    "message": "Pengguna sah, akses tidak cukup",
    "data": null,
    "errorCode": "FORBIDDEN"
}
```

### HTTP 404

ID tidak ditemukan: taxInvoiceId tidak dikenal

**TAX_INVOICE_NOT_FOUND**: taxInvoiceId tidak dikenal

```json
{
    "code": "404",
    "message": "Faktur pajak tidak ditemukan",
    "data": null,
    "errorCode": "TAX_INVOICE_NOT_FOUND"
}
```

## API-KE01-05: Koreksi atau batalkan faktur dengan riwayat

`PATCH /api/v1/tax-invoices/{taxInvoiceId}`

Fitur FTR-KE01-03; dipakai UI-KE01-03 (internal). Data faktur hasil ekstraksi dikoreksi atau faktur dibatalkan (status CANCELLED) tanpa menghilangkan riwayat lama. Sumber: Backlog BL-02, BL-03; Peta Aktor dan Proses E-1 (Langkah 4–5); jawaban mitra Q-05, Q-06, Q-07.

### HTTP 200

Faktur diperbarui

**sukses**

```json
{
    "code": "200",
    "message": "Faktur diperbarui",
    "data": {
        "taxInvoice": {
            "taxInvoiceId": "01a1950c-b380-73a9-91c4-7e20d5b86f14",
            "type": "OUTGOING",
            "invoiceId": "inv-demo-001",
            "vendorName": null,
            "projectId": "prj-demo-001",
            "clientId": null,
            "taxInvoiceNumber": "010.000-26.12345678",
            "taxInvoiceDate": "2026-11-01",
            "taxPeriod": "2026-11",
            "dpp": "100000000.00",
            "ppn": "11000000.00",
            "ppnbm": "0.00",
            "status": "NORMAL",
            "pdfUri": "/files/tax/efaktur-001.pdf",
            "createdByAkunId": "AKN-0002",
            "createdAt": "2026-11-01T03:00:00Z",
            "updatedAt": "2026-11-01T03:05:00Z"
        },
        "auditId": "01a19511-4760-70e7-bb5c-92a4f1d63b80"
    }
}
```

### HTTP 400

Permintaan tidak valid: field di luar yang diizinkan atau nilai tidak valid

**INVALID_CHANGE**: field di luar yang diizinkan atau nilai tidak valid

```json
{
    "code": "400",
    "message": "Field tidak boleh diubah atau nilai tidak valid",
    "data": null,
    "errorCode": "INVALID_CHANGE"
}
```

### HTTP 401

Token tidak ada atau tidak sah

**UNAUTHORIZED**: token tidak ada atau tidak sah

```json
{
    "code": "401",
    "message": "Token tidak ada atau tidak sah",
    "data": null,
    "errorCode": "UNAUTHORIZED"
}
```

### HTTP 403

Pengguna sah, akses tidak cukup (peran bukan Admin, CEO, atau COO)

**FORBIDDEN**: peran bukan Admin, CEO, atau COO

```json
{
    "code": "403",
    "message": "Pengguna sah, akses tidak cukup",
    "data": null,
    "errorCode": "FORBIDDEN"
}
```

### HTTP 404

ID tidak ditemukan: taxInvoiceId tidak dikenal

**TAX_INVOICE_NOT_FOUND**: taxInvoiceId tidak dikenal

```json
{
    "code": "404",
    "message": "Faktur pajak tidak ditemukan",
    "data": null,
    "errorCode": "TAX_INVOICE_NOT_FOUND"
}
```

### HTTP 409

Konflik status atau data: faktur sudah Dibatalkan sehingga tidak dapat diubah lagi; taxInvoiceNumber hasil koreksi sudah terdaftar pada faktur lain

**TAX_INVOICE_ALREADY_CANCELLED**: faktur sudah Dibatalkan sehingga tidak dapat diubah lagi

```json
{
    "code": "409",
    "message": "Faktur sudah berstatus Dibatalkan",
    "data": null,
    "errorCode": "TAX_INVOICE_ALREADY_CANCELLED"
}
```

**DUPLICATE_NSFP**: taxInvoiceNumber hasil koreksi sudah terdaftar pada faktur lain

```json
{
    "code": "409",
    "message": "NSFP sudah terdaftar",
    "data": null,
    "errorCode": "DUPLICATE_NSFP"
}
```

## API-KE01-06: Riwayat perubahan faktur

`GET /api/v1/tax-invoices/{taxInvoiceId}/history`

Fitur FTR-KE01-03; dipakai UI-KE01-03 (internal). Riwayat perubahan faktur ditampilkan secara kronologis (waktu, pengguna, aksi, nilai sebelum dan sesudah). Sumber: Backlog BL-02, BL-03; Peta Aktor dan Proses E-1 (Langkah 4–5); jawaban mitra Q-05, Q-06, Q-07.

### HTTP 200

Riwayat faktur

**sukses**

```json
{
    "code": "200",
    "message": "Riwayat faktur",
    "data": {
        "history": [
            {
                "auditId": "01a1950c-b380-74c1-88d2-7a05e9b3f671",
                "action": "CREATE",
                "actorAkunId": "AKN-0002",
                "changedAt": "2026-11-01T03:00:00Z",
                "beforeSnapshot": null,
                "afterSnapshot": {
                    "taxInvoiceNumber": "010.000-26.12345678",
                    "taxInvoiceDate": "2026-11-10",
                    "taxPeriod": "2026-11",
                    "dpp": "100000000.00",
                    "ppn": "11000000.00",
                    "ppnbm": "0.00",
                    "status": "NORMAL"
                },
                "note": null
            },
            {
                "auditId": "01a19511-4760-70e7-bb5c-92a4f1d63b80",
                "action": "UPDATE",
                "actorAkunId": "AKN-0002",
                "changedAt": "2026-11-01T03:05:00Z",
                "beforeSnapshot": {
                    "taxInvoiceDate": "2026-11-10"
                },
                "afterSnapshot": {
                    "taxInvoiceDate": "2026-11-01"
                },
                "note": "Koreksi tanggal faktur"
            }
        ]
    }
}
```

### HTTP 401

Token tidak ada atau tidak sah

**UNAUTHORIZED**: token tidak ada atau tidak sah

```json
{
    "code": "401",
    "message": "Token tidak ada atau tidak sah",
    "data": null,
    "errorCode": "UNAUTHORIZED"
}
```

### HTTP 403

Pengguna sah, akses tidak cukup (peran bukan Admin, CEO, atau COO)

**FORBIDDEN**: peran bukan Admin, CEO, atau COO

```json
{
    "code": "403",
    "message": "Pengguna sah, akses tidak cukup",
    "data": null,
    "errorCode": "FORBIDDEN"
}
```

### HTTP 404

ID tidak ditemukan: taxInvoiceId tidak dikenal

**TAX_INVOICE_NOT_FOUND**: taxInvoiceId tidak dikenal

```json
{
    "code": "404",
    "message": "Faktur pajak tidak ditemukan",
    "data": null,
    "errorCode": "TAX_INVOICE_NOT_FOUND"
}
```

## API-KE01-07: Rekap pajak per masa pajak

`GET /api/v1/tax-recaps/{taxPeriod}`

Fitur FTR-KE01-04; dipakai UI-KE01-04 (internal). Rekap per masa pajak bulanan ditampilkan: ringkasan faktur NORMAL keluaran yang belum terganti, daftar seluruh faktur periode, dan pembayaran. Sumber: Backlog BL-05; Peta Aktor dan Proses E-1 (Langkah 7); jawaban mitra Q-03, Q-12, Q-13.

### HTTP 200

Rekap masa pajak

**sukses**

```json
{
    "code": "200",
    "message": "Rekap masa pajak",
    "data": {
        "recap": {
            "taxPeriod": "2026-11",
            "invoiceCount": 1,
            "totalDpp": "100000000.00",
            "totalPpn": "11000000.00",
            "totalPpnbm": "0.00",
            "amountPaid": "11000000.00",
            "paymentDifference": "0.00",
            "paymentStatus": "CONFIRMED",
            "computedAt": "2026-12-10T02:00:05Z"
        },
        "invoices": [
            {
                "taxInvoiceId": "01a1950c-b380-73a9-91c4-7e20d5b86f14",
                "taxInvoiceNumber": "010.000-26.12345678",
                "invoiceId": "inv-demo-001",
                "projectId": "prj-demo-001",
                "clientId": null,
                "status": "NORMAL",
                "dpp": "100000000.00",
                "ppn": "11000000.00",
                "ppnbm": "0.00",
                "isCounted": true
            }
        ],
        "payments": [
            {
                "taxPaymentId": "01a25dad-c900-79c2-a6e1-b45d08af7362",
                "amountPaid": "11000000.00",
                "billingId": "BILL-202611-001",
                "ntpn": "NTPN-ABC123",
                "status": "CONFIRMED",
                "createdAt": "2026-12-10T02:00:00Z"
            }
        ]
    }
}
```

### HTTP 400

Permintaan tidak valid: taxPeriod bukan YYYY-MM

**INVALID_TAX_PERIOD**: taxPeriod bukan YYYY-MM

```json
{
    "code": "400",
    "message": "Masa pajak harus berformat YYYY-MM (contoh 2026-11)",
    "data": null,
    "errorCode": "INVALID_TAX_PERIOD"
}
```

### HTTP 401

Token tidak ada atau tidak sah

**UNAUTHORIZED**: token tidak ada atau tidak sah

```json
{
    "code": "401",
    "message": "Token tidak ada atau tidak sah",
    "data": null,
    "errorCode": "UNAUTHORIZED"
}
```

### HTTP 403

Pengguna sah, akses tidak cukup (peran bukan Admin, CEO, atau COO)

**FORBIDDEN**: peran bukan Admin, CEO, atau COO

```json
{
    "code": "403",
    "message": "Pengguna sah, akses tidak cukup",
    "data": null,
    "errorCode": "FORBIDDEN"
}
```

## API-KE01-08: Catat pembayaran dan pelaporan pajak

`POST /api/v1/tax-payments`

Fitur FTR-KE01-05; dipakai UI-KE01-05 (internal). Pembayaran dan pelaporan pajak untuk masa pajak yang dibuka dari rekap tercatat: jumlah setor, masa dan tahun pajak, kode billing, NTPN, e-Billing, dan bukti pembayaran. Sumber: Backlog BL-06; Peta Aktor dan Proses E-1 (Langkah 8); jawaban mitra Q-02, Q-09, Q-10.

### HTTP 200

Pembayaran tersimpan

**sukses**

```json
{
    "code": "200",
    "message": "Pembayaran tersimpan",
    "data": {
        "taxPaymentId": "01a25dad-c900-79c2-a6e1-b45d08af7362",
        "status": "INCOMPLETE",
        "taxPeriod": "2026-11",
        "paymentDifference": "0.00"
    }
}
```

### HTTP 400

Permintaan tidak valid: field wajib kosong, angka negatif, atau lampiran bukan PDF

**INVALID_PAYMENT**: field wajib kosong, angka negatif, atau lampiran bukan PDF

```json
{
    "code": "400",
    "message": "Data pembayaran tidak valid",
    "data": null,
    "errorCode": "INVALID_PAYMENT"
}
```

### HTTP 401

Token tidak ada atau tidak sah

**UNAUTHORIZED**: token tidak ada atau tidak sah

```json
{
    "code": "401",
    "message": "Token tidak ada atau tidak sah",
    "data": null,
    "errorCode": "UNAUTHORIZED"
}
```

### HTTP 403

Pengguna sah, akses tidak cukup (peran bukan Admin, CEO, atau COO)

**FORBIDDEN**: peran bukan Admin, CEO, atau COO

```json
{
    "code": "403",
    "message": "Pengguna sah, akses tidak cukup",
    "data": null,
    "errorCode": "FORBIDDEN"
}
```

### HTTP 404

ID tidak ditemukan: masa pajak belum memiliki rekap

**TAX_RECAP_NOT_FOUND**: masa pajak belum memiliki rekap

```json
{
    "code": "404",
    "message": "Masa pajak belum memiliki rekap",
    "data": null,
    "errorCode": "TAX_RECAP_NOT_FOUND"
}
```

## API-KE01-09: Ekspor rekap ke Excel atau PDF

`GET /api/v1/tax-recaps/{taxPeriod}/export`

Fitur FTR-KE01-06; dipakai UI-KE01-06 (internal). File rekap masa pajak dihasilkan dalam format Excel atau PDF; susunan kolom, isi, dan total identik dengan rekap di layar. Sumber: Backlog BL-07; Peta Aktor dan Proses E-1 (Langkah 9); jawaban mitra Q-15, Q-17.

### HTTP 200

File rekap

Badan respons berisi berkas, bukan JSON. `Content-Disposition: attachment; filename="rekap-pajak-{taxPeriod}.xlsx"` atau `.pdf`.

- `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
- `Content-Type: application/pdf`

Excel memuat Ringkasan, Faktur, Pembayaran. Nominal dan ID ditulis sebagai teks untuk menjaga seluruh digit; PDF menampilkan rupiah dengan format Indonesia.

### HTTP 400

Permintaan tidak valid: format selain xlsx atau pdf, atau taxPeriod tidak valid

**INVALID_FORMAT**: format selain xlsx atau pdf, atau taxPeriod tidak valid

```json
{
    "code": "400",
    "message": "Format ekspor hanya xlsx atau pdf",
    "data": null,
    "errorCode": "INVALID_FORMAT"
}
```

**INVALID_TAX_PERIOD**: taxPeriod bukan YYYY-MM

```json
{
    "code": "400",
    "message": "Masa pajak harus berformat YYYY-MM (contoh 2026-11)",
    "data": null,
    "errorCode": "INVALID_TAX_PERIOD"
}
```

### HTTP 401

Token tidak ada atau tidak sah

**UNAUTHORIZED**: token tidak ada atau tidak sah

```json
{
    "code": "401",
    "message": "Token tidak ada atau tidak sah",
    "data": null,
    "errorCode": "UNAUTHORIZED"
}
```

### HTTP 403

Pengguna sah, akses tidak cukup (peran bukan Admin, CEO, atau COO)

**FORBIDDEN**: peran bukan Admin, CEO, atau COO

```json
{
    "code": "403",
    "message": "Pengguna sah, akses tidak cukup",
    "data": null,
    "errorCode": "FORBIDDEN"
}
```

## API-KE01-10: Ringkasan pajak per masa pajak untuk modul lain

`GET /api/v1/tax/summary`

Fitur FTR-KE01-07; dipakai modul ERP lain, termasuk E-2. Ringkasan pajak (jumlah faktur aktif, total DPP, PPN, PPnBM, dan status pembayaran) per masa pajak untuk modul ERP lain, termasuk E-2 (arus kas dan dasbor keuangan). Sumber: Backlog BL-08; Peta Aktor dan Proses E-1 (Langkah 10); hasil minimum E-1 pada Pembagian Pekerjaan (API ringkasan pajak); jawaban mitra Q-22.

### HTTP 200

Ringkasan pajak

**sukses**

```json
{
    "code": "200",
    "message": "Ringkasan pajak",
    "data": {
        "taxPeriod": "2026-11",
        "invoiceCount": 1,
        "totalDpp": "100000000.00",
        "totalPpn": "11000000.00",
        "totalPpnbm": "0.00",
        "paymentStatus": "CONFIRMED"
    }
}
```

### HTTP 400

Permintaan tidak valid: taxPeriod bukan YYYY-MM; projectId tidak valid

**INVALID_TAX_PERIOD**: taxPeriod bukan YYYY-MM

```json
{
    "code": "400",
    "message": "Masa pajak harus berformat YYYY-MM (contoh 2026-11)",
    "data": null,
    "errorCode": "INVALID_TAX_PERIOD"
}
```

**INVALID_PROJECT_ID**: projectId tidak valid

```json
{
    "code": "400",
    "message": "projectId tidak valid",
    "data": null,
    "errorCode": "INVALID_PROJECT_ID"
}
```

### HTTP 401

Token tidak ada atau tidak sah

**UNAUTHORIZED**: token tidak ada atau tidak sah

```json
{
    "code": "401",
    "message": "Token tidak ada atau tidak sah",
    "data": null,
    "errorCode": "UNAUTHORIZED"
}
```

### HTTP 403

Pengguna sah, akses tidak cukup (konsumen atau pengguna tidak berwenang membaca ringkasan pajak)

**FORBIDDEN**: konsumen atau pengguna tidak berwenang membaca ringkasan pajak

```json
{
    "code": "403",
    "message": "Pengguna sah, akses tidak cukup",
    "data": null,
    "errorCode": "FORBIDDEN"
}
```

## API-KE01-11: Lengkapi pembayaran menjadi Terkonfirmasi

`PATCH /api/v1/tax-payments/{taxPaymentId}`

Fitur FTR-KE01-05; dipakai UI-KE01-05 (internal). Catatan pembayaran berstatus Draft/INCOMPLETE dilengkapi menjadi Terkonfirmasi (CONFIRMED); pelengkapan hanya dapat dilakukan bila NTPN dan bukti pembayaran ada. Sumber: Backlog BL-06; Peta Aktor dan Proses E-1 (Langkah 8); jawaban mitra Q-02 (update data dari rekap), Q-10.

### HTTP 200

Pembayaran Terkonfirmasi

**sukses**

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

### HTTP 400

Permintaan tidak valid: ntpn atau bukti pembayaran tidak ada, atau lampiran bukan PDF

**INVALID_PAYMENT**: ntpn atau bukti pembayaran tidak ada, atau lampiran bukan PDF

```json
{
    "code": "400",
    "message": "NTPN dan bukti pembayaran wajib diisi untuk Terkonfirmasi",
    "data": null,
    "errorCode": "INVALID_PAYMENT"
}
```

### HTTP 401

Token tidak ada atau tidak sah

**UNAUTHORIZED**: token tidak ada atau tidak sah

```json
{
    "code": "401",
    "message": "Token tidak ada atau tidak sah",
    "data": null,
    "errorCode": "UNAUTHORIZED"
}
```

### HTTP 403

Pengguna sah, akses tidak cukup (peran bukan Admin, CEO, atau COO)

**FORBIDDEN**: peran bukan Admin, CEO, atau COO

```json
{
    "code": "403",
    "message": "Pengguna sah, akses tidak cukup",
    "data": null,
    "errorCode": "FORBIDDEN"
}
```

### HTTP 404

ID tidak ditemukan: taxPaymentId tidak dikenal

**TAX_PAYMENT_NOT_FOUND**: taxPaymentId tidak dikenal

```json
{
    "code": "404",
    "message": "Catatan pembayaran tidak ditemukan",
    "data": null,
    "errorCode": "TAX_PAYMENT_NOT_FOUND"
}
```

