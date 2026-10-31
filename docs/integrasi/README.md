# Integrasi E-1 dengan repo F-1 dan D-2

[README proyek](../../README.md) · [Temuan upstream](TEMUAN_F1_D2.md) · [Bukti terbaru](bukti/2026-10-31T07-43-25-100Z/bukti-http.json) · [Koleksi Postman](../postman/DEP_D2_F1_Testing.postman_collection.json)

Hasil **31 Oktober 2026, diperiksa ulang pada konfigurasi utama**: E-1 Laravel benar-benar memanggil server JWT demo F-1 dan backend database D-2 melalui HTTP. Login, pencatatan e-Faktur, relasi proyek, pembayaran, rekap, serta ekspor diuji. Ini integrasi lokal terhadap implementasi repo resmi, **belum deployment produksi, review dua arah, atau pengujian aplikasi E-2**. Kode upstream tetap bersih.

| Layanan | Versi | Alamat lokal |
|---|---|---|
| E-1 | dev dasar ea57e08 + perubahan integrasi pada working tree; hash di [versi sumber](bukti/2026-10-31T07-43-25-100Z/versi-sumber.json) | http://127.0.0.1:8080 (utama), 8087 (suite terpisah) |
| F-1 | main 0feb37a; OpenAPI 0.4.0; Node demo, state memori | http://127.0.0.1:4100 |
| D-2 | main b93fc4d; Laravel dengan DB terpisah; D-1 internal masih mock | http://127.0.0.1:8026 |

## Siap diuji dari aplikasi utama

[Panduan anggota baru](SETUP_ANGGOTA.md) menjelaskan pemasangan E-1, PostgreSQL, serta satu perintah yang otomatis menyiapkan F-1/D-2 dan menjalankan ketiga layanan pada setiap laptop.

Konfigurasi .env utama sekarang memakai login/cek akun F-1 dan HttpInvoiceProvider D-2; E1_DEV_LOGIN=false. Empat migrasi E-1 yang tertinggal sudah diterapkan pada laptop ini tanpa mengubah seluruh baris transaksi, rekap, audit, atau berkas lama. Composer serve juga membaca konfigurasi HTTP yang sama, tetapi hanya menjalankan E-1.

Untuk menjalankan ketiga layanan pada Windows atau Linux dari folder PPL-E-1:

```powershell
php artisan integration
```

Buka http://127.0.0.1:8080/login. Gunakan akun fiktif resmi super.admin / Password123!. Perintah `php artisan integration` boleh dipanggil ulang ketika layanan aktif: status berhasil ditampilkan, PID dipertahankan, dan hanya layanan yang berhenti yang dinyalakan. Jalankan `php artisan integration:stop` untuk berhenti. Alias Composer `composer integration` dan `composer integration:stop` tetap tersedia. Windows memakai PowerShell; Linux memakai Bash/nvm, `setsid`, `nohup`, PHP `posix`, dan `/proc`. Startup sesudah stop memakai database yang sama tanpa seed ulang D-2. Server F-1 tetap mereset data memorinya ketika proses F-1 benar-benar direstart, sesuai implementasi pemilik.

Di Linux, pasang nvm pada akun pengguna dan siapkan versi `default` (`nvm alias default <versi-terpasang>`). Launcher memuat `$NVM_DIR/nvm.sh` atau `~/.nvm/nvm.sh`, mengikuti `.nvmrc` F-1/E-1 jika tersedia. Checkout `../PPL_F1` dan `../PPL-D-2` diprioritaskan jika ada; jika tidak, dipakai `../_integrasi/F1` dan `../_integrasi/D2` (di-clone bila belum ada). Gunakan `F1_PATH=/lokasi/F1 D2_PATH=/lokasi/D2 php artisan integration` untuk lokasi lain. Log dan PID tersimpan pada direktori runtime privat yang sama. Penghentian memeriksa identitas PID dari `/proc`, sehingga PID yang telah dipakai ulang tidak dihentikan. Instruksi PowerShell di bawah tetap khusus Windows; profil utama Linux menggunakan perintah Artisan di atas.

Persiapan awal E-1/dependensi/PostgreSQL tetap diperlukan pada laptop baru sebagaimana bagian Menyiapkan laptop di bawah. Launcher otomatis mengunduh checkout F-1/D-2 yang telah diuji ke `../_integrasi`, memasang dependensinya bila belum ada, lalu menjalankan ketiga layanan. enable-integration.php otomatis menyiapkan runtime bila belum ada, mengaktifkan hanya kunci F1_*/D2_*/E1_DEV_LOGIN pada .env, membuat cadangan privat, serta mempertahankan APP_ENV, APP_KEY, database, dan storage utama. Aktivasi demo ini ditolak di production. Jalankan php artisan migrate pada instalasi E-1 yang belum memiliki schema terbaru.

[Langkah uji kelompok](UJI_KELOMPOK.md) memuat invoice/PDF siap pakai di laptop ini. Bukti konfigurasi utama, login, dan pratinjau HTTP: [aktif-utama.json](bukti/2026-10-31T07-43-25-100Z/aktif-utama.json); pemulihan/restart tanpa seed ulang: [pemulihan.json](bukti/2026-10-31T07-43-25-100Z/pemulihan.json).

## Menyiapkan laptop

Siapkan PHP/Composer/Node/PostgreSQL sesuai README utama. PHP perlu pdo_pgsql. Akun PostgreSQL pada .env harus dapat membuat database (CREATEDB); script membuat tiga DB baru dan tidak menghapus/reset database mana pun. Mode ini tidak menulis data ke database .env utama.

Struktur folder:

```text
folder-kerja/
  PPL-E-1/
  _integrasi/
    F1/    # clone https://github.com/Izra29/PPL_F1
    D2/    # clone https://github.com/eldyfg/PPL-D-2
```

1. Di E-1, jalankan `composer install`, `npm ci`, `npm run build`. Siapkan .env PostgreSQL dan APP_KEY sesuai README utama, lalu `php artisan config:clear`.
2. Jalankan `composer integration` untuk aplikasi utama. Launcher otomatis clone F-1 dan D-2 ke struktur di atas (pada revisi pada tabel), memasang dependensi, lalu menjalankan ketiga layanan. Bila checkout tersebut sudah ada, launcher tidak mengubahnya. Jangan menyalin source F-1/D-2 ke app E-1.
3. Untuk profil uji terpisah, dari E-1 jalankan:

```powershell
php scripts/prepare-integration.php
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/start-integration.ps1
```

prepare-integration.php menggabungkan .env dengan [.env.integration.example](../../.env.integration.example), menghasilkan rahasia JWT acak khusus demo, tiga DB baru (E-1, D-2, tests), dan storage terpisah. Profil lama disalin sebagai cadangan di runtime privat. .env.integration dan storage privat diabaikan Git. Launcher memigrasikan E-1, memigrasikan/seed D-2, lalu menjalankan server resmi ketiganya. Database E-1 integrasi awal kosong; tidak menjalankan DemoSeeder agar sumber invoice benar-benar HTTP D-2.

Port 4100/8026/8087 harus kosong; launcher tidak mematikan proses lain. Jika lokasi clone berbeda, gunakan parameter `-F1Path` dan `-D2Path`. Semua proses background memakai jendela tersembunyi. Stop hanya memeriksa PID, waktu mulai, dan port yang dimiliki runtime ini.

## Mencoba melalui browser

Untuk profil uji terpisah, buka http://127.0.0.1:8087/login. Untuk aplikasi utama, gunakan composer integration dan port 8080. Gunakan akun seed demo resmi **super.admin / Password123!** (ADMIN) atau **yuril.salsabila / Password123!** (COO). Ini kredensial fiktif publik dari source F-1, bukan akun produksi. Login demo E-1 dimatikan pada profil ini.

Di Catat e-Faktur, masukkan ID invoice D-2 **inv-demo-sample**. Invoice seed bertanggal **11 Oktober 2026**, DPP 100000000.00, PPN 11000000.00, proyek prj-demo-001. PDF pasangannya tersedia di [input bukti](bukti/2026-10-31T07-43-25-100Z/input/manifest.json), khususnya [efaktur.pdf](bukti/2026-10-31T07-43-25-100Z/input/efaktur.pdf). Invoice tersebut untuk jasa; PPnBM 0.00. Pilih masa **2026-10** ketika membaca rekap. Pada DB baru, lakukan Periksa PDF lalu Simpan Faktur.

Tanggal e-Billing/bukti contoh berada 9/10 November 2026 untuk masa Oktober; semua fiktif. E-1 mencatat isi PDF dan tidak menerbitkan faktur/DJP atau menentukan klasifikasi barang mewah. Mode HTTP tidak menyediakan katalog palsu: daftar invoice belum tersedia dari D-2, maka form menerima ID langsung.

`demo:samples` tetap generator snapshot lokal; untuk bukti HTTP ini PDF dibuat dari respons aktual D-2 oleh `scripts/generate-integration-samples.php`. Format PDF dan keterbacaan teks diverifikasi; keaslian transaksi/DJP tidak diklaim.

## Menjalankan pengujian yang sama

Suite otomatis memerlukan runtime baru/kosong. Hentikan aplikasi utama lebih dulu dengan composer integration:stop, kemudian jalankan prepare/start (tanpa -Main) seperti di atas agar memakai port 8087 dan DB terpisah. Lalu:

```powershell
node scripts/test-integration.mjs
php scripts/check-integration-evidence.php <folder-hasil-yang-dicetak>
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/stop-integration.ps1
```

Skrip membutuhkan Chromium Playwright atau Microsoft Edge yang terpasang. Jika belum ada, jalankan `npx playwright install chromium`. Path browser khusus dapat diberikan lewat PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH.

Skrip mencatat **42 kasus request dengan 57 pemeriksaan E-1 lulus**, tiga screenshot, hash keadaan DB/audit/berkas, input PDF, dan log produsen. Termasuk 11 operasi bisnis, PDF/XLSX, token hilang/rusak/expired, peran pegawai, akun locked/inactive, 404 invoice, retry 409, duplicate NTPN, dan lebih setor. Kasus terakhir sengaja mematikan proses D-2 dan F-1 milik runtime uji untuk memeriksa 503 tanpa data parsial. **Setelah skrip, layanan tersebut berhenti.** Buat runtime baru untuk mengulang demo dari awal. Jangan menjalankan pengujian pada DB yang sudah dipakai karena uji membutuhkan keadaan awal kosong.

Lima observasi upstream tidak memenuhi harapan kontrak; lihat temuan. PASS di ringkasan berarti E-1 menangani skenario yang diuji, bukan semua layanan upstream sudah aman.

Regresi lengkap: **219/219 tes lulus, 1.824 assertions** ([hasil](bukti/2026-10-31T07-43-25-100Z/regresi.json)). Untuk mengulang, gunakan DB tests terpisah dan APP_ENV=testing sesuai phpunit.xml; jangan mengarahkan suite RefreshDatabase ke DB aplikasi.

`php scripts/test-recap-concurrency.php <path-hasil.json>` memakai DB tests **baru/kosong** dari runtime yang sama, terpisah dari DB HTTP. Hasil [uji dua proses](bukti/2026-10-31T07-43-25-100Z/konkurensi.json): refresh menunggu lock sebelum compute dan dua pembayaran Rp6 juta terhadap total Rp9,9 juta menghasilkan satu 200 dan satu 400. Ini pengujian interleaving terkontrol, bukan stress test produksi.

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
