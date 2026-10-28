Engagement: Pentest/WAPT pada proyek PPL-E-1.

# Audit logika kritis — 25 Oktober 2026

Audit menemukan **tiga cacat baru** pada logika rantai pengganti, konsistensi
rekap, dan penanganan batas agregat. Satu kelemahan upload yang ditemukan pada
sesi sebelumnya juga tetap terkonfirmasi. Temuan paling penting: pembatalan
faktur di tengah rantai membuat satu invoice dihitung dua kali dan menaikkan
batas pencatatan pembayaran. Tidak ada dampak berseverity Critical/High yang
dibuktikan pada cakupan pengujian ini.

Implementasi bisnis belum diperbaiki dalam audit ini. Test baru merupakan
karakterisasi perilaku saat ini; assertion yang membuktikan cacat harus diubah
menjadi assertion penolakan/invariant setelah perbaikannya diterapkan.

## Metodologi dan cakupan

Metodologi utama: `bb-methodology` — map, discover, prove, validate, report.
Tujuan utama adalah integritas pencatatan pajak. Pemeriksaan dilakukan per
kelompok: transisi faktur dan transaksi; autentikasi/otorisasi; upload dan
penyajian data. Skill pendukung yang digunakan: `codebase-memory`,
`hunt-business-logic`, `hunt-race-condition`, `hunt-jwt-crypto`,
`hunt-auth-bypass`, `hunt-idor`, `hunt-api-misconfig`, `hunt-file-upload`,
`hunt-sqli`, `hunt-xss`, `triage-validation`, dan `report-writing`.

Cakupan mencakup kode aplikasi milik proyek: route, controller, middleware,
service, model, migration/constraint/trigger, integrasi D-2, ekspor Excel/PDF,
JavaScript UI, template Blade, konfigurasi, perintah CLI/seeder dan CI yang
terkait. Sebelas operasi API kontrak dan endpoint pembantu ditelusuri.
Dependency pihak ketiga tidak diaudit baris per baris dan tidak dilakukan
pencarian CVE/dependency advisory dalam sesi ini.

Eksplorasi memakai graph Tier 2, trace dan snippet, lalu membaca source aktual.
Coverage graph diperiksa pada 116 path inventaris awal dan path tambahan.
Graph mencatat parsing parsial pada `resources/views/layouts/app.blade.php`
baris 19–21; bagian tersebut telah dibaca langsung. Metadata graph bersifat
best effort, bukan bukti bahwa semua cabang kode telah diuji.

Basis penilaian adalah working tree saat audit, termasuk perubahan audit
pembayaran yang sudah ada sebelum sesi ini. Pengujian memakai PostgreSQL
`e1_pajak_test`, storage pengujian, dan fixture fiktif. Probe concurrency memakai
schema dan direktori sekali pakai yang dibersihkan setelah selesai; tidak
mengubah data produksi.

## Ringkasan temuan

| ID | Temuan | Penilaian WAPT | Prioritas |
| --- | --- | --- | --- |
| AUD-01 | Pembatalan faktur tengah mengaktifkan dua versi untuk satu invoice | Medium, integritas bisnis | P1 |
| AUD-02 | Snapshot lama menimpa read model rekap yang lebih baru | Low, konsistensi data | P2 |
| AUD-03 | Total agregat melebihi kapasitas menghasilkan 500 meski tiap input valid | Low, validasi/keandalan | P2 |
| AUD-04 | Penanda `%PDF-` palsu diterima sebagai dokumen pembayaran | Low, validasi dokumen; temuan sebelumnya | P2 |

Semua skenario perubahan data memerlukan peran yang memang berhak mengelola
E-1: Admin, CEO, atau COO. Penilaian tidak mengasumsikan akses pengguna biasa,
pengambilalihan akun, transfer uang, atau eksekusi kode server.

## AUD-01 — Pembatalan faktur tengah menggandakan penghitungan invoice

**Dampak yang dibuktikan:** pengguna berwenang membatalkan B pada rantai
A → B → C, sehingga A dan C menjadi aktif untuk invoice yang sama. Total PPN
yang sebelumnya Rp11.000.000 menjadi Rp22.000.000. Pencatatan pembayaran
Rp22.000.000 kemudian diterima karena batasnya memakai total yang sudah salah.
Ini melanggar invariant satu invoice : satu faktur aktif (Q-04).

**Lokasi:** `app/Services/Tax/TaxInvoiceService.php:229` dan `:328`, serta
`app/Models/FakturPajak.php:85`.

`update()` memeriksa apakah target CANCELLED tetapi tidak melarang pembatalan
target yang sudah digantikan. `cancel()` melepaskan
`replaces_tax_invoice_id` milik B. Akibatnya A kehilangan successor dan kembali
aktif, sementara C tetap menggantikan B. Kedua cabang kemudian lolos scope
`active()`/`counted()`. Advisory lock pada create tidak memperbaiki transisi ini.

**Reproduksi otomatis:**

```bash
php artisan test --compact tests/Feature/CriticalLogicAuditTest.php \
  --filter=test_cancel_middle_replacement_double_counts_one_invoice_and_increases_payment_limit
```

Urutan request yang direproduksi oleh test:

1. POST `/api/v1/tax-invoices/outgoing` untuk invoice `inv-demo-001`: buat A.
2. POST endpoint yang sama dengan `replacesTaxInvoiceId=A`: buat B.
3. POST endpoint yang sama dengan `replacesTaxInvoiceId=B`: buat C.
4. PATCH `/api/v1/tax-invoices/B`, JSON `{"changes":{"status":"CANCELLED"}}`.
5. GET `/api/v1/tax-recaps/2026-11`: `invoiceCount=2`, `totalPpn=22000000.00`.
6. POST pembayaran Rp22.000.000: diterima dan jumlah tersebut tersimpan.

Test memakai PDF valid dengan NSFP berbeda pada setiap versi, dan memeriksa
ID faktur aktif serta nilai DB. Bukti bukan hanya perbedaan status HTTP.

**Perbaikan:** tentukan kebijakan pembatalan versi historis. Pilihan sederhana
adalah menolak pembatalan faktur yang memiliki successor aktif dengan 409.
Jika pembatalan versi historis dibutuhkan, ubah seluruh rantai secara atomik
tanpa mengaktifkan dua cabang. Gunakan lock per invoice yang sama untuk create,
replace, cancel, dan transisi lain yang memengaruhi keaktifan. Tambahkan uji
rantai minimal tiga versi, termasuk lintas periode dan perubahan bersamaan.

## AUD-02 — Refresh rekap menimpa hasil baru dengan snapshot lama

**Dampak yang dibuktikan:** tabel `rekap_pajak_bulanan` menyimpan total PPN
Rp11.000.000 setelah koreksi faktur Rp22.000.000 sudah commit dan sempat
memperbarui tabel rekap ke nilai baru. Database sumber dan read model berbeda.

**Lokasi:** `app/Services/Tax/RecapService.php:80`.

`refresh()` membaca agregat melalui `compute()` sebelum melakukan UPSERT,
tanpa serialisasi per periode yang mencakup pembacaan tersebut. Penulisan row
di UPSERT tidak menjamin snapshot yang ditulis masih terbaru.

**Bukti dua proses:**

```bash
php scripts/audit-recap-concurrency.php
```

Proses A menjalankan `refresh()` dan dijeda tepat sebelum UPSERT memakai hook
query. Proses B menjalankan koreksi faktur melalui `TaxInvoiceService::update()`
dan commit rekap baru. Setelah A dilanjutkan, A menimpa rekap dengan total lama.
Lima interleaving terkontrol menghasilkan stale write **5/5**, pada nilai
Rp11→22 juta, Rp22→33 juta, hingga Rp55→66 juta. Bukti mentah tersedia pada
[AUDIT_LOGIC_CONCURRENCY_EVIDENCE.json](AUDIT_LOGIC_CONCURRENCY_EVIDENCE.json).

Ini menjalankan service aplikasi dan koneksi PostgreSQL nyata di dua proses,
bukan mock hasil agregat. Waktu jeda sengaja dikontrol; 5/5 bukan estimasi
peluang eksploitasi melalui jaringan. Schema uji menyalin kolom, index dan
CHECK tabel; FK/trigger tidak disalin dan tidak menjadi bagian dari bukti race
snapshot ini.

**Batas dampak:** API rekap/summary menghitung ulang dari sumber, sehingga
pengujian ini tidak membuktikan API tersebut menampilkan snapshot tersimpan
yang salah. Tidak ada bypass batas pembayaran dari race ini yang terbukti.
Pengujian pembayaran saling beririsan justru menunjukkan pemeriksaan ulang
setelah lock menolak request kedua, menyisakan satu catatan pembayaran.

**Perbaikan:** serialisasi refresh per periode sebelum `compute()` hingga
UPSERT selesai, misalnya advisory transaction lock yang dipakai konsisten
oleh penulis terkait. Tetapkan urutan lock lintas periode untuk menghindari
deadlock. Alternatifnya gunakan versi sumber untuk mencegah snapshot lama
menimpa versi yang lebih baru. Evaluasi juga snapshot konsisten untuk ekspor.

## AUD-03 — Overflow agregat dikembalikan sebagai kegagalan server

**Dampak yang dibuktikan:** perubahan DPP yang valid secara individual
ditolak dengan HTTP 500 `INTERNAL_ERROR` ketika jumlah seluruh DPP periode
melewati 16 digit integer. Data lama tetap utuh setelah rollback.

**Lokasi:** `app/Support/Money.php:13`,
`app/Services/Tax/RecapService.php:66`, serta kolom decimal(18,2) pada migration.

Reproduksi otomatis:

```bash
php artisan test --compact tests/Feature/CriticalLogicAuditTest.php \
  --filter=test_aggregate_overflow_returns_500_and_rolls_back_correction
```

Dua faktur awal masing-masing memiliki DPP Rp100 juta. DPP A diubah menjadi
`9999999899999999.99`; total periode menjadi `9999999999999999.99` dan diterima.
Perubahan DPP B menjadi `100000001.00` valid menurut validator per nilai,
tetapi membuat agregat `10000000000000000.99`. `Money::normalize()` melempar
exception yang dirender sebagai 500. Assertion membuktikan perubahan B
di-rollback dan rekap sebelumnya tetap dapat dibaca.

Tidak ada korupsi permanen, penghentian layanan keseluruhan, atau bypass
otorisasi yang dibuktikan. Ini dicatat sebagai kelemahan batas validasi dan
penanganan kesalahan, bukan klaim DoS besar.

**Perbaikan:** tentukan kapasitas agregat terpisah dari nilai satu faktur.
Jika jumlah di atas batas memang tidak didukung, validasi sebelum commit dan
kembalikan 400/409 yang spesifik. Jika perlu didukung, sesuaikan tipe kolom
agregat, helper uang, JSON dan ekspor secara menyeluruh; jangan beralih ke float.

## AUD-04 — Signature substring tidak membuktikan dokumen PDF

**Dampak yang dibuktikan:** e-Billing dan bukti yang bukan PDF diterima dan
disimpan ketika kontennya memuat `%PDF-` di 1.024 byte pertama. Dengan field
lain valid, status pembayaran menjadi `CONFIRMED`.

**Lokasi:** `app/Services/Tax/FileStore.php:34` dan
`app/Services/Tax/TaxPaymentService.php:49`, `:130`.

Reproduksi:

```bash
php artisan test --compact tests/Feature/UploadFilenameTamperingTest.php
```

Fixture inert `<?php /* q8v2m6z9r4 %PDF-1.7 */ ?>` diterima pada POST pembayaran
dan PATCH pelengkapan. Alur e-Faktur menolak fixture yang sama ketika parser
mencoba membaca isinya. Ekstensi `.php` tidak dipertahankan: storage memakai
nama buatan server dengan `.pdf`. Eksekusi PHP tidak dibuktikan.

**Perbaikan:** validasi struktur PDF pada e-Billing dan bukti dengan batas
sumber daya yang sesuai. Pertahankan penyimpanan privat, nama server, dan
penyajian non-executable. Validitas format tetap tidak membuktikan keaslian
transaksi. Detail tersedia pada [catatan upload](CATATAN_UJI_UPLOAD_FILENAME.md).

## Observasi kebijakan bisnis — bukan temuan keamanan definitif

**OBS-01 — NTPN dan bukti yang sama bisa dipakai pada dua pembayaran.**
Test `test_same_ntpn_and_proof_can_be_counted_as_two_payments` membuat dua
catatan Rp5.500.000 dengan billing ID berbeda tetapi NTPN dan byte bukti sama.
Keduanya CONFIRMED dan jumlah setor dihitung Rp11.000.000. Unique constraint
saat ini hanya pada billing ID. Belum ada aturan proyek yang tegas tentang
keunikan NTPN, pembagian satu penerimaan ke beberapa catatan, atau verifikasi
eksternal, sehingga ini tidak diperingkat sebagai fraud yang terkonfirmasi.
Tetapkan aturan bisnis dahulu; jika satu NTPN harus satu penerimaan, gunakan
normalisasi dan constraint yang sesuai serta catat koreksinya dalam audit trail.

**OBS-02 — Koreksi pajak dapat menghasilkan selisih plus setelah pembayaran.**
Test `test_paid_invoice_can_be_reduced_then_payment_confirmed_with_positive_difference`
mencatat pembayaran Rp11 juta, mengoreksi PPN menjadi Rp1, lalu melengkapi bukti.
Hasilnya `paymentDifference=10999999.00` dan status `CONFIRMED`.
Ini menunjukkan larangan lebih setor hanya diberlakukan saat create pembayaran.
Namun kontrak mendefinisikan CONFIRMED berdasarkan kelengkapan dokumen,
bukan kesesuaian jumlah, dan koreksi pajak setelah pembayaran dapat sah.
Tentukan apakah perlu status rekonsiliasi/peringatan/persetujuan tambahan;
jangan otomatis memblokir koreksi yang dibutuhkan untuk akurasi pembukuan.

## Kontrol yang diverifikasi dan hasil negatif

| Area | Bukti dan hasil |
| --- | --- |
| Autentikasi | Tanda tangan salah, token kedaluwarsa/tanpa exp, issuer/audience salah dan akun kosong ditolak oleh suite AuthTest. Probe alg:none serta perubahan role tanpa tanda tangan sah menghasilkan 401. |
| Otorisasi route | Matriks baru mencakup 12 operasi/helper pengelolaan: tanpa token 401 dan peran LayananE2 403; tidak tercipta data faktur/pembayaran. Hak summary tambahan tetap diuji oleh suite yang ada. |
| Batas kepemilikan | Tidak ada model tenant atau kebijakan kepemilikan per pembuat. Admin/CEO/COO memang memiliki hak bersama menurut config. Akses lintas pembuat tidak dilabeli IDOR. |
| Mass assignment | Field aktor, status dan URI bukti sisipan pada create pembayaran diabaikan; aktor asli dipertahankan. Koreksi faktur memakai allowlist field. |
| SQL injection | Review raw query menunjukkan SQL tetap/parameter terikat pada alur aplikasi. Probe quote/boolean, `%`, dan `_` pada pencarian nomor/proyek/klien tidak memperluas hasil dan tidak merusak DB. Ini bukan pembuktian seluruh kemungkinan SQLi mustahil. |
| Nominal dan pembayaran | BCMath mempertahankan presisi; lebih setor satu sen ditolak tanpa file/baris/audit parsial. Billing ID duplikat ditolak oleh suite pembayaran. Dua operasi pembayaran beririsan diuji dengan service/proses nyata: pemeriksaan ulang setelah lock menolak operasi kedua. |
| Riwayat dan rollback | Suite yang ada memverifikasi pencatatan aktor, snapshot perubahan pembayaran, retensi bukti lama, trigger append-only dan rollback kegagalan penulisan audit. Temuan lama tentang perubahan pembayaran tanpa riwayat tidak ditemukan lagi pada working tree ini. |
| Ekspor | Suite memverifikasi kesetaraan nominal JSON/XLSX/PDF dan sel Excel `=1+1` tetap bertipe string. Template PDF memakai escaping Blade. |
| UI | Review sink innerHTML menelusuri escaping data, textContent, encodeURIComponent dan safeNext. Empat tes helper JavaScript lulus. Tidak ada klaim XSS terkonfirmasi tanpa eksekusi browser. |
| Integrasi D-2 | Suite menguji respons gagal, JSON rusak, identitas berbeda dan field bertipe salah; tidak ada persistensi parsial. URL upstream berasal dari konfigurasi dan invoice ID di-encode. Integrasi D-2 nyata belum diuji. |
| Mode demo | Suite DevLoginTest memverifikasi endpoint mati saat fitur dinonaktifkan atau environment production. Penerbitan akun bebas dalam mode demo adalah perilaku eksplisit, bukan bukti auth bypass production. |

## Validasi, bukti, dan batas audit

Hasil akhir yang dijalankan:

| Pemeriksaan | Hasil |
| --- | --- |
| `php artisan test --compact` | 182 passed, 2.162 assertions |
| Test audit baru | 20 kasus termasuk matriks route; bagian dari hasil di atas |
| Test upload sesi sebelumnya | 8 kasus; bagian dari hasil di atas |
| `php scripts/audit-recap-concurrency.php` | stale write 5/5; guard pembayaran beririsan bekerja |
| `npm run test:unit` melalui nvm default | 4 passed, Node 26.12.0; tidak ada `.nvmrc` |
| Pint pada dua berkas audit PHP baru | Passed |
| `git diff --check` | Passed |

Berkas reproduksi baru:

- [CriticalLogicAuditTest.php](../tests/Feature/CriticalLogicAuditTest.php)
- [audit-recap-concurrency.php](../scripts/audit-recap-concurrency.php)
- [Bukti concurrency JSON](AUDIT_LOGIC_CONCURRENCY_EVIDENCE.json)

Script concurrency membaca profil test dari `phpunit.xml`, menolak database
yang bukan localhost/berakhiran `_test`, dan memerlukan tabel test sudah
dimigrasikan. Jalankan setelah suite test jika perlu menyiapkan tabelnya.
Schema dan upload probe dibersihkan pada akhir eksekusi.

Feature test berjalan melalui kernel Laravel dengan UploadedFile terurai,
bukan parser multipart native PHP lewat jaringan. Perilaku reverse proxy,
boundary multipart, web server deployment, rate limit/load besar, keaslian
PDF mitra, integrasi F-1/D-2/E-2 nyata, dan pemanfaatan pada sistem produksi
belum dibuktikan. Browser Playwright tidak dijalankan pada audit ini.

Gate validasi membatasi kesimpulan pada hasil DB, byte file, kontrol aktor,
dan respons yang benar-benar diuji. Temuan yang membutuhkan asumsi kebijakan
dipisahkan sebagai OBS-01/02. Tidak ada klaim pencurian data, kerugian bank,
RCE, atau privilege escalation dari bukti yang tersedia. Tes yang lulus
tidak berarti kelemahan telah diperbaiki.

## Urutan perbaikan

1. Tutup transisi pembatalan versi tengah dan pertahankan satu faktur aktif
   per invoice; serialisasikan perubahan rantai secara konsisten.
2. Cegah snapshot rekap lama menimpa yang lebih baru; uji urutan lock dan
   interleaving transaksi sebelum memilih strategi locking.
3. Perketat validasi struktur PDF dan tangani batas agregat sebagai galat
   bisnis yang dapat dipahami pengguna.
4. Putuskan aturan NTPN dan rekonsiliasi setelah koreksi pajak, lalu tuliskan
   kebijakan tersebut ke kontrak, constraint dan pengujian.
