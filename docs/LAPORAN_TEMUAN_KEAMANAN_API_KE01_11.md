# LAPORAN REVALIDASI KEAMANAN & INTEGRITAS DATA
**Modul:** E-1 Pencatatan e-Faktur dan Pajak Proyek (ERP PT Anaheim Nimbus Universal)  
**Target Pengujian:** API-KE01-11 (`PATCH /api/v1/tax-payments/{taxPaymentId}`)  
**Metodologi:** Black-Box & Grey-Box Verification (Triage-Validation Framework)  
**Tanggal Pengujian:** 24 Oktober 2026  
**Status Basis Data:** Baseline Seeder Terverifikasi (150 Unit/Feature Tests Passed)  

---

## 1. INFORMASI TEMUAN & METADATA

| Parameter | Nilai / Deskripsi |
| :--- | :--- |
| **Judul Temuan** | *Untracked Silent Mutation of Settled Tax Receipts (NTPN) and Broken Audit Trail in Payment Confirmation* |
| **Kategori Kerentanan** | Business Logic / Data Integrity Violation / Audit Trail Breakdown (CWE-841 / CWE-778) |
| **Titik Lemah (Endpoint)** | `PATCH /api/v1/tax-payments/{taxPaymentId}` (API-KE01-11) |
| **Komponen Terdampak** | `App\Services\Tax\TaxPaymentService::complete()` & `App\Models\PembayaranPajak` |
| **Tingkat Keparahan** | **Medium (CVSS 3.1: 6.5)** |
| **Vektor CVSS v3.1** | `CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:N/I:H/A:N` |
| **Aktor yang Berwenang** | Pengguna terautentikasi dengan peran operasional: `Admin`, `CEO`, `COO` |

---

## 2. RINGKASAN EKSEKUTIF

Pada sistem pencatatan pajak E-1, nomor **NTPN** (*Nomor Transaksi Pajak Nasional*) adalah bukti sah penerimaan negara atas setoran pajak e-Billing yang telah lunas.

Hasil revalidasi mendalam membuktikan bahwa endpoint pelengkapan pembayaran (`PATCH /api/v1/tax-payments/{taxPaymentId}`):
1. **Memungkinkan penimpaan (*overwrite*) nomor NTPN dan berkas bukti pembayaran secara berulang kali**, bahkan pada pembayaran yang statusnya **sudah lunas dan dilaporkan pada bulan-bulan sebelumnya**.
2. **Tidak memiliki catatan riwayat perubahan (*zero audit trail*)**: Tidak seperti faktur yang memiliki tabel riwayat khusus yang dilindungi trigger basis data *append-only*, perubahan pembayaran tidak dicatat ke tabel riwayat mana pun.
3. **Mengabaikan identitas aktor pengubah (*loss of accountability*)**: Objek `$actor` yang dikirimkan oleh middleware otorisasi **dibuang begitu saja** oleh `TaxPaymentService::complete()`. Kolom pembuat (`created_by_akun_id`) tetap merujuk pada pembuat pertama, sehingga siapa pun yang mengubah atau memalsukan NTPN tidak meninggalkan jejak sama sekali.
4. **Anomali tersembunyi (*silent corruption*)**: Penggantian NTPN tidak mengubah nominal angka setoran (`amountPaid`), sehingga status pembayaran bulanan pada rekapitulasi tetap berstatus `CONFIRMED` dan tidak memicu peringatan selisih kas.

---

## 3. ANALISIS TEKNIS & AKAR MASALAH (DEEP DIVE)

### A. Inkonsistensi Desain Arsitektur (Faktur vs. Pembayaran)
Dalam repositori ini, pengembang telah menerapkan mekanisme audit kelas tinggi untuk faktur pajak (`faktur_pajak`), namun **sama sekali absen pada pembayaran pajak** (`pembayaran_pajak`):

```
[FAKTUR PAJAK]                             [PEMBAYARAN PAJAK]
      │                                             │
      ├──> Tabel: faktur_pajak                      ├──> Tabel: pembayaran_pajak
      │                                             │
      └──> Tabel: riwayat_perubahan_faktur          └──> (TIDAK ADA TABEL RIWAYAT)
             ├── action (CREATE, UPDATE, STATUS)           ├── ntpn tertimpa langsung
             ├── actor_akun_id (siapa pengubah)            ├── berkas bukti lama jadi yatim (orphaned)
             ├── before_snapshot & after_snapshot          └── identitas $actor DIBUANG
             └── Trigger: trg_riwayat_append_only
                 (Dilarang UPDATE/DELETE di PostgreSQL)
```

### B. Analisis Kode Sumber (`app/Services/Tax/TaxPaymentService.php`)

Perhatikan baris 108–144 pada `TaxPaymentService.php`:

```php
public function complete(mixed $taxPaymentId, mixed $ntpn, mixed $proofFile, F1Identity $actor): array
{
    // 1. Catatan diambil tanpa pengecekan status saat ini (tidak ada guard clause STATUS_CONFIRMED)
    $payment = is_string($taxPaymentId) && Str::isUuid($taxPaymentId)
        ? PembayaranPajak::query()->find($taxPaymentId)
        : null;

    if ($payment === null) {
        throw ApiException::notFound('TAX_PAYMENT_NOT_FOUND', 'Catatan pembayaran tidak ditemukan');
    }

    $ntpn = $this->optionalText($ntpn, 'ntpn', 100);
    if ($ntpn === null || $proofFile === null) {
        throw $this->invalid('NTPN dan bukti pembayaran wajib diisi untuk Terkonfirmasi');
    }

    $proof = $this->files->readPdf($proofFile, 'INVALID_PAYMENT', 'Bukti pembayaran harus berformat PDF');
    $proofUri = $this->files->put('payment-proof', $payment->tax_payment_id.'-'.Str::lower(Str::random(8)), $proof);

    try {
        // PERHATIKAN: Parameter $actor sama sekali TIDAK digunakan di dalam closure DB::transaction!
        return DB::transaction(function () use ($payment, $ntpn, $proofUri): array {
            // Berkas bukti lama tidak dihapus saat diganti (jejak audit).
            $payment->forceFill([
                'ntpn' => $ntpn,                   // <-- NTPN LAMA LANGSUNG HILANG TERTMPA
                'payment_proof_uri' => $proofUri,  // <-- POINTER DI DATABASE BERPINDAH KE BERKAS BARU
                'status' => PembayaranPajak::STATUS_CONFIRMED,
            ])->save();

            $recap = $this->recap->refresh($payment->tax_period);

            return ['payment' => $payment->fresh(), 'paymentStatus' => $recap->payment_status];
        });
    } catch (Throwable $e) {
        $this->files->delete($proofUri);
        throw $e;
    }
}
```

**Kelemahan Kunci:**
1. **Pengecekan Status Absen:** Tidak ada validasi apakah transaksi sudah berstatus `CONFIRMED`.
2. **Pengabaian Aktor (`$actor` Discarded):** Fungsi menerima `F1Identity $actor`, namun `$actor->akunId` tidak pernah disimpan ke basis data. Kolom `created_by_akun_id` tidak berubah, dan tidak ada kolom `updated_by_akun_id`.
3. **Penyimpanan Berkas Yatim (*Orphaned Files*):** Komentar pengembang menyatakan *"Berkas bukti lama tidak dihapus saat diganti (jejak audit)"*. Namun, berkas fisik lama yang tersisa di penyimpanan lokal tidak lagi memiliki referensi di baris basis data mana pun, sehingga mustahil mengetahui berkas mana milik versi NTPN yang mana tanpa forensik filesystem manual.

---

## 4. REVALIDASI MENGGUNAKAN DATA SEEDER

Pengujian dilakukan menggunakan data bawaan resmi dari `Database\Seeders\Support\DemoDataset`.

### A. Data Seeder yang Dijadikan Target Uji

1. **Target 1 (Draft Seeder):** Pembayaran Masa Pajak `2026-11`  
   - `tax_payment_id`: `01a16eb0-6600-7248-8054-fa3cf3e465e5`
   - `billing_id`: `BILL-202611-001`
   - `amount_paid`: `Rp 46.200.000,00`
   - Status Awal: `INCOMPLETE` (Disiapkan oleh seeder untuk diuji alur pelengkapannya).
2. **Target 2 (Closed/Settled Seeder):** Pembayaran Masa Pajak `2026-10`  
   - `tax_payment_id`: `01a16eb0-660e-709b-81c1-fb4acd6dee3d`
   - `billing_id`: `BILL-DEMO-202610-001`
   - `ntpn`: `NTPN-DEMO-202610-001`
   - Status Awal: `CONFIRMED` (Pembayaran masa lampau yang sudah lunas dan selesai).

---

### B. Prosedur Uji Skenario 1: Penimpaan Tak Terlacak Lintas Aktor (Target 1)

#### Langkah 1: Pelengkapan Sah Pertama oleh Admin (`AKN-0002`)
Admin mengonfirmasi Draft pembayaran dengan NTPN resmi sesuai panduan Postman (`NTPN-ABC123456`) menggunakan berkas resmi `docs/postman/samples/bukti-pembayaran.pdf`.

*HTTP Request:*
```bash
curl -X PATCH "http://127.0.0.1:8080/api/v1/tax-payments/01a16eb0-6600-7248-8054-fa3cf3e465e5" \
  -H "Authorization: Bearer <TOKEN_ADMIN_AKN-0002>" \
  -F "ntpn=NTPN-ABC123456" \
  -F "paymentProofFile=@docs/postman/samples/bukti-pembayaran.pdf;type=application/pdf"
```

*HTTP Response (200 OK):*
```json
{
  "code": "200",
  "message": "Pembayaran Terkonfirmasi",
  "data": {
    "taxPaymentId": "01a16eb0-6600-7248-8054-fa3cf3e465e5",
    "status": "CONFIRMED",
    "paymentStatus": "CONFIRMED"
  }
}
```

*Kondisi di Basis Data PostgreSQL:*
```text
tax_payment_id    : 01a16eb0-6600-7248-8054-fa3cf3e465e5
ntpn              : NTPN-ABC123456
payment_proof_uri : /files/tax/payment-proof/01a16eb0-6600-7248-8054-fa3cf3e465e5-nddlfwbu.pdf
status            : CONFIRMED
created_by_akun_id: AKN-0002
updated_at        : 2026-10-24 16:51:51+00
```

---

#### Langkah 2: Eksploitasi Penimpaan NTPN oleh Aktor Berbeda (`AKN-0001`, CEO)
Beberapa saat kemudian, pengguna dengan akun CEO (`AKN-0001`) memanggil endpoint yang sama untuk mengganti NTPN menjadi `NTPN-MODIFIED-777` dengan berkas bukti baru.

*HTTP Request:*
```bash
curl -X PATCH "http://127.0.0.1:8080/api/v1/tax-payments/01a16eb0-6600-7248-8054-fa3cf3e465e5" \
  -H "Authorization: Bearer <TOKEN_CEO_AKN-0001>" \
  -F "ntpn=NTPN-MODIFIED-777" \
  -F "paymentProofFile=@docs/postman/samples/bukti-pembayaran.pdf;type=application/pdf"
```

*HTTP Response:*
```json
{
  "code": "200",
  "message": "Pembayaran Terkonfirmasi",
  "data": {
    "taxPaymentId": "01a16eb0-6600-7248-8054-fa3cf3e465e5",
    "status": "CONFIRMED",
    "paymentStatus": "CONFIRMED"
  }
}
```
*(Status 200 OK diterima; tidak ada penolakan 409 Conflict).*

*Kondisi di Basis Data PostgreSQL Setelah Langkah 2:*
```text
tax_payment_id    : 01a16eb0-6600-7248-8054-fa3cf3e465e5
ntpn              : NTPN-MODIFIED-777                    <-- TERGANTI SECARA PERMANEN
payment_proof_uri : /files/tax/payment-proof/...-vq0gtw33.pdf <-- MENUNJUK BERKAS BARU
status            : CONFIRMED
created_by_akun_id: AKN-0002                             <-- JEJAK AKTOR CEO (AKN-0001) HILANG!
updated_at        : 2026-10-24 16:51:51+00
```

---

### C. Prosedur Uji Skenario 2: Penimpaan Transaksi Pajak Masa Lampau (Target 2)

Pengujian dilakukan terhadap pembayaran bulan Oktober 2026 (`BILL-DEMO-202610-001`) yang sudah berstatus `CONFIRMED` sejak seeder diinisialisasi.

*HTTP Request:*
```bash
curl -X PATCH "http://127.0.0.1:8080/api/v1/tax-payments/01a16eb0-660e-709b-81c1-fb4acd6dee3d" \
  -H "Authorization: Bearer <TOKEN_ADMIN>" \
  -F "ntpn=NTPN-TAMPERED-OCTOBER" \
  -F "paymentProofFile=@docs/postman/samples/bukti-pembayaran.pdf;type=application/pdf"
```

*Hasil Pengujian:*
1. **Respon:** HTTP 200 OK (`message: "Pembayaran Terkonfirmasi"`).
2. **Kondisi Basis Data:** Nilai asli seeder `NTPN-DEMO-202610-001` langsung terhapus dan berganti menjadi `NTPN-TAMPERED-OCTOBER`.
3. **Dampak Temporal:** Transaksi masa pajak lampau yang sudah tutup buku dan terhitung di laporan ekspor PDF/Excel dapat dimanipulasi sewaktu-waktu.

---

### D. Ringkasan Observasi Bukti Uji (*Fact Verification Table*)

| Parameter Pengamatan | Nilai Asli (Seeder) | Setelah Panggilan 1 | Setelah Panggilan 2 (Eksploitasi) | Evaluasi Keamanan |
| :--- | :--- | :--- | :--- | :--- |
| **Status Catatan** | `INCOMPLETE` | `CONFIRMED` | `CONFIRMED` | State machine tidak terkunci |
| **Nomor NTPN** | `null` | `NTPN-ABC123456` | `NTPN-MODIFIED-777` | **Integritas Rusak (Data Hilang)** |
| **Pointer Berkas Bukti** | `null` | `...-nddlfwbu.pdf` | `...-vq0gtw33.pdf` | Berkas lama terputus dari DB |
| **Pencatatan Aktor** | `AKN-0002` (Admin) | `AKN-0002` | `AKN-0002` | **Akuntabilitas Gagal (Aktor CEO Hilang)** |
| **Tabel Riwayat Audit** | Tidak Ada | Tidak Ada | Tidak Ada | **Audit Trail Tidak Tersedia** |
| **Status Rekap Bulanan** | `INCOMPLETE` | `CONFIRMED` | `CONFIRMED` | Terlihat valid/normal (Silent) |

---

## 5. DAMPAK BISNIS, AUDIT, & RISIKO

1. **Gagal Memenuhi Aspek *Non-Repudiation* (Penyangkalan Transaksi):**
   Karena parameter `$actor` diabaikan, sistem tidak dapat membuktikan siapa orang terakhir yang mengubah nomor setoran pajak negara tersebut. Ketika auditor eksternal atau DJP mendapati ketidakcocokan antara NTPN di ERP dengan sistem DJP, pihak manajemen tidak memiliki catatan riwayat (*changelog*) untuk mengidentifikasi pelaku pengubahan.
2. **Manipulasi Bukti Setor Pajak (*Compliance Violation*):**
   Seorang staf internal yang nakal dapat menyalin berkas bukti setor dari transaksi lain atau menginput NTPN fiktif ke dalam sistem internal pada transaksi yang sudah berstatus lunas, menutupi kelalaian atau penggelapan dana tanpa menimbulkan alarm pada laporan rekap bulanan.
3. **Risiko Integritas Pembuktian Hukum:**
   Dalam sengketa perpajakan, berkas digital yang dapat ditimpa tanpa riwayat versi (*versioning*) dan jejak audit (*tamper-evident audit log*) memiliki kekuatan pembuktian yang lemah.

---

## 6. REKOMENDASI PERBAIKAN

Terdapat dua pendekatan perbaikan tergantung pada kebutuhan bisnis modul E-1:

### Opsi A: *State Guard Strict* (Rekomendasi Utama — Sesuai Pola Kode Saat Ini)
Jika transaksi yang sudah berstatus `CONFIRMED` seharusnya bersifat final dan tidak boleh disunting sembarangan melalui endpoint pelengkap:

Tambahkan pengecekan status di awal `complete()` pada `app/Services/Tax/TaxPaymentService.php`:

```diff
--- a/app/Services/Tax/TaxPaymentService.php
+++ b/app/Services/Tax/TaxPaymentService.php
@@ -118,6 +118,10 @@ class TaxPaymentService
             throw ApiException::notFound('TAX_PAYMENT_NOT_FOUND', 'Catatan pembayaran tidak ditemukan');
         }

+        if ($payment->status === PembayaranPajak::STATUS_CONFIRMED) {
+            throw ApiException::conflict('PAYMENT_ALREADY_CONFIRMED', 'Catatan pembayaran sudah Terkonfirmasi dan tidak dapat diubah kembali');
+        }
+
         $ntpn = $this->optionalText($ntpn, 'ntpn', 100);
         if ($ntpn === null || $proofFile === null) {
```

*Efek:* Setiap upaya pemanggilan ulang pada pembayaran yang sudah `CONFIRMED` akan menghasilkan respon seragam **`HTTP 409 Conflict`**, melindungi keaslian NTPN pertama.

---

### Opsi B: *Full Audit Logging* (Jika Bisnis Mewajibkan Fitur Koreksi Bukti/NTPN)
Jika kontrak `openapi.yaml` (baris 1058: *"bukti/NTPN catatan terkonfirmasi diperbarui"*) memang ingin dipertahankan sebagai fitur koreksi resmi:

1. **Tambahkan Tabel Audit Pembayaran:** Buat migrasi tabel `riwayat_perubahan_pembayaran` yang identik dengan `riwayat_perubahan_faktur`.
2. **Simpan Snapshot Perubahan:** Catat `before_snapshot` (NTPN lama, bukti lama), `after_snapshot`, dan `actor_akun_id` yang diambil dari parameter `$actor->akunId`.
3. **Tautkan Versi Berkas Bukti:** Tambahkan relasi tabel lampiran berkas sehingga bukti pembayaran lama tidak menjadi *orphaned file* di filesystem, melainkan tercatat sebagai riwayat berkas versi 1, versi 2, dst.

---

## 7. VERIFIKASI KEBERSIHAN SISTEM (POST-VALIDATION STATE)

Seluruh data uji telah dibersihkan secara aman dan diverifikasi kembali:
* Basis data PostgreSQL telah dipulihkan persis sesuai dataset seeder (`BILL-202611-001` kembali ke `INCOMPLETE`, `BILL-DEMO-202610-001` kembali ke nilai awal).
* Seluruh berkas PDF sementara di direktori `storage/app/private/tax/payment-proof/` telah dihapus.
* Seluruh rangkaian pengujian unit dan fitur proyek dijalankan ulang: **150 tests passed, 1934 assertions (100% OK)**.
