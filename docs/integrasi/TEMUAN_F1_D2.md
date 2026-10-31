# Temuan F-1 dan D-2 — Integrasi E-1

Diperiksa 31 Oktober 2026 oleh E-1 melalui repo resmi yang dijalankan lokal. **Kode, kontrak, seed, dan lockfile F-1/D-2 tidak diperbaiki oleh E-1.** Pemeriksaan ini belum merupakan review atau persetujuan pemilik layanan.

| Pemilik | Versi sumber yang diuji | Bentuk layanan |
|---|---|---|
| [F-1](https://github.com/Izra29/PPL_F1) | `0feb37aafe9df9e871aaba9a5457093696ce38a0`, OpenAPI 0.4.0 | Server Node `demo/local-server.js`, JWT HS256 asli, penyimpanan memori. Bukan Laravel/backend produksi. |
| [D-2](https://github.com/eldyfg/PPL-D-2) | `b93fc4dadcf7916995c9e6c2f981f95cc039dd97` | Backend Laravel dan database PostgreSQL terpisah; D-1 di dalam D-2 masih registry simulasi. |

Seluruh status aktual di bawah dapat ditelusuri berdasarkan ID kasus pada [bukti HTTP](bukti/2026-10-31T07-43-25-100Z/bukti-http.json). Token dan kata sandi sudah disamarkan.

## Kesalahan dan ketidaksesuaian yang perlu dilaporkan

| ID | Kategori / bukti | Harapan dan hasil aktual | Dampak untuk E-1 | Pemilik / status |
|---|---|---|---|---|
| F1-INT-01 | Kebutuhan klaim JWT; F1-01, `claimsObserved`; [penerbit token](https://github.com/Izra29/PPL_F1/blob/0feb37aafe9df9e871aaba9a5457093696ce38a0/demo/local-server.js#L115) | JWT memiliki `accountId`, `roleCode`, `iat`, `exp`, tetapi tidak memiliki `iss` atau `aud`. Ini kebutuhan keamanan E-1 yang belum tersedia, bukan pelanggaran field wajib LoginResponse F-1. | Profil lokal integrasi mengosongkan ekspektasi issuer/audience secara eksplisit. Signature dan exp tetap wajib; algoritma tetap HS256. Produksi dan keputusan issuer/audience belum disepakati. | F-1; terbuka. Minta penerbitan iss/aud dan kesepakatan kunci serta rotasi. |
| F1-INT-02 | Autentikasi operasi akun; F1-GAP-01/02; [handler akun](https://github.com/Izra29/PPL_F1/blob/0feb37aafe9df9e871aaba9a5457093696ce38a0/demo/local-server.js#L129) | OpenAPI menerapkan bearerAuth. POST /accounts tanpa token tetap **201**, PUT /accounts/{accountId} tanpa token tetap **200**. Uji hanya memakai akun fiktif yang baru dibuat oleh penguji. | Status/peran adalah sumber otorisasi E-1; bila perubahan akun tidak dilindungi, kepercayaan tersebut belum aman untuk produksi. E-1 tetap memverifikasi JWT dan membaca status/peran terkini. | F-1; terbuka. Lindungi mutasi akun dengan autentikasi/otorisasi. E-1 tidak mengubah handler F-1. |
| D2-INT-01 | Signature, expiry, dan pemetaan peran; D2-04/05/06; [middleware](https://github.com/eldyfg/PPL-D-2/blob/b93fc4dadcf7916995c9e6c2f981f95cc039dd97/app/Http/Middleware/ModulD2AuthMiddleware.php#L39) | Token acak → **200** (harapan 401); JWT expired → **200** (harapan 401); token F-1 EMPLOYEE → **200** (harapan 403). Middleware membaca payload tanpa memverifikasi signature/exp; default peran petugas_keuangan dan tidak membaca roleCode F-1. | E-1 meneruskan token yang telah diverifikasi, tanpa mengirim X-User-Role untuk menaikkan hak. D-2 sendiri belum membuktikan pembatasan akses yang aman; kesepakatan hak baca E-1 masih dibutuhkan. | D-2 bersama F-1; terbuka. Terapkan verifikasi JWT dan aturan peran/service account; jangan percaya header peran dari klien. |

## Keterbatasan kontrak atau prototipe, bukan bug endpoint yang dijanjikan

| ID | Bukti | Batas dan penanganan E-1 |
|---|---|---|
| D2-REQ-01 | D2-07: GET /api/v1/invoices → 405; routes/api.php hanya POST dan GET detail | Endpoint daftar/pencarian invoice belum diterbitkan. E-1 memakai **input ID invoice** dalam mode HTTP; tidak mengarang katalog lokal sebagai data D-2 aktif. Daftar invoice perlu disepakati bila diperlukan. |
| D2-REQ-02 | D2-01, E1-04 | projectId tersedia pada `data.terkait.projectId`; clientId/nama proyek/klien tidak dikirim. E-1 sudah memperbaiki pemetaan nested projectId. Nilai yang tidak disediakan tetap null; field tambahan memerlukan kesepakatan. |
| F1-LIMIT-01 | README dan demo/local-server.js | Demo memakai password polos yang dipatok untuk akun seed dan state memori. Restart mengembalikan seed. Token sudah JWT asli; kemampuan prototipe ini tidak disebut sebagai keamanan/penyimpanan produksi. Launcher E-1 mengirim JWT_SECRET melalui environment proses secara eksplisit. |
| D2-LIMIT-01 | app/Services/D1ProjectServiceClient.php; config/services.php | Alur uji memakai registry mock proyek internal D-2. API D-1/F-6 nyata tidak diuji dan bukan dependensi langsung E-1. |

`npm ci` F-1 melaporkan 20 temuan dependensi (8 moderate, 10 high, 2 critical). Itu hasil audit pemasangan, **belum analisis keterjangkauan eksploitasi**; dependency/lockfile tidak diubah. Triage menjadi tugas F-1.

## Temuan lama yang sudah berubah pada versi terbaru

- Token placeholder F-1 telah diganti JWT bertanda tangan; password salah ditolak 401 dan akun terkunci/nonaktif 403. F1-01 sampai F1-05 membuktikan perilaku demo sekarang.
- Invoice D-2 sudah persisten: D2-08 membuat invoice, D2-09 membaca ulang nilai yang sama, D2-10 menolak pengulangan termin dengan 409.
- ID invoice tidak dikenal sekarang 404 (D2-02); tanpa token ditolak 401 (D2-03).
- Hilangnya projectId adalah kesalahan adapter **E-1**, sudah diperbaiki dan diuji pada E1-04. Respons tiruan F-1/D-2 di E-1 juga telah dihapus.

Temuan historis dari Oktober sebelumnya tidak otomatis berlaku pada commit baru. Tidak ada klaim bahwa pemilik upstream sudah menerima laporan ini; file ini disiapkan untuk dilaporkan kelompok E-1.

## Cara mengulang temuan

Ikuti [panduan integrasi](README.md) dan jalankan `node scripts/test-integration.mjs` pada runtime baru. Skrip membedakan **pemeriksaan E-1 yang harus lulus** dari **observasi kontrak upstream**: lima kasus D2-04/05/06 dan F1-GAP-01/02 sengaja merekam harapan yang belum terpenuhi, bukan melabeli respons 200 sebagai lulus keamanan. Pengujian hanya dilakukan pada server dan database lokal yang dibuat launcher.

## Uji ulang pada konfigurasi utama E-1

Uji repo yang sama diulang 31 Oktober 2026: suite 42 request/57 pemeriksaan E-1 lulus, dan lima observasi akses upstream tetap belum sesuai. Aplikasi utama 8080 sekarang mengaktifkan koneksi HTTP di .env; tidak memakai login/data mock E-1. Seluruh kode/kontrak/seed F-1 dan D-2 tetap bersih. [Bukti utama](bukti/2026-10-31T07-43-25-100Z/aktif-utama.json).
