# Integrasi E-1 dengan repo F-1 dan D-2

**Alur terbaru:** `composer integration` menyiapkan checkout branch `main`, dependensi, migrasi, dan seed E-1/D-2 tanpa menyalakan layanan. `composer integration run` menyalakan ketiga layanan dengan D-2 HTTP. Mode dummy SQLite dipilih eksplisit melalui `--d2=mock` pada setup dan run. Ikuti [panduan anggota](SETUP_ANGGOTA.md). Versi serta hasil pada tabel bukti di bawah merupakan catatan pengujian 31 Oktober, bukan bukti versi `main` saat ini.

[README proyek](../../README.md) · [Temuan upstream](TEMUAN_F1_D2.md) · [Bukti terbaru](bukti/2026-10-31T07-43-25-100Z/bukti-http.json) · [Koleksi Postman](../postman/DEP_D2_F1_Testing.postman_collection.json)

Hasil **31 Oktober 2026, diperiksa ulang pada konfigurasi utama**: E-1 Laravel benar-benar memanggil server JWT demo F-1 dan backend database D-2 melalui HTTP. Login, pencatatan e-Faktur, relasi proyek, pembayaran, rekap, serta ekspor diuji. Ini integrasi lokal terhadap implementasi repo resmi, **belum deployment produksi, review dua arah, atau pengujian aplikasi E-2**. Kode upstream tetap bersih.

| Layanan | Versi | Alamat lokal |
|---|---|---|
| E-1 | dev dasar ea57e08 + perubahan integrasi pada working tree; hash di [versi sumber](bukti/2026-10-31T07-43-25-100Z/versi-sumber.json) | http://127.0.0.1:8087 (utama dan suite terpisah) |
| F-1 | main 0feb37a; OpenAPI 0.4.0; Node demo, state memori | http://127.0.0.1:4100 |
| D-2 | main b93fc4d; Laravel dengan DB terpisah; D-1 internal masih mock | http://127.0.0.1:8026 |

## Siap diuji dari aplikasi utama

[Panduan anggota baru](SETUP_ANGGOTA.md) menjelaskan pemasangan E-1, PostgreSQL, serta perintah terpisah untuk menyiapkan F-1/D-2 dan menjalankan ketiga layanan pada setiap laptop.

Setup mengaktifkan login/cek akun F-1 dan HttpInvoiceProvider D-2 pada `.env` E-1, mempertahankan konfigurasi database utama, dan menanam referensi contoh tanpa membuat transaksi faktur/pembayaran.

Untuk menjalankan ketiga layanan pada Windows atau Linux dari folder PPL-E-1:

```powershell
composer integration
composer integration run
```

Buka http://127.0.0.1:8087/login dan gunakan akun fiktif resmi super.admin / Password123!. `composer integration run` boleh diulang saat layanan aktif: proses sehat dipertahankan dan hanya layanan yang berhenti yang dimulai. Gunakan `composer integration:stop` untuk berhenti. Sebelum mengulang setup, hentikan layanan. Restart memakai database yang sama; pemeriksaan seed mengisi baris yang hilang dan mempertahankan baris lama; F-1 memuat ulang seed JSON saat prosesnya dimulai.

Setup clone branch `main` ke checkout terkelola `../_integrasi/F1` dan `../_integrasi/D2`, tanpa patokan commit. Checkout tetangga tidak dipilih otomatis. Untuk memakai checkout sendiri, tentukan `F1_PATH` dan `D2_PATH` saat setup. Lokasi disimpan untuk `run`; checkout terkelola pada branch lain, detached HEAD, atau dengan perubahan lokal ditolak tanpa reset/pull. Checkout yang sudah ada tidak di-pull otomatis.

E-1 memakai database utama `.env`; D-2 memakai database integrasi terpisah. Setup memigrasi dan menanam seed keduanya, tanpa reset. Node/npm dijalankan melalui nvm; gunakan `.nvmrc` F-1/E-1 bila tersedia, atau versi default nvm pada Linux dan versi aktif nvm-windows. Linux juga memerlukan PHP posix, Bash, setsid, dan nohup. Port 4100/8026/8087 harus kosong saat setup. Detail instalasi dan pemulihan ada pada [SETUP_ANGGOTA.md](SETUP_ANGGOTA.md).

[Langkah uji kelompok](UJI_KELOMPOK.md) dan arsip [aktif-utama.json](bukti/2026-10-31T07-43-25-100Z/aktif-utama.json) / [pemulihan.json](bukti/2026-10-31T07-43-25-100Z/pemulihan.json) menyimpan bukti konfigurasi terdahulu. Class `RuntimeSetup` menyiapkan runtime dan koneksi F-1/D-2 tanpa profil `.env.integration`; jangan memakai urutan prepare/start lama untuk menggantikan dua perintah di atas.

## Mencoba melalui browser

Setelah setup dan run, buka http://127.0.0.1:8087/login. Akun seed fiktif F-1: **super.admin / Password123!** (ADMIN) atau **yuril.salsabila / Password123!** (COO). Login demo E-1 dimatikan pada profil ini.

Seed HTTP menyediakan ID **inv-demo-sample**, nomor **INV-2026-SAMPLE**, tanggal **11 Oktober 2026**, DPP **100000000.00**, PPN **11000000.00**, dan proyek **prj-demo-001**. Setup menyiapkan referensi. Setelah layanan berjalan, `TaxInvoiceSeeder` membuat faktur demo dari invoice sumber yang belum memiliki faktur; pembayaran tetap dicatat manual. D-2 belum menyediakan endpoint katalog resmi. Untuk runtime lokal, E-1 membaca ID dari database sumber secara read-only dan mengambil detail lewat HTTP; halaman daftar dan pemilihan invoice memakai hasil terverifikasi tersebut. Arsip PDF/bukti lama tidak otomatis sesuai dengan seed terbaru; cocokkan isi PDF dengan respons invoice aktual. Mode dummy beserta PDF pasangan tersedia dengan `--d2=mock` pada setup dan run.

Tanggal e-Billing/bukti contoh berada 9/10 November 2026 untuk masa Oktober; semua fiktif. E-1 mencatat isi PDF dan tidak menerbitkan faktur/DJP atau menentukan klasifikasi barang mewah. Nilai komersial pada katalog HTTP diambil dari respons detail D-2. Katalog lokal mencakup ID pada database sumber; di luar runtime lokal, katalog menggunakan ID referensi E-1 dan form tetap menerima ID langsung.

`demo:samples` tetap generator snapshot lokal; untuk bukti HTTP ini PDF dibuat dari respons aktual D-2 oleh `docs/arsip/integrasi-lama/generate-integration-samples.php`. Format PDF dan keterbacaan teks diverifikasi; keaslian transaksi/DJP tidak diklaim.

## Menjalankan pengujian yang sama

Untuk regresi aplikasi, gunakan `composer test` dengan database tests terpisah sesuai `phpunit.xml`. Jangan mengarahkan suite RefreshDatabase ke database aplikasi.

Suite browser `docs/arsip/integrasi-lama/test-integration.mjs` dan bukti 31 Oktober di bawah menggunakan profil pengujian lama, dengan E-1 pada `.env.integration`. Launcher terbaru memakai database utama E-1. Suite lama tersebut perlu disesuaikan sebelum digunakan kembali; jangan menjalankannya terhadap runtime aplikasi utama. Perintah prepare/start lama tidak lagi menyiapkan layanan secara mandiri.

Catatan arsip: suite lama merekam **42 kasus request dengan 57 pemeriksaan E-1 lulus**, tiga screenshot, hash keadaan DB/audit/berkas, PDF, dan log produsen. Cakupannya meliputi 11 operasi bisnis, ekspor, autentikasi, konflik transaksi, serta gangguan upstream. Hasil itu tetap bukti versi pada tabel arsip, bukan validasi commit terbaru.

Lima observasi upstream tidak memenuhi harapan kontrak; lihat temuan. PASS di ringkasan berarti E-1 menangani skenario yang diuji, bukan semua layanan upstream sudah aman.

Regresi lengkap: **219/219 tes lulus, 1.824 assertions** ([hasil](bukti/2026-10-31T07-43-25-100Z/regresi.json)). Untuk mengulang, gunakan DB tests terpisah dan APP_ENV=testing sesuai phpunit.xml; jangan mengarahkan suite RefreshDatabase ke DB aplikasi.

Runner konkurensi lama tersimpan di `docs/arsip/integrasi-lama/test-recap-concurrency.php` dan membutuhkan runtime lama. Untuk pengujian aktif, gunakan `php scripts/audit-recap-concurrency.php` pada database test sesuai `phpunit.xml`. Hasil [uji dua proses](bukti/2026-10-31T07-43-25-100Z/konkurensi.json): refresh menunggu lock sebelum compute dan dua pembayaran Rp6 juta terhadap total Rp9,9 juta menghasilkan satu 200 dan satu 400. Ini pengujian interleaving terkontrol, bukan stress test produksi.

## Konfigurasi penting

| Konfigurasi | Perilaku |
|---|---|
| F1_API_ENABLED=true | POST /api/auth/f1/login E-1 memanggil POST /auth/login F-1 dengan emailOrUsername/password. |
| F1_CLAIM_AKUN_ID=accountId; F1_CLAIM_ROLE=roleCode | Mengikuti JWT F-1 v0.4; field aktor API bisnis E-1 tetap createdByAkunId/actorAkunId. |
| F1_CHECK_ACCOUNT=true | Setiap API bisnis membaca GET /accounts/{accountId}/role. Akun inactive/locked dan role tidak berwenang ditolak; F-1 putus tidak memakai klaim lama sebagai fallback. |
| F1_JWT_ISSUER / AUDIENCE kosong pada profil lokal | JWT demo F-1 belum memiliki iss/aud. Pengecualian eksplisit pada lokal E-1 (.env utama maupun profil uji); signature, exp, dan algoritma tetap diperiksa. Produksi memerlukan kesepakatan F-1. |
| D2_DRIVER=http | Invoice dari D-2 HTTP; Bearer diteruskan setelah lolos middleware E-1. projectId dibaca dari data.terkait.projectId. |
| E1_DEV_LOGIN=false | Integrasi memakai token F-1, bukan token yang diterbitkan E-1. |

## Respons login pendukung dan gangguan

| HTTP | errorCode | Makna |
|---|---|---|
| 200 | — | accessToken, tokenType, expiresIn, accountId, roleCode; Cache-Control no-store. |
| 400 | INVALID_LOGIN | Input email/username atau password tidak valid. |
| 401 | INVALID_CREDENTIALS | F-1 menolak kredensial. |
| 403 | F1_ACCOUNT_BLOCKED | Login F-1 menolak akun terkunci/nonaktif. |
| 404 | NOT_FOUND | Login F-1 E-1 tidak diaktifkan. |
| 429 | TOO_MANY_REQUESTS | Batas 10 percobaan per menit/IP di E-1. |
| 502 | F1_INVALID_TOKEN / F1_INVALID_RESPONSE | Token, identitas, atau struktur respons upstream tidak valid. |
| 503 | F1_UNAVAILABLE | F-1 tidak terjangkau atau merespons gangguan/redirect; tidak memakai login tiruan pengganti. |

Pada API bisnis, token tidak sah 401 UNAUTHORIZED; peran/status ditolak 403 FORBIDDEN; pemeriksaan akun upstream tidak valid 502 F1_INVALID_RESPONSE; F-1 tidak tersedia 503 F1_UNAVAILABLE; D-2 tidak tersedia 503 D2_UNAVAILABLE. Invoice hilang 404 INVOICE_NOT_FOUND. Semua memakai amplop Error E-1.

OpenAPI E-1 diperbarui **0.2.0 → 0.2.1**, tetap 11 operasi bisnis. Respons 502/503 pemeriksaan F-1 dan enum error ditambahkan. Login pendukung didokumentasikan di sini; [referensi respons bisnis](../api/RESPON_API.md) dihasilkan ulang. Dokumen/kontrak sumber Pertemuan 1–5 tidak diubah, dan perubahan ini belum diklaim disepakati pasangan.

## Batas bukti dan observasi

Log f1-requests.ndjson/d2-requests.ndjson dibuat oleh observer di repo E-1, di luar source upstream. Observer merekam method/path/status/X-Request-ID tanpa token/password dan menjalankan handler asli. UI-01/02 dapat dikorelasikan dengan GET detail D-2 menggunakan ID yang sama; ini bukti panggilan aplikasi, bukan tangkapan response tiruan di E-1. Request observer tidak mengubah body respons produsen.

Konfirmasi kebutuhan agregat E-2 pada P5 dipertahankan, tetapi tidak ada aplikasi E-2 atau reviewer pasangan yang diuji di sesi ini. Matriks hak per aksi/objek tetap menunggu Q-19. Koreksi pajak setelah pembayaran yang menghasilkan selisih positif tetap observasi kebijakan (bukan otomatis memblokir koreksi sah); CONFIRMED menunjukkan kelengkapan dokumen.
