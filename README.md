# E-1 Pencatatan e-Faktur dan Pajak Proyek: API

API modul **E-1 (Kelas E, Kelompok 1)** untuk ERP PT Anaheim Nimbus Universal. Mencatat e-Faktur keluaran dari PDF,
mengoreksi/membatalkan dengan riwayat, merekap per masa pajak, mencatat pembayaran pajak (billing, NTPN, bukti),
mengekspor rekap (Excel/PDF), dan menyediakan ringkasan pajak untuk modul lain (E-2).

- **Stack:** Laravel 13 · PHP 8.4 · PostgreSQL · PHPUnit
- **Kontrak:** [`openapi.yaml`](openapi.yaml) (OpenAPI 3.1, 11 operasi `API-KE01-01` s.d. `API-KE01-11`) dan contoh respons di [`examples/`](examples)
- **Dependensi:** F-1 (token/peran) dan D-2 (invoice); keduanya dapat ditiru lokal (lihat di bawah)

## Mulai cepat

```bash
composer install
cp .env.example .env && php artisan key:generate

# PostgreSQL: buat role dan database (sesuaikan dengan .env)
psql -U postgres -c "CREATE ROLE e1_app LOGIN PASSWORD 'e1_dev_pass' CREATEDB;"
psql -U postgres -c "CREATE DATABASE e1_pajak OWNER e1_app;"
psql -U postgres -c "CREATE DATABASE e1_pajak_test OWNER e1_app;"

php artisan migrate --seed          # skema + data demo fiktif
composer serve                      # http://127.0.0.1:8080
```

`composer serve` menjalankan server PHP dengan `upload_max_filesize=6M` dan `post_max_size=16M`. Batas bawaan PHP (2 MB)
akan menolak berkas yang masih di bawah batas aplikasi 5 MB.

### Coba lewat curl

```bash
TOKEN=$(php artisan f1:token AKN-0002 Admin)       # token F-1 PALSU, hanya untuk lokal

curl -s http://127.0.0.1:8080/api/v1/tax-invoices -H "Authorization: Bearer $TOKEN"
curl -s "http://127.0.0.1:8080/api/v1/tax/summary?taxPeriod=2026-11" -H "Authorization: Bearer $TOKEN"
curl -s -X POST http://127.0.0.1:8080/api/v1/tax-invoices/extractions \
     -H "Authorization: Bearer $TOKEN" -F invoiceId=inv-demo-005 -F eFakturFile=@efaktur.pdf
```

## Operasi API

Semua di bawah `/api/v1`, header `Authorization: Bearer <token F-1>`. Sukses selalu HTTP 200 dengan amplop
`{"code","message","data"}`; gagal `{"code","message","data":null,"errorCode"}`.

| ID | Metode dan path | Fungsi | Fitur |
|---|---|---|---|
| API-KE01-01 | `POST /tax-invoices/extractions` | Pratinjau ekstraksi PDF e-Faktur (tidak menyimpan) | FTR-01 |
| API-KE01-02 | `POST /tax-invoices/outgoing` | Simpan faktur keluaran atau faktur pengganti | FTR-01, 03 |
| API-KE01-03 | `GET /tax-invoices` | Cari/filter faktur (nomor, proyek, klien, masa pajak, status, paging) | FTR-02 |
| API-KE01-04 | `GET /tax-invoices/{taxInvoiceId}` | Detail + item + tautan asal/pengganti | FTR-02 |
| API-KE01-05 | `PATCH /tax-invoices/{taxInvoiceId}` | Koreksi atau batalkan (`CANCELLED`) dengan riwayat | FTR-03 |
| API-KE01-06 | `GET /tax-invoices/{taxInvoiceId}/history` | Riwayat perubahan (append-only) | FTR-03 |
| API-KE01-07 | `GET /tax-recaps/{taxPeriod}` | Rekap masa pajak (+ daftar faktur dan pembayaran) | FTR-04 |
| API-KE01-08 | `POST /tax-payments` | Catat pembayaran (Draft/`INCOMPLETE` bila NTPN/bukti belum ada) | FTR-05 |
| API-KE01-09 | `GET /tax-recaps/{taxPeriod}/export?format=xlsx\|pdf` | Ekspor rekap (berkas biner, tanpa amplop) | FTR-06 |
| API-KE01-10 | `GET /tax/summary?taxPeriod=&projectId=` | Ringkasan pajak untuk modul lain (E-2) | FTR-07 |
| API-KE01-11 | `PATCH /tax-payments/{taxPaymentId}` | Lengkapi menjadi `CONFIRMED` (NTPN + bukti wajib) | FTR-05 |

Kode kesalahan lengkap ada pada `components/schemas/Error` di `openapi.yaml`.

## Aturan bisnis yang diterapkan

| Aturan | Sumber | Implementasi |
|---|---|---|
| 1 invoice : 1 faktur aktif | Q-04 | 409 `INVOICE_ALREADY_HAS_ACTIVE_TAX_INVOICE`; pencatatan per invoice diserialkan dengan advisory lock |
| NSFP tidak boleh ganda | Q-02 | indeks unik + 409 `DUPLICATE_NSFP` (juga pada pratinjau dan koreksi) |
| Status Normal/Pengganti/Dibatalkan | Q-07 | `NORMAL`/`REPLACEMENT`/`CANCELLED`; pengganti wajib menunjuk faktur asal (invoice sama) |
| Koreksi/batal tidak menghapus riwayat | Q-05, Q-06 | `riwayat_perubahan_faktur` append-only (trigger basis data menolak UPDATE/DELETE); tidak ada hard delete |
| Rekap tidak menghitung Dibatalkan dan faktur asal yang sudah diganti | Q-13 | `FakturPajak::active()`; daftar rekap tetap menampilkan semuanya dengan `isCounted` |
| Terkonfirmasi = NTPN + bukti | Q-10 | status `CONFIRMED` hanya bila keduanya ada; selain itu `INCOMPLETE` |
| Selisih jumlah setor vs total PPN tetap disimpan | FTR-KE01-05 | `paymentDifference` (bisa negatif); tidak menolak |
| Periode kosong = rekap nol | FTR-KE01-04 | 200 dengan nilai 0.00, tanpa membuat data |
| Berkas PDF maksimum 5 MB | keputusan 13 Okt | divalidasi dari isi (`%PDF-`), bukan ekstensi |

## Autentikasi dan hak akses

- `Authorization: Bearer <JWT>` dari F-1. Diperiksa: tanda tangan, `exp` (wajib), `iss`, `aud`. Gagal → **401** `UNAUTHORIZED`.
- Klaim identitas `akunId` dan peran `kodePeran` (nama dapat diubah lewat `F1_CLAIM_*`; `sub`/`role` diterima sebagai cadangan).
- Peran **Admin, CEO, COO** berhak penuh (BL-09; matriks per aksi menunggu Q-19). Peran lain → **403** `FORBIDDEN`.
- `E1_SUMMARY_EXTRA_ROLES` memberi peran layanan tambahan hak baca **API-KE01-10 saja** (konsumen E-2).
- `HS256` (rahasia bersama, `F1_JWT_SECRET`) atau `RS256` (kunci publik pada `F1_JWT_PUBLIC_KEY_PATH`).
  Pemilik kunci, masa token, dan nama klaim menunggu keputusan F-1 (OPEN-08).
- `php artisan f1:token {akunId} {role}` menerbitkan token palsu untuk pengembangan; dinonaktifkan di production.

## Integrasi D-2 (invoice)

Kontrak D-2 belum final, sehingga `D2_DRIVER=mock` (bawaan) membaca [`database/data/d2-invoices.json`](database/data/d2-invoices.json)
yang mengikuti `Skema_Data_Awal_D2` (`inv-demo-001` dan seterusnya). `D2_DRIVER=http` memanggil `D2_BASE_URL` +
`D2_INVOICE_PATH`; bila D-2 tidak dapat dihubungi, API memberi 503 `D2_UNAVAILABLE`. Antarmuka: `App\Services\Invoices\InvoiceProvider`.

## Ekstraksi PDF e-Faktur

`App\Services\EFaktur\EFakturPdfParser` membaca label tetap pada Faktur Pajak (Kode dan Nomor Seri, Harga Jual/Penggantian/
Uang Muka/Termin, Dikurangi Potongan Harga, Dikurangi Uang Muka, Dasar Pengenaan Pajak, Jumlah PPN, Jumlah PPnBM, tanggal).
Format angka Indonesia dan internasional diterima. **Contoh dokumen asli dari mitra (Q-17) belum diterima**, jadi parser
diuji dengan PDF fiktif; sesuaikan setelah contoh asli tersedia.

## Pengujian

```bash
composer test                 # PHPUnit; memerlukan database e1_pajak_test
```

Suite meliputi: autentikasi dan amplop, tiap operasi (sukses dan seluruh kode kesalahan), ekstraksi PDF, ekspor Excel/PDF,
dan **uji kontrak** yang memvalidasi respons nyata serta seluruh `examples/*.json` terhadap skema `openapi.yaml`.
Variabel `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` dapat diatur lewat environment.

## Struktur

```
app/Http/Controllers/Api/V1   controller tipis (4)
app/Http/Middleware           AuthenticateF1Token, ParseMultipartBody (PATCH multipart)
app/Services/Tax              TaxInvoiceService, RecapService, TaxPaymentService, HistoryRecorder, FileStore
app/Services/EFaktur          EFakturPdfParser
app/Services/Invoices         InvoiceProvider (mock/http D-2)
app/Services/Export           RecapReport, RecapWorkbook (xlsx), RecapExporter (pdf)
database/migrations           5 tabel E-1 + CHECK + trigger append-only
openapi.yaml, examples/       kontrak dan contoh (sumber kebenaran)
```

Penamaan kolom snake_case (JSON tetap camelCase sesuai kontrak). ID UUID v7, nominal `decimal(18,2)` sebagai string,
waktu UTC RFC 3339.

## Catatan keputusan dan hal terbuka

- Kolom `client_id` bertipe teks (03: UUID) agar menerima ID klien F-6 berprefiks; `tax_invoice_number` unik; `item_faktur.line_no`
  dan `pembayaran_pajak.updated_at` ditambahkan. Lainnya sesuai `03_Skema_Data_Awal`.
- `type` (`OUTGOING`/`INCOMING`) dan `vendorName` ada pada kontrak mengikuti 03; API hanya membuat `OUTGOING`.
- Membatalkan faktur pengganti melepas tautannya (03: hanya untuk `REPLACEMENT`) dan faktur asal berlaku kembali; riwayat kedua faktur dicatat.
- Satu masa pajak boleh memiliki lebih dari satu catatan pembayaran; status rekap `INCOMPLETE` selama ada yang Draft.
- Kompensasi kelebihan/kurang bayar **tidak dihitung** (Q-24 terbuka); hanya selisih yang disimpan dan ditampilkan.
- Menunggu mitra/tim: Q-19 (hak per aksi), Q-20 (faktur masukan), Q-21 (log hapus vs batal), Q-22 (data yang dibagikan), Q-23 (integrasi DJP).

## Docker (belum diverifikasi di mesin pengembangan)

```bash
docker compose up --build        # app :8080 + PostgreSQL
docker compose exec app php artisan db:seed --force
```
